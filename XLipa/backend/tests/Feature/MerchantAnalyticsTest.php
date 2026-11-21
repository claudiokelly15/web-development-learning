<?php

namespace Tests\Feature;

use App\Actions\Payments\AnalyticsEvidence;
use App\Actions\Payments\MerchantAnalytics;
use App\Actions\Payments\MerchantWallet;
use App\Actions\Payments\MockPaymentLedger;
use App\Models\Merchant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDOException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\Support\AnalyticsStoreFixture;
use Tests\Support\FakeLedgerAdmission;
use Tests\Support\LedgerStoreFixture;
use Tests\Support\MerchantWalletHttpHarness;
use Tests\Support\MockPaymentJourney;
use Tests\Support\PaymentLinkStoreFixture;
use Tests\TestCase;

class MerchantAnalyticsTest extends TestCase
{
    use MockPaymentJourney;

    private MerchantAnalytics $analytics;

    private AnalyticsEvidence $evidence;

    private FakeLedgerAdmission $ledgerAdmission;

    private function ready(): array
    {
        [$owner,$merchant] = $this->eligible();
        LedgerStoreFixture::install($this->paymentPdo);
        PaymentLinkStoreFixture::install($this->paymentPdo);
        AnalyticsStoreFixture::install($this->paymentPdo);
        $this->paymentAdmission->allow = true;
        $this->ledgerAdmission = new FakeLedgerAdmission;
        $this->ledgerAdmission->actor = $owner->id;
        $this->ledgerAdmission->merchant = $merchant->id;
        $this->ledgerAdmission->allow = true;
        $this->evidence = new AnalyticsEvidence(str_repeat('A', 32));
        $this->analytics = new MerchantAnalytics(new MockPaymentLedger($this->payments->engine, $this->ledgerAdmission), $this->evidence);

        return [$owner->fresh(), $merchant->fresh()];
    }

