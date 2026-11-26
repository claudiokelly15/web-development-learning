<?php

namespace Tests\Feature;

use App\Actions\Payments\AnalyticsEvidence;
use App\Actions\Payments\FictionalFundsEvidence;
use App\Actions\Payments\FictionalPayoutProvider;
use App\Actions\Payments\FinancialReportExport;
use App\Actions\Payments\LedgerLifecycle;
use App\Actions\Payments\MerchantAnalytics;
use App\Actions\Payments\MerchantFinancialReports;
use App\Actions\Payments\MerchantNotificationService;
use App\Actions\Payments\MerchantWithdrawals;
use App\Actions\Payments\MockPaymentLedger;
use App\Actions\Payments\NotificationSchema;
use App\Actions\Payments\NotificationTransport;
use App\Actions\Payments\PaymentStore;
use App\Models\PlatformRoleAssignment;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDOException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\Support\AnalyticsStoreFixture;
use Tests\Support\FakeFundsAdmission;
use Tests\Support\FakeLedgerAdmission;
use Tests\Support\LedgerStoreFixture;
use Tests\Support\LifecycleStoreFixture;
use Tests\Support\MockPaymentJourney;
use Tests\Support\PaymentLinkStoreFixture;
use Tests\TestCase;

class P10NotificationsReportsTest extends TestCase
{
    use MockPaymentJourney;

    private MockPaymentLedger $ledger;

    private LedgerLifecycle $funds;

    private FakeFundsAdmission $fundsAdmission;

    private FictionalFundsEvidence $evidence;

    private MerchantNotificationService $notifications;

    private MerchantFinancialReports $reports;

    private NotificationTransport $transport;

    private function ready(): array
    {
        [$owner,$merchant] = $this->eligible();
        LedgerStoreFixture::install($this->paymentPdo);
        PaymentLinkStoreFixture::install($this->paymentPdo);
        AnalyticsStoreFixture::install($this->paymentPdo);
        LifecycleStoreFixture::install($this->paymentPdo);
        foreach ([...NotificationSchema::statements(), ...NotificationSchema::completionStatements()] as $sql) {
            $this->paymentPdo->exec($sql);
        }
        $this->paymentAdmission->allow = true;
        $admission = new FakeLedgerAdmission;
        $admission->actor = $owner->id;
        $admission->merchant = $merchant->id;
        $admission->allow = true;
        $this->ledger = new MockPaymentLedger($this->payments->engine, $admission);
        $this->fundsAdmission = new FakeFundsAdmission;
        $this->fundsAdmission->actor = $owner->id;
        $this->fundsAdmission->merchant = $merchant->id;
        $this->fundsAdmission->allow = true;
        $this->evidence = new FictionalFundsEvidence(str_repeat('S', 32), str_repeat('P', 32));
        $this->funds = new LedgerLifecycle($this->ledger, $this->fundsAdmission, $this->evidence);
        $this->transport = new class implements NotificationTransport
        {
            public string $status = 'delivered';

            public array $calls = [];

            public function send(string $channel, string $recipient, string $subject, string $message, string $deliveryId): array
            {
                $this->calls[] = compact('channel', 'recipient', 'subject', 'message', 'deliveryId');

                return ['status' => $this->status, 'receipt' => $this->status === 'delivered' ? 'fake_receipt_'.$deliveryId : null];
            }
        };
        $this->notifications = new MerchantNotificationService($this->ledger, $this->funds, fn (): int => max($this->paymentAdmission->time, $this->fundsAdmission->time), str_repeat('N', 32), $this->transport, fn (string $id, int $ownerId): string => '+255700000001');
        $this->reports = new MerchantFinancialReports($this->ledger, $this->funds, new MerchantAnalytics($this->ledger, new AnalyticsEvidence(str_repeat('A', 32))));
        app()->instance(MerchantNotificationService::class, $this->notifications);
        app()->instance(MerchantFinancialReports::class, $this->reports);
        app()->instance('p10.clock', fn (): int => $this->paymentAdmission->time);

        return [$owner, $merchant];
    }

