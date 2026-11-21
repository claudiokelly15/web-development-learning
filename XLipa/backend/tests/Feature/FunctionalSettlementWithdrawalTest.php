<?php

namespace Tests\Feature;

use App\Actions\Payments\FictionalFundsEvidence;
use App\Actions\Payments\FictionalPayoutProvider;
use App\Actions\Payments\LedgerLifecycle;
use App\Actions\Payments\MerchantWithdrawals;
use App\Actions\Payments\MockPaymentLedger;
use App\Actions\Payments\PaymentEngine;
use App\Actions\Payments\PaymentStore;
use App\Models\PlatformRoleAssignment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDOException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\Support\FakeFundsAdmission;
use Tests\Support\FakeLedgerAdmission;
use Tests\Support\LedgerStoreFixture;
use Tests\Support\LifecycleStoreFixture;
use Tests\Support\MockPaymentJourney;
use Tests\TestCase;

class FunctionalSettlementWithdrawalTest extends TestCase
{
    use MockPaymentJourney;

    private LedgerLifecycle $funds;

    private MerchantWithdrawals $withdrawals;

    private FictionalFundsEvidence $evidence;

    private FictionalPayoutProvider $payout;

    private FakeFundsAdmission $fundsAdmission;

    private function ready(bool $settle = true): array
    {
        [$owner,$merchant] = $this->eligible();
        $this->paymentAdmission->allow = true;
        LedgerStoreFixture::install($this->paymentPdo);
        LifecycleStoreFixture::install($this->paymentPdo);
        $grant = new FakeLedgerAdmission;
        $grant->actor = $owner->id;
        $grant->merchant = $merchant->id;
        $grant->allow = true;
        $ledger = new MockPaymentLedger($this->payments->engine, $grant);
        $this->evidence = new FictionalFundsEvidence(str_repeat('S', 32), str_repeat('P', 32));
        $this->fundsAdmission = new FakeFundsAdmission;
        $this->fundsAdmission->actor = $owner->id;
        $this->fundsAdmission->merchant = $merchant->id;
        $this->fundsAdmission->allow = true;
        $this->funds = new LedgerLifecycle($ledger, $this->fundsAdmission, $this->evidence);
        $this->payout = new FictionalPayoutProvider($this->evidence);
        $this->withdrawals = new MerchantWithdrawals($this->funds, $this->fundsAdmission, $this->evidence, $this->payout);
        $reference = $this->createPayment($merchant)->assertOk()->json('reference');
        $this->receiveEvent($this->event($reference))->assertOk();
        $confirmation = $this->paymentPdo->query("SELECT id FROM payment_confirmations WHERE payment_reference='$reference'")->fetchColumn();
        $ledger->post($owner, $merchant->id, $confirmation, $reference, 12500, 'TZS');
        $body = $this->evidence->body(['event_id' => (string) Str::uuid(), 'merchant_id' => $merchant->id, 'payment_reference' => $reference, 'confirmation_id' => $confirmation, 'amount_tzs' => 12500, 'currency' => 'TZS', 'provider_transaction' => 'fictional_settlement_1', 'occurred_at' => $this->fundsAdmission->time]);
        if ($settle) {
            $this->funds->settle($owner, $merchant->id, $body, $this->evidence->signature('settlement', $body));
        }

        return [$owner->fresh(), $merchant->fresh(), $body];
    }

    private function denied(callable $work, int $status): void
    {
        try {
            $work();
            $this->fail('Expected denied operation');
        } catch (HttpExceptionInterface $error) {
            $this->assertSame($status, $error->getStatusCode());
        } catch (AuthorizationException) {
            $this->assertSame(403, $status);
        }
    }

    public function test_independent_settlement_authentication_exact_amount_idempotency_and_balanced_postings(): void
    {
        [$owner,$merchant,$body] = $this->ready(false);
        $this->assertSame(0, $this->funds->overview($owner, $merchant->id)['position']['simulated_available_tzs']);
        $this->denied(fn () => $this->funds->settle($owner, $merchant->id, $body, str_repeat('0', 64)), 401);
        $wrong = str_replace('12500', '12501', $body);
        $this->denied(fn () => $this->funds->settle($owner, $merchant->id, $wrong, $this->evidence->signature('settlement', $wrong)), 409);
        $first = $this->funds->settle($owner, $merchant->id, $body, $this->evidence->signature('settlement', $body));
        $this->assertSame($first, $this->funds->settle($owner, $merchant->id, $body, $this->evidence->signature('settlement', $body)));
        $changed = str_replace('fictional_settlement_1', 'fictional_settlement_2', $body);
        $this->denied(fn () => $this->funds->settle($owner, $merchant->id, $changed, $this->evidence->signature('settlement', $changed)), 409);
        $position = $this->funds->overview($owner, $merchant->id)['position'];
        $this->assertSame(12500, $position['simulated_available_tzs']);
        $this->assertSame(0, $position['pending_obligation_tzs']);
        $this->assertSame(0, $position['operational_available_tzs']);
        $this->assertSame([['direction' => 'credit', 'value' => 25000], ['direction' => 'debit', 'value' => 25000]], $this->paymentPdo->query('SELECT direction,sum(amount_tzs) AS value FROM ledger_lifecycle_postings GROUP BY direction ORDER BY direction')->fetchAll());
    }