    private function payment(User $owner, Merchant $merchant, int $amount, array $source = [], string $outcome = 'ok', ?string $method = null): array
    {
        $row = $this->createPayment($merchant, ['amount_tzs' => $amount, 'idempotency_key' => (string) Str::uuid()])->assertOk()->json();
        $reference = $row['reference'];
        $this->analytics->capture($owner, $merchant->id, $reference, $source, $this->paymentAdmission->time);
        if ($outcome !== 'pending') {
            $this->receiveEvent($this->event($reference, $outcome))->assertOk();
        }
        $confirmation = $this->paymentPdo->query("SELECT id FROM payment_confirmations WHERE payment_reference='$reference'")->fetchColumn();
        $body = null;
        if ($method !== null) {
            $body = json_encode(['event_id' => (string) Str::uuid(), 'merchant_id' => $merchant->id, 'payment_reference' => $reference, 'confirmation_id' => $confirmation, 'amount_tzs' => $amount, 'currency' => 'TZS', 'method' => $method, 'occurred_at' => $this->paymentAdmission->time], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $this->analytics->attest($owner, $merchant->id, $body, $this->evidence->signature($body), $this->paymentAdmission->time);
        }

        return [$reference, $confirmation, $body];
    }

    private function denied(callable $work, int $status): void
    {
        try {
            $work();
            $this->fail('Expected denied operation');
        } catch (HttpExceptionInterface $error) {
            $this->assertSame($status, $error->getStatusCode());
        }
    }

    public function test_dimensions_reconcile_exactly_without_counting_pending_failed_or_customers(): void
    {
        [$owner,$merchant] = $this->ready();
        $this->payment($owner, $merchant, 100, ['utm_source' => 'facebook'], 'ok', 'synthetic_mpesa');
        $this->payment($owner, $merchant, 200, ['utm_source' => 'tiktok'], 'ok', 'synthetic_mixx');
        $this->payment($owner, $merchant, 300, ['direct' => true]);
        $this->payment($owner, $merchant, 400);
        $this->payment($owner, $merchant, 999, ['utm_source' => 'instagram'], 'pending');
        $this->payment($owner, $merchant, 888, ['utm_source' => 'whatsapp'], 'declined');
        $report = $this->analytics->report($owner, $merchant->id, [], $this->paymentAdmission->time);
        $this->assertSame(4, $report['count']);
        $this->assertSame(1000, $report['value_tzs']);
        $this->assertNull($report['unique_customers']);
        foreach (['sources', 'methods'] as $dimension) {
            $this->assertSame(1000, array_sum(array_column($report[$dimension], 'value_tzs')));
            $this->assertSame(10000, array_sum(array_column($report[$dimension], 'basis_points')));
            $this->assertSame(4, array_sum(array_column($report[$dimension], 'count')));
        }
        $this->assertSame(300, $report['sources']['direct']['value_tzs']);
        $this->assertSame(400, $report['sources']['unknown']['value_tzs']);
        $this->assertSame(600, $report['attributed_value_tzs']);
        $this->assertSame(700, $report['methods']['unknown']['value_tzs']);
        $this->assertSame(1000, array_sum(array_column($report['cross'], 'value_tzs')));
        $this->assertSame(1000, array_sum(array_column($report['trends'], 'value_tzs')));
    }

    public function test_source_classification_preserves_direct_unknown_and_unverified_claims(): void
    {
        [$owner,$merchant] = $this->ready();
        foreach (['facebook', 'tiktok', 'instagram', 'whatsapp', 'google', 'youtube'] as $source) {
            $this->payment($owner, $merchant, 1, ['utm_source' => strtoupper($source), 'campaign' => 'campaign_1']);
        }
        $this->payment($owner, $merchant, 1, ['referral_host' => 'facebook.com.evil.test']);
        $this->payment($owner, $merchant, 1, ['utm_source' => 'direct']);
        $this->payment($owner, $merchant, 1, ['referral_host' => 'wa.me']);
        $report = $this->analytics->report($owner, $merchant->id, [], $this->paymentAdmission->time);
        $this->assertSame(0, $report['sources']['direct']['count']);
        $this->assertSame(2, $report['sources']['referral']['count']);
        $this->assertSame(2, $report['sources']['whatsapp']['count']);
        $this->assertSame(10000, array_sum(array_column($report['sources'], 'basis_points')));
        $this->assertSame('unverified_utm', $this->paymentPdo->query('SELECT provenance FROM analytics_attribution LIMIT 1')->fetchColumn());
    }

    public function test_duplicate_and_conflicting_evidence_cannot_rewrite_attribution_or_methods(): void
    {
        [$owner,$merchant] = $this->ready();
        [$reference,,$body] = $this->payment($owner, $merchant, 123, ['utm_source' => 'facebook'], 'ok', 'synthetic_mpesa');
        $this->analytics->capture($owner, $merchant->id, $reference, ['utm_source' => 'facebook'], $this->paymentAdmission->time);
        $this->denied(fn () => $this->analytics->capture($owner, $merchant->id, $reference, ['utm_source' => 'tiktok'], $this->paymentAdmission->time), 409);
        $this->analytics->attest($owner, $merchant->id, $body, $this->evidence->signature($body), $this->paymentAdmission->time);
        $changed = str_replace('synthetic_mpesa', 'synthetic_bank', $body);
        $this->denied(fn () => $this->analytics->attest($owner, $merchant->id, $changed, $this->evidence->signature($changed), $this->paymentAdmission->time), 409);
        $this->assertSame(1, $this->analytics->report($owner, $merchant->id, [], $this->paymentAdmission->time)['count']);
        $this->expectException(PDOException::class);
        $this->paymentPdo->exec("UPDATE analytics_attribution SET source='tiktok'");
    }

    public function test_forged_wrong_amount_stale_and_unrecognized_method_attestations_are_rejected(): void
    {
        [$owner,$merchant] = $this->ready();
        [$reference,$confirmation] = $this->payment($owner, $merchant, 123);
        $facts = ['event_id' => (string) Str::uuid(), 'merchant_id' => $merchant->id, 'payment_reference' => $reference, 'confirmation_id' => $confirmation, 'amount_tzs' => 123, 'currency' => 'TZS', 'method' => 'synthetic_mpesa', 'occurred_at' => $this->paymentAdmission->time];
        $body = json_encode($facts, JSON_UNESCAPED_SLASHES);
        $this->denied(fn () => $this->analytics->attest($owner, $merchant->id, $body, str_repeat('0', 64), $this->paymentAdmission->time), 401);
        foreach ([['amount_tzs' => 124], ['method' => 'mpesa'], ['occurred_at' => 0]] as $changes) {
            $body = json_encode([...$facts, ...$changes], JSON_UNESCAPED_SLASHES);
            $this->denied(fn () => $this->analytics->attest($owner, $merchant->id, $body, $this->evidence->signature($body), $this->paymentAdmission->time), isset($changes['amount_tzs']) ? 409 : 422);
        }
        $this->assertSame(123, $this->analytics->report($owner, $merchant->id, [], $this->paymentAdmission->time)['methods']['unknown']['value_tzs']);
    }

    public function test_date_ranges_use_confirmation_time_in_eat_and_validate_custom_bounds(): void
    {
        [$owner,$merchant] = $this->ready();
        $this->payment($owner, $merchant, 123, ['direct' => true]);
        foreach (['today', 'week', 'month'] as $range) {
            $this->assertSame(123, $this->analytics->report($owner, $merchant->id, ['range' => $range], $this->paymentAdmission->time)['value_tzs']);
        }
        $this->assertSame(0, $this->analytics->report($owner, $merchant->id, ['range' => 'yesterday'], $this->paymentAdmission->time)['count']);
        $date = (new \DateTimeImmutable('@'.intdiv($this->paymentAdmission->time, 1000)))->setTimezone(new \DateTimeZone('Africa/Nairobi'))->format('Y-m-d');
        $this->assertSame(123, $this->analytics->report($owner, $merchant->id, ['range' => 'custom', 'from' => $date, 'to' => $date], $this->paymentAdmission->time)['value_tzs']);
        foreach ([['range' => 'all'], ['range' => 'custom', 'from' => '2026-02-30', 'to' => $date], ['range' => 'custom', 'from' => $date, 'to' => '2020-01-01'], ['range' => 'today', 'from' => $date], ['range' => []]] as $input) {
            $this->denied(fn () => $this->analytics->report($owner, $merchant->id, $input, $this->paymentAdmission->time), 422);
        }
    }

    public function test_foreign_revoked_and_ungranted_access_is_denied_and_empty_report_is_truthful(): void
    {
        [$owner,$merchant] = $this->ready();
        $empty = $this->analytics->report($owner, $merchant->id, [], $this->paymentAdmission->time);
        $this->assertSame(0, $empty['value_tzs']);
        $this->assertSame(0, array_sum(array_column($empty['sources'], 'basis_points')));
        $this->denied(fn () => $this->analytics->report($owner, 'foreign-merchant', [], $this->paymentAdmission->time), 503);
        $this->ledgerAdmission->allow = false;
        $this->denied(fn () => $this->analytics->report($owner, $merchant->id, [], $this->paymentAdmission->time), 503);
        $this->ledgerAdmission->allow = true;
        DB::table('merchant_memberships')->where('merchant_id', $merchant->id)->update(['status' => 'revoked', 'revoked_at' => now(), 'authority_version' => 2]);
        $this->denied(fn () => $this->analytics->report($owner, $merchant->id, [], $this->paymentAdmission->time), 403);
    }

    public function test_isolated_http_date_filter_and_drilldown_are_scoped_and_ordinary_routes_are_not_added(): void
    {
        [$owner,$merchant] = $this->ready();
        $this->payment($owner, $merchant, 12500, ['utm_source' => 'facebook'], 'ok', 'synthetic_mpesa');
        $ledger = new MockPaymentLedger($this->payments->engine, $this->ledgerAdmission);
        $harness = new MerchantWalletHttpHarness(new MerchantWallet($ledger), $this->analytics, fn (): int => $this->paymentAdmission->time);
        $harness->register();
        app('router')->getRoutes()->refreshNameLookups();
        $url = '/merchants/'.$merchant->id.'/__isolated-finances/analytics';
        $this->getJson($url)->assertOk()->assertJsonPath('analytics.value_tzs', 12500)->assertJsonPath('analytics.count', 1)->assertJsonPath('analytics.unique_customers', null);
        $this->getJson($url.'?range=yesterday')->assertOk()->assertJsonPath('analytics.value_tzs', 0);
        $this->getJson($url.'?range[]=today')->assertStatus(422);
        $this->get($url)->assertOk()->assertSee('Traffic Source Attribution')->assertSee('M-Pesa (synthetic)')->assertSee('Unavailable')->assertSee('Transaction details');
        $foreign = Merchant::factory()->create();
        $this->getJson('/merchants/'.$foreign->id.'/__isolated-finances/analytics')->assertStatus(404);
        auth()->logout();
        $this->getJson($url)->assertUnauthorized();
    }
}
