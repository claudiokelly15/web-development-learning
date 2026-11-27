<?php

namespace Tests\Feature;

use App\Actions\Payments\AnalyticsEvidence;
use App\Actions\Payments\FictionalFundsEvidence;
use App\Actions\Payments\FictionalPayoutProvider;
use App\Actions\Payments\FictionalWebhookTransport;
use App\Actions\Payments\LedgerLifecycle;
use App\Actions\Payments\MerchantAnalytics;
use App\Actions\Payments\MerchantDeveloperTools;
use App\Actions\Payments\MerchantFinancialReports;
use App\Actions\Payments\MerchantNotificationService;
use App\Actions\Payments\MerchantWebhooks;
use App\Actions\Payments\MerchantWithdrawals;
use App\Actions\Payments\MockPaymentLedger;
use App\Actions\Payments\PaymentEngine;
use App\Actions\Payments\PaymentLinkService;
use App\Actions\Payments\PaymentStore;
use App\Staging\FinancialSchema;
use Illuminate\Support\Str;
use PDO;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\Support\FakeFundsAdmission;
use Tests\Support\FakeLedgerAdmission;
use Tests\Support\FakePaymentLinkAdmission;
use Tests\Support\MockPaymentJourney;
use Tests\TestCase;

class StagingFinancialCompatibilityTest extends TestCase
{
    use MockPaymentJourney;

    private array $schemaContext;

    private function composed(): array
    {
        [$owner,$merchant] = $this->eligible();
        $this->paymentPdo = new PDO('sqlite::memory:');
        $this->paymentPdo->exec('PRAGMA foreign_keys=ON; PRAGMA recursive_triggers=ON');
        $this->paymentPdo->exec('CREATE TABLE staging_identity(runtime TEXT NOT NULL,generation TEXT NOT NULL,profile TEXT NOT NULL)');
        $this->paymentPdo->exec("INSERT INTO staging_identity VALUES('compatibility','compatibility','unprovisioned-r22')");
        $this->schemaContext = ['runtime' => 'compatibility', 'generation' => 'compatibility', 'store' => 'compatibility', 'release' => 'compatibility'];
        FinancialSchema::create($this->paymentPdo, $this->schemaContext);
        $store = new PaymentStore($this->paymentPdo);
        $this->payments->engine = new PaymentEngine($store, $this->provider, $this->paymentAdmission);
        $this->paymentAdmission->allow = true;
        $grant = new FakeLedgerAdmission;
        $grant->actor = $owner->id;
        $grant->merchant = $merchant->id;
        $grant->allow = true;
        $ledger = new MockPaymentLedger($this->payments->engine, $grant);

        return [$owner, $merchant, $store, $ledger];
    }

