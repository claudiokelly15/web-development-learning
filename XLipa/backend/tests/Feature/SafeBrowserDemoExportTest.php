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
use App\Actions\Payments\MerchantWallet;
use App\Actions\Payments\MerchantWebhooks;
use App\Actions\Payments\MerchantWithdrawals;
use App\Actions\Payments\MockPaymentLedger;
use App\Actions\Payments\NotificationSchema;
use App\Actions\Payments\NotificationTransport;
use App\Actions\Payments\PaymentLinkService;
use App\Actions\Payments\PaymentStore;
use App\Models\Merchant;
use App\Models\PlatformRoleAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\AnalyticsStoreFixture;
use Tests\Support\DeveloperStoreFixture;
use Tests\Support\FakeFundsAdmission;
use Tests\Support\FakeLedgerAdmission;
use Tests\Support\FakePaymentLinkAdmission;
use Tests\Support\LedgerHttpHarness;
use Tests\Support\LedgerStoreFixture;
use Tests\Support\LifecycleStoreFixture;
use Tests\Support\MerchantDeveloperHttpHarness;
use Tests\Support\MerchantWalletHttpHarness;
use Tests\Support\MockPaymentJourney;
use Tests\Support\PaymentLinkHttpHarness;
use Tests\Support\PaymentLinkStoreFixture;
use Tests\TestCase;

class SafeBrowserDemoExportTest extends TestCase
{
    use MockPaymentJourney {
        journey as private baseOnboardingJourney;
    }

    private FakeLedgerAdmission $ledgerAdmission;

    private FakePaymentLinkAdmission $linkAdmission;

    private MerchantWalletHttpHarness $financial;

    private PaymentLinkHttpHarness $checkout;

    private LedgerHttpHarness $accounting;

    private array $customerPages = [];

    private MerchantAnalytics $analytics;

    private AnalyticsEvidence $methodEvidence;

    private LedgerLifecycle $funds;

    private FictionalFundsEvidence $fundsEvidence;

    private FictionalPayoutProvider $payoutProvider;

    private MerchantWithdrawals $withdrawals;

    private FakeFundsAdmission $fundsAdmission;

    private MerchantDeveloperTools $developerTools;

    private MerchantWebhooks $merchantWebhooks;

    private function journey(): array
    {
        [$owner, $merchant] = $this->baseOnboardingJourney();
        if (getenv('XLIPA_DEMO_EXPORT')) {
            $url = route('merchants.onboarding', ['merchant' => $merchant->id]);
            $response = $this->get($url)->assertOk()->assertSee('data-completion-preview', false);
            file_put_contents(getenv('XLIPA_DEMO_EXPORT').'/business-name-completion.html', $response->getContent());
            $this->customerPages['business-name-completion'] = ['url' => $url, 'file' => 'business-name-completion.html'];
        }

        return [$owner, $merchant];
    }