    private function confirm($owner, $merchant): array
    {
        $payment = $this->createPayment($merchant)->assertOk()->json();
        $reference = $payment['reference'];
        $this->receiveEvent($this->event($reference))->assertOk();
        $id = $this->paymentPdo->query('SELECT id FROM payment_confirmations')->fetchColumn();
        $this->ledger->post($owner, $merchant->id, $id, $reference, 12500, 'TZS');

        return [$reference, $id];
    }

    private function denied(int $status, \Closure $operation): void
    {
        try {
            $operation();
            $this->fail('Expected rejection');
        } catch (HttpExceptionInterface $error) {
            $this->assertSame($status, $error->getStatusCode());
        }
    }

    public function test_payment_outbox_email_only_idempotency_retry_and_real_routes(): void
    {
        [$owner,$merchant] = $this->ready();
        $this->assertSame([], $this->notifications->sweep($owner, $merchant->id)['events']);
        [$reference] = $this->confirm($owner, $merchant);
        $history = $this->notifications->sweep($owner, $merchant->id);
        $this->assertCount(1, $history['events']);
        $this->assertCount(1, $history['deliveries']);
        $delivery = $history['deliveries'][0];
        $this->assertSame('email', $delivery['channel']);
        $this->assertSame('pending', $delivery['status']);
        $this->assertCount(1, $this->notifications->sweep($owner, $merchant->id)['events']);
        $this->transport->status = 'queued';
        $this->assertSame('queued', $this->notifications->deliver($owner, $merchant->id, $delivery['id'])['deliveries'][0]['status']);
        $this->assertCount(1, $this->transport->calls);
        $this->notifications->deliver($owner, $merchant->id, $delivery['id']);
        $this->assertCount(1, $this->transport->calls);
        $this->paymentAdmission->time += 61000;
        $this->transport->status = 'failed';
        $this->assertSame('retry_scheduled', $this->notifications->deliver($owner, $merchant->id, $delivery['id'])['deliveries'][0]['status']);
        $this->paymentAdmission->time += 61000;
        $this->transport->status = 'delivered';
        $this->assertSame('delivered', $this->notifications->deliver($owner, $merchant->id, $delivery['id'])['deliveries'][0]['status']);
        $this->notifications->deliver($owner, $merchant->id, $delivery['id']);
        $this->assertCount(3, $this->transport->calls);
        $this->assertSame($owner->email, $this->transport->calls[0]['recipient']);
        $this->assertStringContainsString($reference, $this->transport->calls[0]['message']);
        $this->assertStringContainsString('TZS 12,500', $this->transport->calls[0]['message']);
        $this->assertStringContainsString('EAT', $this->transport->calls[0]['message']);
        $this->assertStringNotContainsString($owner->email, json_encode($history));
        $base = '/merchants/'.$merchant->id;
        $this->get($base.'/notifications')->assertOk()->assertSee('Merchant notification history')->assertDontSee($owner->email);
        $this->get($base.'/reports')->assertOk()->assertSee('12,500');
        $csv = $this->get($base.'/reports/export/csv')->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8')->getContent();
        $this->assertStringContainsString($reference, $csv);
        $this->get($base.'/reports/export/pdf')->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get($base.'/reports/export/xlsx')->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $filtered = $this->reports->report($owner, $merchant->id, ['status' => 'failed'], $this->paymentAdmission->time);
        $this->assertCount(0, $filtered['transactions']);
        $this->assertSame(0, $filtered['confirmed_tzs']);
        $this->denied(422, fn () => $this->reports->report($owner, $merchant->id, ['source' => 'made_up'], $this->paymentAdmission->time));
        $this->get($base.'/reports?range=custom&from=2026-11-14&to=2026-11-14')->assertOk();
        $this->get($base.'/reports?range=custom&from=2026-02-30&to=2026-11-14')->assertUnprocessable();
        $this->get($base.'/notifications/'.$history['events'][0]['id'].'/document')->assertNotFound();
        $this->createPayment($merchant, ['idempotency_key' => 'p10-second-confirmation'])->assertOk();
        $second = $this->paymentPdo->query('SELECT reference FROM payment_intents WHERE reference<>'.$this->paymentPdo->quote($reference))->fetchColumn();
        $this->receiveEvent($this->event($second))->assertOk();
        $this->transport->status = 'failed';
        $next = array_values(array_filter($this->notifications->sweep($owner, $merchant->id)['deliveries'], fn (array $row): bool => $row['id'] !== $delivery['id']))[0];
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->notifications->deliver($owner, $merchant->id, $next['id']);
            $this->paymentAdmission->time += 61000;
        }
        $this->notifications->deliver($owner, $merchant->id, $next['id']);
        $next = array_values(array_filter($this->notifications->history($owner, $merchant->id)['deliveries'], fn (array $row): bool => $row['id'] === $next['id']))[0];
        $this->assertSame('permanently_failed', $next['status']);
        $this->assertSame(3, $next['attempts']);
        $this->assertCount(6, $this->transport->calls);
        $this->assertSame(2, count(array_unique(array_column($this->transport->calls, 'deliveryId'))));
        $other = User::factory()->create();
        $this->signIn($other);
        $this->get($base.'/reports')->assertNotFound();
        $this->get($base.'/notifications')->assertNotFound();
        $this->get($base.'/reports/export/pdf')->assertNotFound();
        $this->get($base.'/reports/export/xlsx')->assertNotFound();
        $this->denied(503, fn () => $this->notifications->preferences($other, $merchant->id, 'off'));
    }

    public function test_withdrawal_advice_is_not_receipt_and_confirmation_requires_reconciliation(): void
    {
        [$owner,$merchant] = $this->ready();
        [$reference,$confirmation] = $this->confirm($owner, $merchant);
        $body = $this->evidence->body(['event_id' => (string) Str::uuid(), 'merchant_id' => $merchant->id, 'payment_reference' => $reference, 'confirmation_id' => $confirmation, 'amount_tzs' => 12500, 'currency' => 'TZS', 'provider_transaction' => 'fictional_p10_settlement', 'occurred_at' => $this->fundsAdmission->time]);
        $settlement = $this->funds->settle($owner, $merchant->id, $body, $this->evidence->signature('settlement', $body));
        $this->assertSame(0, (int) $this->paymentPdo->query('SELECT count(*) FROM p10_documents')->fetchColumn());
        $settlementDocument = $this->get('/merchants/'.$merchant->id.'/documents/settlement/'.$settlement['journal_id'])->assertOk()->getContent();
        file_put_contents(dirname(__DIR__, 3).'/.recovery/p10-settlement.pdf', $settlementDocument);
        $provider = new FictionalPayoutProvider($this->evidence);
        $withdrawals = new MerchantWithdrawals($this->funds, $this->fundsAdmission, $this->evidence, $provider);
        $request = $withdrawals->request($owner, $merchant->id, 2500, 'fictional_destination_1', 'p10-withdrawal-key-001');
        $history = $this->notifications->sweep($owner, $merchant->id);
        $advice = array_values(array_filter($history['events'], fn ($event) => $event['kind'] === 'withdrawal.requested'))[0];
        $this->assertCount(3, $history['deliveries']);
        $document = $this->notifications->document($owner, $merchant->id, $advice['id']);
        $this->assertStringContainsString('payout NOT confirmed', $document['content']);
        $this->assertStringNotContainsString('fictional_destination_1', $document['content']);
        $this->assertStringContainsString('**** 0001', $document['content']);
        $this->assertSame($document['content'], $this->notifications->document($owner, $merchant->id, $advice['id'])['content']);
        $withdrawals->dispatch($owner, $merchant->id, $request['reference']);
        $body = $provider->eventBody($request['reference'], 'confirmed', $this->fundsAdmission->time);
        $withdrawals->receive($owner, $merchant->id, $body, $this->evidence->signature('payout', $body));
        $this->assertCount(2, $this->notifications->sweep($owner, $merchant->id)['events']);
        $withdrawals->reconcile($owner, $merchant->id, $request['reference']);
        $history = $this->notifications->sweep($owner, $merchant->id);
        $this->assertCount(3, $history['events']);
        $this->assertCount(5, $history['deliveries']);
        $this->assertSame(1, (int) $this->paymentPdo->query("SELECT count(*) FROM p10_documents WHERE kind='payout'")->fetchColumn());
        $this->assertSame(1, (int) $this->paymentPdo->query("SELECT count(*) FROM p10_documents WHERE kind='advice'")->fetchColumn());
        $receipt = array_values(array_filter($history['events'], fn ($event) => $event['kind'] === 'payout.confirmed'))[0];
        $pdf = $this->get('/merchants/'.$merchant->id.'/notifications/'.$receipt['id'].'/document')->assertOk()->getContent();
        $this->assertStringContainsString('Independently confirmed fictional payout', $pdf);
        $this->assertSame('%PDF-1.4', substr($pdf, 0, 8));
        file_put_contents(dirname(__DIR__, 3).'/.recovery/p10-receipt.pdf', $pdf);
        file_put_contents(dirname(__DIR__, 3).'/.recovery/p10-advice.pdf', $document['content']);
        $beforeDelivery = $this->funds->overview($owner, $merchant->id)['position'];
        $this->notifications->preferences($owner, $merchant->id, 'off');
        foreach ($history['deliveries'] as $delivery) {
            $this->notifications->deliver($owner, $merchant->id, $delivery['id']);
        }
        $this->assertSame(2, count(array_filter($this->transport->calls, fn ($call) => $call['channel'] === 'sms')));
        $this->assertCount(4, $this->transport->calls);
        $this->assertSame($beforeDelivery, $this->funds->overview($owner, $merchant->id)['position']);
        $this->assertCount(5, $this->notifications->sweep($owner, $merchant->id)['deliveries']);
        $this->assertSame(0, $this->funds->overview($owner, $merchant->id)['position']['operational_available_tzs']);
        $forged = (string) Str::uuid();
        $forgedPayload = json_encode(['reference' => $request['reference'], 'amount_tzs' => 2500, 'status' => 'Confirmed payout', 'occurred_at' => $this->fundsAdmission->time, 'destination_mask' => '**** 0001', 'evidence' => 'unproven_source'], JSON_THROW_ON_ERROR);
        $this->paymentPdo->prepare('INSERT INTO p10_events VALUES(?,?,?,?,?,?,?,?)')->execute([$forged, $merchant->id, 'payout.confirmed', 'unproven_source', $forgedPayload, hash('sha256', $forgedPayload), $this->fundsAdmission->time, $this->fundsAdmission->time]);
        $this->denied(409, fn () => $this->notifications->document($owner, $merchant->id, $forged));
        $export = $this->reports->export($owner, $merchant->id, [], $this->paymentAdmission->time, 'pdf');
        file_put_contents(dirname(__DIR__, 3).'/.recovery/p10-report.pdf', $export['content']);
        $chief = $this->chief();
        $this->fundsAdmission->chief = $chief->id;
        app()->instance(LedgerLifecycle::class, $this->funds);
        app()->instance('p10.chief-context', ['merchant' => $merchant->id, 'store' => new PaymentStore($this->paymentPdo), 'admission' => $this->fundsAdmission]);
        $this->get('/admin/reports')->assertOk()->assertSee('Chief application reporting');
        $this->get('/admin/reports')->assertOk()->assertSee('Scoped notification delivery')->assertDontSee($owner->email);
        $this->get('/admin/reports?format=csv')->assertOk();
        $this->get('/admin/reports?format=pdf')->assertOk();
        $staff = User::factory()->create();
        PlatformRoleAssignment::factory()->create(['user_id' => $staff->id, 'role' => 'admin']);
        $this->signIn($staff);
        $this->get('/admin/reports')->assertForbidden();
        $this->get('/admin/reports?format=pdf')->assertForbidden();
    }

    public function test_migration_constraints_retention_pdf_and_csv_safety(): void
    {
        config(['database.connections.p10_schema' => ['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true]]);
        $migration = require database_path('migrations/2026_11_22_140749_create_p10_notification_outbox.php');
        config(['database.default' => 'p10_schema']);
        try {
            $migration->up();
            $completionMigration = require database_path('migrations/2026_11_26_200746_create_p10_policy_documents.php');
            $completionMigration->up();
        } finally {
            config(['database.default' => 'sqlite']);
        }
        $db = DB::connection('p10_schema');
        $this->assertSame(':memory:', $db->getDatabaseName());
        $db->insert('INSERT INTO p10_events VALUES(?,?,?,?,?,?,?,?)', ['e1', 'm1', 'payment.confirmed', 'source', '{}', hash('sha256', '{}'), 1, 2]);
        $db->insert("INSERT INTO p10_deliveries VALUES('d1','m1','e1','email','encrypted','hash','pending',0,2,NULL,NULL)");
        foreach (["INSERT INTO p10_deliveries VALUES('d2','m2','e1','email','encrypted','hash','pending',0,2,NULL,NULL)", "INSERT INTO p10_deliveries VALUES('d2','m1','e1','sms','encrypted','hash','pending',0,2,NULL,NULL)", "UPDATE p10_events SET payload='changed' WHERE id='e1'", 'DELETE FROM p10_deliveries', "INSERT OR REPLACE INTO p10_events VALUES('e1','m1','payment.confirmed','source','changed','hash',1,2)"] as $sql) {
            try {
                $db->statement($sql);
                $this->fail('Constraint must reject mutation');
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }
        try {
            $migration->down();
            $this->fail('History must be retained');
        } catch (\LogicException) {
            $this->assertSame(1, $db->table('p10_events')->count());
        }
        $csv = FinancialReportExport::csv([['=SUM(1,1)', " \t@cmd", '+123', '-5', 'normal,quoted', '"quoted"']]);
        $this->assertStringContainsString("'=SUM", $csv);
        $this->assertStringContainsString("' \t@cmd", $csv);
        $this->assertStringContainsString("'+123", $csv);
        $pdf = FinancialReportExport::pdf('Safety', ['Literal (text) \\ <script>', $csv, ...array_fill(0, 60, 'A bounded report line')]);
        $this->assertStringContainsString('/Count 2', $pdf);
        $this->assertStringNotContainsString('/JavaScript', $pdf);
        $this->assertStringNotContainsString('/URI', $pdf);
        file_put_contents(dirname(__DIR__, 3).'/.recovery/p10-multipage.pdf', $pdf);
        $xlsx = FinancialReportExport::xlsx([['=SUM(1,1)', '@cmd', "\t+formula", '<xml>', 12500]]);
        $this->assertSame('PK', substr($xlsx, 0, 2));
        $this->assertStringNotContainsString('<f>', $xlsx);
        $this->assertStringContainsString('t="inlineStr"', $xlsx);
        $this->assertStringContainsString('&lt;xml&gt;', $xlsx);
        $this->assertStringNotContainsString('TargetMode="External"', $xlsx);
        file_put_contents(dirname(__DIR__, 3).'/.recovery/p10-safety.xlsx', $xlsx);
    }

    public function test_preferences_digests_eat_cutoffs_and_no_retroactive_duplicates(): void
    {
        [$owner,$merchant] = $this->ready();
        $base = '/merchants/'.$merchant->id;
        $this->assertSame('individual', $this->notifications->preferences($owner, $merchant->id)['mode']);
        $this->denied(422, fn () => $this->notifications->preferences($owner, $merchant->id, 'sms'));
        $this->notifications->preferences($owner, $merchant->id, 'daily');
        [$reference,$confirmation] = $this->confirm($owner, $merchant);
        $this->postJson($base.'/notifications/preferences', ['_token' => session()->token(), 'mode' => 'daily'])->assertRedirect();
        $this->postJson($base.'/notifications/preferences', ['_token' => session()->token(), 'mode' => 'sms'])->assertUnprocessable();
        $additional = $this->createPayment($merchant, ['idempotency_key' => 'p10-daily-second-payment'])->assertOk()->json('reference');
        $this->receiveEvent($this->event($additional))->assertOk();
        $history = $this->notifications->sweep($owner, $merchant->id);
        $this->assertCount(2, $history['events']);
        $this->assertCount(0, $history['deliveries']);
        $due = (int) $this->paymentPdo->query('SELECT due_at FROM p10_payment_modes')->fetchColumn();
        $this->assertStringContainsString('00:00:00 EAT', MerchantNotificationService::eat($due));
        $this->paymentAdmission->time = $due - 1;
        $this->assertCount(0, $this->notifications->sweep($owner, $merchant->id)['deliveries']);
        $this->paymentAdmission->time = $due;
        $history = $this->notifications->sweep($owner, $merchant->id);
        $this->assertCount(1, $history['digests']);
        $this->assertCount(1, $history['deliveries']);
        $this->notifications->deliver($owner, $merchant->id, $history['deliveries'][0]['id']);
        $this->assertStringContainsString('Daily payment digest', $this->transport->calls[0]['subject']);
        $this->assertStringContainsString($reference, $this->transport->calls[0]['message']);
        $this->assertStringContainsString('Count: 2', $this->transport->calls[0]['message']);
        $this->assertStringContainsString('Confirmed gross TZS: 25000', $this->transport->calls[0]['message']);
        $this->assertCount(1, $this->notifications->sweep($owner, $merchant->id)['deliveries']);
        $this->notifications->preferences($owner, $merchant->id, 'weekly');
        $second = $this->createPayment($merchant, ['idempotency_key' => 'p10-weekly-payment'])->assertOk()->json('reference');
        $this->receiveEvent($this->event($second))->assertOk();
        $this->notifications->sweep($owner, $merchant->id);
        $weeklyDue = (int) $this->paymentPdo->query("SELECT due_at FROM p10_payment_modes WHERE mode='weekly'")->fetchColumn();
        $this->assertSame('1', (new \DateTimeImmutable('@'.intdiv($weeklyDue, 1000)))->setTimezone(new \DateTimeZone('Africa/Nairobi'))->format('N'));
        $this->paymentAdmission->time = $weeklyDue;
        $history = $this->notifications->sweep($owner, $merchant->id);
        $this->assertCount(2, $history['digests']);
        $weekly = array_values(array_filter($history['deliveries'], fn ($d) => $d['status'] === 'pending'))[0];
        $this->transport->status = 'accepted';
        $accepted = $this->notifications->deliver($owner, $merchant->id, $weekly['id']);
        $this->assertContains('provider_accepted', array_column($accepted['history'], 'status'));
        $this->assertSame('queued', array_values(array_filter($accepted['deliveries'], fn ($d) => $d['id'] === $weekly['id']))[0]['status']);
        $this->paymentAdmission->time += 61000;
        $this->transport->status = 'delivered';
        $this->notifications->deliver($owner, $merchant->id, $weekly['id']);
        $this->assertSame($this->transport->calls[1]['deliveryId'], $this->transport->calls[2]['deliveryId']);
        $this->assertStringContainsString('Weekly payment digest', $this->transport->calls[1]['subject']);
        $this->notifications->preferences($owner, $merchant->id, 'off');
        $third = $this->createPayment($merchant, ['idempotency_key' => 'p10-off-payment-001'])->assertOk()->json('reference');
        $this->receiveEvent($this->event($third))->assertOk();
        $this->assertCount(2, $this->notifications->sweep($owner, $merchant->id)['deliveries']);
        $this->notifications->preferences($owner, $merchant->id, 'individual');
        $this->assertCount(2, $this->notifications->sweep($owner, $merchant->id)['deliveries']);
        $this->get($base.'/notifications')->assertOk()->assertSee('Daily digest')->assertSee('TZS 0');
        $this->get($base.'/documents/payment/'.$confirmation)->assertOk()->assertHeader('cache-control', 'no-store, private');
        $this->get($base.'/documents/payment/'.Str::uuid())->assertNotFound();
        $paymentPdf = $this->reports->document($owner, $merchant->id, 'payment', $confirmation, $this->paymentAdmission->time);
        $this->assertSame($paymentPdf, $this->reports->document($owner, $merchant->id, 'payment', $confirmation, $this->paymentAdmission->time));
        file_put_contents(dirname(__DIR__, 3).'/.recovery/p10-payment.pdf', $paymentPdf['content']);
        foreach (["UPDATE p10_preferences SET mode='off'", 'DELETE FROM p10_documents', "UPDATE p10_batches SET payload='{}'"] as $sql) {
            try {
                $this->paymentPdo->exec($sql);
                $this->fail('Immutable history must reject edits');
            } catch (PDOException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