    public function test_withdrawal_reservation_validation_idempotency_and_overspend_prevention(): void
    {
        [$owner,$merchant] = $this->ready();
        foreach ([0, -1, '100', 1.5, true] as $amount) {
            $this->denied(fn () => $this->withdrawals->request($owner, $merchant->id, $amount, 'fictional_destination_1', 'withdrawal_key_0001'), 422);
        }
        $this->denied(fn () => $this->withdrawals->request($owner, $merchant->id, 12501, 'fictional_destination_1', 'withdrawal_key_0001'), 409);
        $row = $this->withdrawals->request($owner, $merchant->id, 10000, 'fictional_destination_1', 'withdrawal_key_0001');
        $this->assertSame($row, $this->withdrawals->request($owner, $merchant->id, 10000, 'fictional_destination_1', 'withdrawal_key_0001'));
        $this->denied(fn () => $this->withdrawals->request($owner, $merchant->id, 3000, 'fictional_destination_1', 'withdrawal_key_0002'), 409);
        $position = $this->funds->overview($owner, $merchant->id)['position'];
        $this->assertSame(2500, $position['simulated_available_tzs']);
        $this->assertSame(10000, $position['reserved_tzs']);
        $this->assertSame('Fictional destination **** 0001', $row['destination_mask']);
    }

    public function test_callback_does_not_pay_until_independent_reconciliation_and_duplicate_effects_are_prevented(): void
    {
        [$owner,$merchant] = $this->ready();
        $row = $this->withdrawals->request($owner, $merchant->id, 10000, 'fictional_destination_1', 'withdrawal_key_0001');
        $reference = $row['reference'];
        $this->withdrawals->dispatch($owner, $merchant->id, $reference);
        $this->withdrawals->dispatch($owner, $merchant->id, $reference);
        $this->assertSame(1, $this->payout->creates);
        $body = $this->payout->eventBody($reference, 'confirmed', $this->fundsAdmission->time);
        $this->denied(fn () => $this->withdrawals->receive($owner, $merchant->id, $body, str_repeat('0', 64)), 401);
        $signature = $this->evidence->signature('payout', $body);
        $this->withdrawals->receive($owner, $merchant->id, $body, $signature);
        $this->withdrawals->receive($owner, $merchant->id, $body, $signature);
        $this->assertSame(10000, $this->funds->overview($owner, $merchant->id)['position']['reserved_tzs']);
        $receipt = $this->withdrawals->reconcile($owner, $merchant->id, $reference);
        $this->assertSame('confirmed_fictional_payout', $receipt['status']);
        $this->assertSame($receipt, $this->withdrawals->reconcile($owner, $merchant->id, $reference));
        $position = $this->funds->overview($owner, $merchant->id)['position'];
        $this->assertSame(0, $position['reserved_tzs']);
        $this->assertSame(2500, $position['simulated_available_tzs']);
        $this->assertSame(10000, $position['completed_fictional_payout_tzs']);
    }

    public function test_lost_acknowledgment_is_not_redispatched_and_failed_payout_releases_only_after_reconciliation(): void
    {
        [$owner,$merchant] = $this->ready();
        $row = $this->withdrawals->request($owner, $merchant->id, 10000, 'fictional_destination_1', 'withdrawal_key_0001');
        $this->payout->scenario = 'unresolved';
        $this->assertSame('unresolved', $this->withdrawals->dispatch($owner, $merchant->id, $row['reference'])['status']);
        $this->withdrawals->dispatch($owner, $merchant->id, $row['reference']);
        $this->assertSame(1, $this->payout->creates);
        $this->assertSame('unresolved', $this->withdrawals->reconcile($owner, $merchant->id, $row['reference'])['status']);
        $body = $this->payout->eventBody($row['reference'], 'failed', $this->fundsAdmission->time);
        $this->withdrawals->receive($owner, $merchant->id, $body, $this->evidence->signature('payout', $body));
        $this->assertSame('failed_reconciled', $this->withdrawals->reconcile($owner, $merchant->id, $row['reference'])['status']);
        $this->assertSame(12500, $this->funds->overview($owner, $merchant->id)['position']['simulated_available_tzs']);
    }

    public function test_conflicting_provider_evidence_holds_reservation_and_history_is_immutable(): void
    {
        [$owner,$merchant] = $this->ready();
        $row = $this->withdrawals->request($owner, $merchant->id, 10000, 'fictional_destination_1', 'withdrawal_key_0001');
        $this->withdrawals->dispatch($owner, $merchant->id, $row['reference']);
        foreach (['confirmed', 'failed'] as $status) {
            $body = $this->payout->eventBody($row['reference'], $status, $this->fundsAdmission->time);
            $this->withdrawals->receive($owner, $merchant->id, $body, $this->evidence->signature('payout', $body));
        }
        $this->assertSame('unresolved', $this->withdrawals->reconcile($owner, $merchant->id, $row['reference'])['status']);
        $this->assertSame(10000, $this->funds->overview($owner, $merchant->id)['position']['reserved_tzs']);
        $this->expectException(PDOException::class);
        $this->paymentPdo->exec('DELETE FROM funds_events');
    }