    private function ready(): array
    {
        [$owner, $merchant] = $this->eligible();
        LedgerStoreFixture::install($this->paymentPdo);
        PaymentLinkStoreFixture::install($this->paymentPdo);
        AnalyticsStoreFixture::install($this->paymentPdo);
        LifecycleStoreFixture::install($this->paymentPdo);
        DeveloperStoreFixture::install($this->paymentPdo);
        $this->paymentAdmission->allow = true;
        $this->ledgerAdmission = new FakeLedgerAdmission;
        $this->ledgerAdmission->actor = $owner->id;
        $this->ledgerAdmission->merchant = $merchant->id;
        $this->ledgerAdmission->allow = true;
        $this->linkAdmission = new FakePaymentLinkAdmission;
        $this->linkAdmission->actor = $owner->id;
        $this->linkAdmission->merchant = $merchant->id;
        $this->linkAdmission->allow = $this->linkAdmission->customers = true;
        $store = new PaymentStore($this->paymentPdo);
        $ledger = new MockPaymentLedger($this->payments->engine, $this->ledgerAdmission);
        $this->checkout = new PaymentLinkHttpHarness(new PaymentLinkService($this->payments->engine, $store, $ledger, $this->linkAdmission));
        $this->checkout->register();
        $this->accounting = new LedgerHttpHarness($ledger);
        $this->accounting->register();
        $this->methodEvidence = new AnalyticsEvidence(str_repeat('A', 32));
        $this->analytics = new MerchantAnalytics($ledger, $this->methodEvidence);
        $this->fundsEvidence = new FictionalFundsEvidence(str_repeat('S', 32), str_repeat('P', 32));
        $this->fundsAdmission = new FakeFundsAdmission;
        $this->fundsAdmission->actor = $owner->id;
        $this->fundsAdmission->merchant = $merchant->id;
        $this->fundsAdmission->allow = true;
        $this->funds = new LedgerLifecycle($ledger, $this->fundsAdmission, $this->fundsEvidence);
        $this->payoutProvider = new FictionalPayoutProvider($this->fundsEvidence);
        $this->withdrawals = new MerchantWithdrawals($this->funds, $this->fundsAdmission, $this->fundsEvidence, $this->payoutProvider);
        $this->financial = new MerchantWalletHttpHarness(new MerchantWallet($ledger), $this->analytics, fn (): int => $this->paymentAdmission->time, $this->funds, $store);
        $this->financial->register();
        $this->developerTools = new MerchantDeveloperTools($this->payments->engine, $store, $this->checkout->links, fn (): int => $this->paymentAdmission->time);
        $this->merchantWebhooks = new MerchantWebhooks($this->developerTools, $store, fn (string $host): array => ['93.184.216.34'], str_repeat('W', 32), new FictionalWebhookTransport);
        (new MerchantDeveloperHttpHarness($this->developerTools, $this->merchantWebhooks, $store))->register();
        foreach ([...NotificationSchema::statements(), ...NotificationSchema::completionStatements()] as $sql) {
            $this->paymentPdo->exec($sql);
        }
        $notificationTransport = new class implements NotificationTransport
        {
            public function send(string $channel, string $recipient, string $subject, string $message, string $deliveryId): array
            {
                return ['status' => 'delivered', 'receipt' => 'synthetic_'.$deliveryId];
            }
        };
        app()->instance(MerchantNotificationService::class, new MerchantNotificationService($ledger, $this->funds, fn (): int => max($this->paymentAdmission->time, $this->fundsAdmission->time), str_repeat('N', 32), $notificationTransport, fn (string $merchant, int $owner): string => '+255700000001'));
        app()->instance(MerchantFinancialReports::class, new MerchantFinancialReports($ledger, $this->funds, $this->analytics));
        app()->instance('p10.clock', fn (): int => max($this->paymentAdmission->time, $this->fundsAdmission->time));
        app('router')->getRoutes()->refreshNameLookups();

        return [$owner->fresh(), $merchant->fresh()];
    }

    private function base(Merchant $merchant): string
    {
        return '/merchants/'.$merchant->id.'/__isolated-finances';
    }

    private function read(string $url, bool $json = true): TestResponse
    {
        $response = $json ? $this->getJson($url) : $this->get($url);
        $failure = $this->financial->lastFailure;
        $this->assertSame(200, $response->status(), $url.' HTTP '.$response->status().' '.$response->getContent().' '.($failure ? $failure::class.': '.$failure->getMessage().' at '.$failure->getFile().':'.$failure->getLine() : ''));

        return $response;
    }

    private function postLink(Merchant $merchant, string $suffix, array $data = []): TestResponse
    {
        session()->save();
        $this->withCookie(config('session.cookie'), session()->getId())->withCredentials();

        return $this->postJson('/merchants/'.$merchant->id.'/__isolated-links'.$suffix, ['_token' => session()->token(), ...$data]);
    }