    private function rejected(callable $operation): void
    {
        try {
            $operation();
            $this->fail('Persistence invariant was not enforced');
        } catch (\PDOException) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_existing_services_operate_on_the_composed_profile_without_business_bindings(): void
    {
        [$owner,$merchant,$store,$ledger] = $this->composed();
        $payment = $this->createPayment($merchant)->assertOk()->json();
        $reference = $payment['reference'];
        $this->assertSame($reference, $this->createPayment($merchant)->assertOk()->json('reference'));
        $body = $this->event($reference);
        $this->receiveEvent($body)->assertOk();
        $this->receiveEvent($body)->assertOk();
        $confirmation = $this->paymentPdo->query('SELECT id FROM payment_confirmations')->fetchColumn();
        $journal = $ledger->post($owner, $merchant->id, $confirmation, $reference, 12500, 'TZS');
        $this->assertSame($journal, $ledger->post($owner, $merchant->id, $confirmation, $reference, 12500, 'TZS'));
        $this->assertSame(1, (int) $this->paymentPdo->query('SELECT count(*) FROM payment_events')->fetchColumn());
        $this->assertSame(2, (int) $this->paymentPdo->query('SELECT count(*) FROM ledger_postings')->fetchColumn());
        $this->assertSame([['direction' => 'credit', 'amount' => 12500], ['direction' => 'debit', 'amount' => 12500]], $this->paymentPdo->query('SELECT direction,sum(amount_tzs) AS amount FROM ledger_postings GROUP BY direction ORDER BY direction')->fetchAll(PDO::FETCH_ASSOC));
        $this->rejected(fn () => $this->paymentPdo->exec('UPDATE ledger_postings SET amount_tzs=1'));
        $this->rejected(fn () => $this->paymentPdo->exec("INSERT INTO ledger_seals VALUES('missing-journal')"));
        $this->rejected(fn () => $this->paymentPdo->exec("INSERT INTO merchant_webhook_receipts VALUES('duplicate','hash',1),('duplicate','hash',1)"));
        $linkGrant = new FakePaymentLinkAdmission;
        $linkGrant->actor = $owner->id;
        $linkGrant->merchant = $merchant->id;
        $linkGrant->allow = $linkGrant->customers = true;
        $links = new PaymentLinkService($this->payments->engine, $store, $ledger, $linkGrant);
        $link = $links->create($owner, $merchant->id, 500000000001, 'TZS', 'Fictional exact TZS', 'r22-link-request-0001');
        $this->assertSame(500000000001, $link['amount_tzs']);
        $tools = new MerchantDeveloperTools($this->payments->engine, $store, $links, fn (): int => $this->paymentAdmission->time);
        $issued = $tools->issue($owner, $merchant->id, ['links:read'], $tools->now() + 10000);
        $this->assertSame(hash('sha256', $issued['token']), $this->paymentPdo->query('SELECT token_hash FROM api_credentials')->fetchColumn());
        $widget = $tools->widget($owner, $merchant->id, $link['id'], 'qr', 'reusable');
        $this->assertSame($link['id'], $widget['link_id']);
        $webhooks = new MerchantWebhooks($tools, $store, fn (): array => ['93.184.216.34'], str_repeat('W', 32), new FictionalWebhookTransport);
        $endpoint = $webhooks->register($owner, $merchant->id, 'https://fictional.example.test/hooks');
        $webhooks->enqueue($owner, $merchant->id);
        $this->assertSame(1, (int) $this->paymentPdo->query('SELECT count(*) FROM merchant_webhook_deliveries')->fetchColumn());
        $this->rejected(fn () => $this->paymentPdo->exec("INSERT INTO merchant_webhook_deliveries SELECT 'foreign-delivery','foreign-merchant',event_id,endpoint_id,status,attempts,next_at,http_status,claim FROM merchant_webhook_deliveries LIMIT 1"));
        $this->assertNotEmpty($endpoint['id']);
        $admission = new FakeFundsAdmission;
        $admission->actor = $owner->id;
        $admission->merchant = $merchant->id;
        $admission->allow = true;
        $evidence = new FictionalFundsEvidence(str_repeat('S', 32), str_repeat('P', 32));
        $funds = new LedgerLifecycle($ledger, $admission, $evidence);
        $settlement = $evidence->body(['event_id' => (string) Str::uuid(), 'merchant_id' => $merchant->id, 'payment_reference' => $reference, 'confirmation_id' => $confirmation, 'amount_tzs' => 12500, 'currency' => 'TZS', 'provider_transaction' => 'fictional-r22-settlement', 'occurred_at' => $admission->time]);
        $funds->settle($owner, $merchant->id, $settlement, $evidence->signature('settlement', $settlement));
        $payout = new FictionalPayoutProvider($evidence);
        $withdrawals = new MerchantWithdrawals($funds, $admission, $evidence, $payout);
        $request = $withdrawals->request($owner, $merchant->id, 2500, 'fictional_destination_1', 'r22-withdrawal-key-001');
        $this->assertSame(2500, $funds->overview($owner, $merchant->id)['position']['reserved_tzs']);
        try {
            $withdrawals->request($owner, $merchant->id, 20000, 'fictional_destination_1', 'r22-overspend-key-001');
            $this->fail('Insufficient reserve accepted');
        } catch (HttpExceptionInterface $error) {
            $this->assertSame(409, $error->getStatusCode());
        }
        $this->assertSame($request, $withdrawals->request($owner, $merchant->id, 2500, 'fictional_destination_1', 'r22-withdrawal-key-001'));
        $notifications = new MerchantNotificationService($ledger, $funds, fn (): int => max($admission->time, $this->paymentAdmission->time), str_repeat('N', 32), verifiedSmsRecipient: fn (): string => '+255600000000');
        $history = $notifications->sweep($owner, $merchant->id);
        $this->assertCount(2, $history['events']);
        $this->assertSame(0, (int) $this->paymentPdo->query('SELECT count(*) FROM p10_documents')->fetchColumn());
        $advice = array_values(array_filter($history['events'], fn ($event) => $event['kind'] === 'withdrawal.requested'))[0];
        $this->assertStringContainsString('payout NOT confirmed', $notifications->document($owner, $merchant->id, $advice['id'])['content']);
        $withdrawals->dispatch($owner, $merchant->id, $request['reference']);
        $callback = $payout->eventBody($request['reference'], 'confirmed', $admission->time);
        $withdrawals->receive($owner, $merchant->id, $callback, $evidence->signature('payout', $callback));
        $this->assertSame(0, (int) $this->paymentPdo->query("SELECT count(*) FROM p10_documents WHERE kind='payout'")->fetchColumn());
        $withdrawals->reconcile($owner, $merchant->id, $request['reference']);
        $notifications->sweep($owner, $merchant->id);
        $this->assertSame(1, (int) $this->paymentPdo->query("SELECT count(*) FROM p10_documents WHERE kind='payout'")->fetchColumn());
        $reports = new MerchantFinancialReports($ledger, $funds, new MerchantAnalytics($ledger, new AnalyticsEvidence(str_repeat('A', 32))));
        foreach (['csv', 'pdf', 'xlsx'] as $format) {
            $this->assertNotEmpty($reports->export($owner, $merchant->id, [], $this->paymentAdmission->time, $format)['content']);
        }
        $this->assertSame('ready', FinancialSchema::assertReady($this->paymentPdo, $this->schemaContext)['status']);
        $this->assertFalse(app()->bound(PaymentStore::class));
        $this->assertSame(0,$funds->overview($owner,$merchant->id)['position']['operational_available_tzs']);
    }
}