    public function test_cross_tenant_and_revoked_grants_cannot_move_funds(): void
    {
        [$owner,$merchant] = $this->ready();
        $this->denied(fn () => $this->withdrawals->request($owner, 'foreign', 1, 'fictional_destination_1', 'withdrawal_key_0001'), 503);
        $this->fundsAdmission->allow = false;
        $this->denied(fn () => $this->funds->overview($owner, $merchant->id), 503);
        $this->fundsAdmission->allow = true;
        DB::table('merchant_memberships')->where('merchant_id', $merchant->id)->update(['status' => 'revoked', 'revoked_at' => now(), 'authority_version' => 2]);
        $this->denied(fn () => $this->withdrawals->request($owner, $merchant->id, 1, 'fictional_destination_1', 'withdrawal_key_0001'), 403);
    }

    public function test_chief_read_requires_independent_grant_and_current_role_and_never_grants_writes(): void
    {
        [$owner,$merchant] = $this->ready();
        $store = new PaymentStore($this->paymentPdo);
        $chief = $this->chief();
        $this->signIn($chief);
        $this->denied(fn () => $this->funds->chiefOverview($chief, $merchant->id, $store), 503);
        $this->fundsAdmission->chief = $chief->id;
        $this->assertSame(12500, $this->funds->chiefOverview($chief, $merchant->id, $store)['position']['simulated_available_tzs']);
        $this->denied(fn () => $this->withdrawals->request($chief, $merchant->id, 1, 'fictional_destination_1', 'withdrawal_key_0001'), 503);
        $staff = User::factory()->create();
        PlatformRoleAssignment::factory()->create(['user_id' => $staff->id, 'role' => 'admin']);
        $this->fundsAdmission->chief = $staff->id;
        $this->signIn($staff);
        $this->denied(fn () => $this->funds->chiefOverview($staff, $merchant->id, $store), 403);
        $this->fundsAdmission->chief = $chief->id;
        $this->signIn($owner);
        $this->denied(fn () => $this->funds->chiefOverview($owner, $merchant->id, $store), 503);
    }

    public function test_two_owned_connections_serialize_reservations_and_cannot_overspend(): void
    {
        [$owner,$merchant] = $this->ready();
        $path = tempnam(sys_get_temp_dir(), 'xlipa-p8-');
        $this->assertNotFalse($path);
        $this->assertSame(realpath(sys_get_temp_dir()), realpath(dirname($path)));
        $this->assertMatchesRegularExpression('/^(xlipa-p8-|xli)[A-Za-z0-9.]+$/D', basename($path));
        unlink($path);
        $one = $two = null;
        try {
            $this->paymentPdo->exec('VACUUM INTO '.$this->paymentPdo->quote($path));
            $one = new \PDO('sqlite:'.$path);
            $two = new \PDO('sqlite:'.$path);
            $one->exec('PRAGMA busy_timeout=0');
            $two->exec('PRAGMA busy_timeout=0');
            $build = function (\PDO $pdo) use ($owner, $merchant): MerchantWithdrawals {
                $store = new PaymentStore($pdo);
                $grant = new FakeLedgerAdmission;
                $grant->actor = $owner->id;
                $grant->merchant = $merchant->id;
                $grant->allow = true;
                $ledger = new MockPaymentLedger(new PaymentEngine($store, $this->provider, $this->paymentAdmission), $grant);

                return new MerchantWithdrawals(new LedgerLifecycle($ledger, $this->fundsAdmission, $this->evidence), $this->fundsAdmission, $this->evidence, $this->payout);
            };
            $first = $build($one);
            $second = $build($two);
            $one->exec('BEGIN IMMEDIATE');
            try {
                $second->request($owner, $merchant->id, 10000, 'fictional_destination_1', 'withdrawal_key_0001');
                $this->fail('Expected lock contention');
            } catch (PDOException $error) {
                $this->assertStringContainsString('locked', $error->getMessage());
            }
            $one->exec('ROLLBACK');
            $request = $first->request($owner, $merchant->id, 10000, 'fictional_destination_1', 'withdrawal_key_0001');
            $this->assertSame($request, $second->request($owner, $merchant->id, 10000, 'fictional_destination_1', 'withdrawal_key_0001'));
            $this->denied(fn () => $second->request($owner, $merchant->id, 3000, 'fictional_destination_1', 'withdrawal_key_0002'), 409);
            $this->assertSame(1, (int) $two->query('SELECT count(*) FROM funds_withdrawals')->fetchColumn());
            $this->assertSame([], $two->query('PRAGMA foreign_key_check')->fetchAll());
        } finally {
            unset($first,$second,$error,$build);
            $one = $two = null;
            gc_collect_cycles();
            foreach (['', '-journal', '-wal', '-shm'] as $suffix) {
                if (is_file($path.$suffix)) {
                    unlink($path.$suffix);
                }
            }
        }
    }
}