    private function checkoutPayment(User $owner, Merchant $merchant, int $amount = 12500, string $description = 'Fictional order P7', string $outcome = 'pending', string $source = 'facebook', string $method = 'synthetic_mpesa'): array
    {
        $this->signIn($owner->fresh());
        $link = $this->postLink($merchant, '', ['amount_tzs' => $amount, 'currency' => 'TZS', 'description' => $description, 'request_key' => (string) Str::uuid()])->assertOk()->json();
        auth()->logout();
        session()->invalidate();
        session()->regenerateToken();
        session()->save();
        $this->withCookie(config('session.cookie'), session()->getId())->withCredentials();
        $url = '/__isolated-checkout/'.$link['public_token'];
        $requestResponse = $this->get($url)->assertOk();
        file_put_contents(getenv('XLIPA_DEMO_EXPORT').'/checkout-request-'.$outcome.'.html', $requestResponse->getContent());
        $this->customerPages['checkout-request-'.$outcome] = ['url' => $url, 'file' => 'checkout-request-'.$outcome.'.html'];
        session()->save();
        $this->withCookie(config('session.cookie'), session()->getId())->withCredentials();
        $this->provider->scenario = $outcome === 'unresolved' ? 'unknown' : 'pending';
        $result = $this->postJson($url, ['_token' => session()->token(), 'request_nonce' => session('checkout.nonces.'.$link['public_token'])])->assertOk()->json();
        $this->signIn($owner->fresh());
        $this->analytics->capture($owner, $merchant->id, $result['payment_reference'], ['utm_source' => $source, 'campaign' => 'fictional_demo_campaign'], $this->paymentAdmission->time);
        auth()->logout();
        if (in_array($outcome, ['confirmed', 'failed'], true)) {
            $this->receiveEvent($this->event($result['payment_reference'], $outcome === 'confirmed' ? 'ok' : 'declined'))->assertOk();
        }
        $statusUrl = $url.'/attempts/'.$result['attempt_id'];
        $statusResponse = $this->get($statusUrl)->assertOk();
        file_put_contents(getenv('XLIPA_DEMO_EXPORT').'/checkout-'.$outcome.'.html', $statusResponse->getContent());
        $this->customerPages['checkout-'.$outcome] = ['url' => $statusUrl, 'file' => 'checkout-'.$outcome.'.html'];
        $this->assertGuest();
        $this->signIn($owner->fresh());
        if ($outcome === 'confirmed') {
            $confirmation = $this->paymentPdo->query('SELECT id FROM payment_confirmations WHERE payment_reference='.$this->paymentPdo->quote($result['payment_reference']))->fetchColumn();
            $body = json_encode(['event_id' => (string) Str::uuid(), 'merchant_id' => $merchant->id, 'payment_reference' => $result['payment_reference'], 'confirmation_id' => $confirmation, 'amount_tzs' => $amount, 'currency' => 'TZS', 'method' => $method, 'occurred_at' => $this->paymentAdmission->time], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $this->analytics->attest($owner, $merchant->id, $body, $this->methodEvidence->signature($body), $this->paymentAdmission->time);
        }

        return [$link, $result];
    }

    public function test_export_safe_browser_demo(): void
    {
        $directory = getenv('XLIPA_DEMO_EXPORT');
        if (! $directory) {
            $this->markTestSkipped('Explicit owned demo export directory required.');
        }
        $root = realpath(dirname(__DIR__, 3).'/scripts');
        $this->assertSame($root, realpath(dirname($directory)));
        $this->assertMatchesRegularExpression('/^demo-export-[a-f0-9]{32}$/', basename($directory));
        $this->assertDirectoryExists($directory);
        $invoke = fn (string $method, array $args = []) => $this->{$method}(...$args);
        [$owner, $merchant] = $invoke('ready');
        [$link, $result] = $invoke('checkoutPayment', [$owner, $merchant, 12500, 'Fictional studio order', 'confirmed']);
        $invoke('postLink', [$merchant, '/attempts/'.$result['attempt_id'].'/consume'])->assertOk();
        foreach ([['tiktok', 'synthetic_mixx', 6000], ['instagram', 'synthetic_airtel', 3500], ['whatsapp', 'synthetic_halo', 2000]] as [$source,$method,$amount]) {
            [, $extra] = $invoke('checkoutPayment', [$owner, $merchant, $amount, 'Fictional '.$source.' order', 'confirmed', $source, $method]);
            $invoke('postLink', [$merchant, '/attempts/'.$extra['attempt_id'].'/consume'])->assertOk();
        }
        $confirmation = $this->paymentPdo->query('SELECT id FROM payment_confirmations WHERE payment_reference='.$this->paymentPdo->quote($result['payment_reference']))->fetchColumn();
        $settlement = $this->fundsEvidence->body(['event_id' => (string) Str::uuid(), 'merchant_id' => $merchant->id, 'payment_reference' => $result['payment_reference'], 'confirmation_id' => $confirmation, 'amount_tzs' => 12500, 'currency' => 'TZS', 'provider_transaction' => 'fictional_demo_settlement', 'occurred_at' => $this->fundsAdmission->time]);
        $this->funds->settle($owner, $merchant->id, $settlement, $this->fundsEvidence->signature('settlement', $settlement));
        foreach (['confirmed' => 2500, 'failed' => 1000, 'unresolved' => 1500, 'pending' => 500] as $status => $amount) {
            $request = $this->withdrawals->request($owner, $merchant->id, $amount, 'fictional_destination_1', (string) Str::uuid());
            $this->payoutProvider->scenario = $status === 'unresolved' ? 'unresolved' : 'pending';
            $this->withdrawals->dispatch($owner, $merchant->id, $request['reference']);
            $body = $this->payoutProvider->eventBody($request['reference'], $status, $this->fundsAdmission->time);
            $this->withdrawals->receive($owner, $merchant->id, $body, $this->fundsEvidence->signature('payout', $body));
            if (in_array($status, ['confirmed', 'failed'], true)) {
                $this->withdrawals->reconcile($owner, $merchant->id, $request['reference']);
            }
        }
        $invoke('checkoutPayment', [$owner, $merchant, 7000, 'Fictional pending order', 'pending']);
        $invoke('checkoutPayment', [$owner, $merchant, 4500, 'Fictional declined order', 'failed']);
        $invoke('checkoutPayment', [$owner, $merchant, 3000, 'Fictional unresolved order', 'unresolved']);
        $base = '/merchants/'.$merchant->id;
        $history = $this->getJson($base.'/__isolated-finances/transactions')->assertOk()->json();
        $overview = $this->getJson($base.'/__isolated-finances/wallet')->assertOk()->json();
        $this->assertSame(7, $history['pagination']['total']);
        $this->assertCount(7, $history['transactions']);
        $this->assertSame(24000, $overview['position']['confirmed_fictional_payment_tzs']);
        $this->assertSame(0, $overview['position']['withdrawable_tzs']);
        foreach (['confirmed', 'pending', 'failed', 'unknown'] as $state) {
            $this->assertSame($state === 'confirmed' ? 4 : 1, $overview['payment_states'][$state]['count']);
        }
        $analytics = $this->analytics->report($owner, $merchant->id, [], $this->paymentAdmission->time);
        $funds = $this->funds->overview($owner, $merchant->id);
        $this->assertSame(24000, $analytics['value_tzs']);
        $this->assertSame(8000, $funds['position']['simulated_available_tzs']);
        $testCredential = $this->developerTools->issue($owner, $merchant->id, ['links:read', 'links:write', 'payments:read'], $this->paymentAdmission->time + 86400000);
        $this->developerTools->issue($owner, $merchant->id, ['links:read'], $this->paymentAdmission->time + 86400000, $testCredential['id']);
        $qrLink = $this->checkout->links->create($owner, $merchant->id, 12500, 'TZS', 'Fictional QR checkout', 'p9-demo-qr-link-0001', $this->paymentAdmission->time + 86400000);
        $this->developerTools->widget($owner, $merchant->id, $qrLink['id'], 'qr', 'dynamic');
        $this->developerTools->widget($owner, $merchant->id, $link['id'], 'qr', 'reusable');
        $this->developerTools->widget($owner, $merchant->id, $link['id'], 'button', 'reusable');
        $testEndpoint = $this->merchantWebhooks->register($owner, $merchant->id, 'https://merchant.example/hooks');
        $delivery = $this->merchantWebhooks->enqueue($owner, $merchant->id);
        $this->assertCount(4, $delivery);
        $this->assertSame('delivered', $this->merchantWebhooks->deliver($owner, $merchant->id, $delivery[0]['id'])['status']);
        $this->assertSame(24000, $this->analytics->report($owner, $merchant->id, [], $this->paymentAdmission->time)['value_tzs']);
        $p10 = app(MerchantNotificationService::class);
        $notificationHistory = $p10->sweep($owner, $merchant->id);
        $this->assertCount(9, $notificationHistory['events']);
        $this->assertCount(14, $notificationHistory['deliveries']);
        $p10->deliver($owner, $merchant->id, $notificationHistory['deliveries'][0]['id']);
        $downloads = [];
        foreach (['csv', 'pdf', 'xlsx'] as $format) {
            $url = $base.'/reports/export/'.$format;
            $download = $this->get($url)->assertOk();
            $file = 'p10-report.'.$format;
            file_put_contents($directory.'/'.$file, $download->getContent());
            $downloads[] = ['url' => $url, 'file' => $file];
        }
        foreach (['withdrawal.requested' => 'p10-withdrawal-advice.pdf', 'payout.confirmed' => 'p10-payout-receipt.pdf'] as $kind => $file) {
            $event = array_values(array_filter($notificationHistory['events'], fn (array $event): bool => $event['kind'] === $kind))[0];
            $url = $base.'/notifications/'.$event['id'].'/document';
            file_put_contents($directory.'/'.$file, $this->get($url)->assertOk()->getContent());
            $downloads[] = ['url' => $url, 'file' => $file];
        }
        file_put_contents($directory.'/downloads.json', json_encode($downloads, JSON_THROW_ON_ERROR));
        $this->customerPages['qr-associated-checkout'] = ['url' => '/__isolated-checkout/'.$qrLink['public_token'], 'file' => 'qr-associated-checkout.html'];
        auth()->logout();
        session()->invalidate();
        file_put_contents($directory.'/qr-associated-checkout.html', $this->get('/__isolated-checkout/'.$qrLink['public_token'])->assertOk()->getContent());
        $this->signIn($owner);
        View::share('financialSummaryPreview', true);
        View::composer('merchants.dashboard', function ($view) use ($history, $overview, $analytics, $funds): void {
            $view->with(['financialHistory' => $history, 'financialOverview' => $overview, 'financialClock' => $this->paymentAdmission->time, 'analytics' => $analytics, 'funds' => $funds]);
        });
        $pages = $this->customerPages;
        $save = function (string $name, string $url) use (&$pages, $directory): void {
            $response = $this->get($url)->assertOk();
            if ($name === 'merchant-dashboard') {
                $response->assertSee('Confirmed payments today')->assertSee('TZS 24,000')->assertSee('TZS 0')->assertSee('LAST 30 DAYS')
                    ->assertSee('TZS 11,500')->assertSee('TZS 8,000')->assertSee('Net of independently proven simulated settlement')
                    ->assertSee('Traffic Source Attribution')->assertSee('Payment Method Analytics')
                    ->assertSee('M-Pesa (synthetic)')->assertSee('Confirmed Payment Value (TZS)')->assertSee('Unique Paying Customers');
                $document = new \DOMDocument;
                @$document->loadHTML($response->getContent());
                $xpath = new \DOMXPath($document);
                $this->assertSame(3, $xpath->query('//*[@data-primary-kpi]')->length);
                $this->assertSame(1, $xpath->query('//*[@aria-label="Available balance"]')->length);
            }
            $pages[$name] = ['url' => $url, 'file' => $name.'.html'];
            file_put_contents($directory.'/'.$name.'.html', $response->getContent());
        };
        foreach (['merchant-dashboard' => $base.'/dashboard', 'business-profile' => $base.'/onboarding',
            'merchant-reports' => $base.'/reports', 'notification-history' => $base.'/notifications',
            'verification' => $base.'/verification', 'applications' => $base.'/applications',
            'payment-links' => $base.'/__isolated-links', 'wallet' => $base.'/__isolated-finances/wallet',
            'transactions' => $base.'/__isolated-finances/transactions',
            'merchant-analytics' => $base.'/__isolated-finances/analytics',
            'settlement-withdrawals' => $base.'/__isolated-finances/funds',
            'merchant-api' => $base.'/__isolated-developers/api',
            'api-credentials' => $base.'/__isolated-developers/credentials',
            'qr-payments' => $base.'/__isolated-developers/qr',
            'pay-buttons' => $base.'/__isolated-developers/buttons',
            'merchant-webhooks' => $base.'/__isolated-developers/webhooks',
            'integration-docs' => $base.'/__isolated-developers/docs',
            'transaction-detail' => $base.'/__isolated-finances/transactions/'.$history['transactions'][0]['record_id'],
            'merchant-settings' => '/account'] as $name => $url) {
            $save($name, $url);
        }
        foreach ($history['transactions'] as $transaction) {
            $save('transaction-'.$transaction['status'], $base.'/__isolated-finances/transactions/'.$transaction['record_id']);
        }
        auth()->logout();
        session()->invalidate();
        $save('customer-checkout', '/__isolated-checkout/'.$link['public_token']);
        $chief = User::findOrFail(PlatformRoleAssignment::where('role', 'chief_admin')->value('user_id'));
        $this->actingAs($chief)->withSession(['auth_version' => $chief->auth_version]);
        $this->fundsAdmission->chief = $chief->id;
        app()->instance(LedgerLifecycle::class, $this->funds);
        app()->instance('p10.chief-context', ['merchant' => $merchant->id, 'store' => new PaymentStore($this->paymentPdo), 'admission' => $this->fundsAdmission]);
        View::share('fundsOversightMerchant', $merchant->id);
        $save('chief-financial-oversight', '/admin/__isolated-funds/'.$merchant->id);
        $save('chief-api-governance', '/admin/__isolated-api-governance/'.$merchant->id);
        $save('chief-reports', '/admin/reports');
        $reviewScope = [];
        foreach (['Pending applications' => 'pending_review', 'Approved applications' => 'approved', 'Rejected applications' => 'rejected'] as $label => $status) {
            $reviewScope[$label] = $this->get('/admin/applications?status='.$status)->assertOk()->viewData('applications')->total();
        }
        $reviewApplications = $this->get('/admin/applications')->assertOk()->viewData('applications')->items();
        View::composer('admin.workspace', fn ($view) => $view->with(['reviewScope' => $reviewScope, 'reviewApplications' => $reviewApplications]));
        $save('chief-dashboard', '/admin/chief');
        $save('application-review', '/admin/applications');
        $save('decision-history', '/admin/applications/'.$merchant->applications()->firstOrFail()->id);
        $save('platform-access', '/admin/access');
        $staff = User::factory()->create(['name' => 'Fictional Staff Demo', 'email' => 'staff@demo.example']);
        PlatformRoleAssignment::factory()->create(['user_id' => $staff->id, 'role' => 'admin']);
        $this->actingAs($staff)->withSession(['auth_version' => $staff->auth_version]);
        $save('staff-dashboard', '/admin/staff');
        $save('staff-access', '/admin/access');
        $this->get('/admin/applications')->assertForbidden();
        $this->get('/admin/chief')->assertForbidden();
        $this->get('/admin/__isolated-funds/'.$merchant->id)->assertForbidden();
        $this->get('/admin/__isolated-api-governance/'.$merchant->id)->assertForbidden();
        auth()->logout();
        session()->invalidate();
        foreach (['public-home' => '/', 'about' => '/about.html', 'contact' => '/contact.html', 'developers' => '/developers.html', 'terms' => '/terms.html', 'privacy' => '/privacy.html', 'disclaimer' => '/disclaimer.html', 'prohibited-businesses' => '/prohibited-businesses.html', 'sign-in' => '/xlipasignin.html', 'registration' => '/xlipacreateaccount.html', 'forgot-password' => '/forgot-password', 'reset-password' => '/reset-password/fictional-token'] as $name => $url) {
            $save($name, $url);
        }
        $unverified = User::factory()->create(['name' => 'Fictional New Account', 'email' => 'new@demo.example', 'email_verified_at' => null]);
        $this->actingAs($unverified)->withSession(['auth_version' => $unverified->auth_version]);
        $save('email-verification', '/email/verify');
        file_put_contents($directory.'/pages.json', json_encode($pages, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
        foreach ($pages as $page) {
            $html = file_get_contents($directory.'/'.$page['file']);
            $this->assertStringNotContainsString($testCredential['token'], $html);
            $this->assertStringNotContainsString($testEndpoint['secret'], $html);
        }
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
    }
}
