<?php

namespace Tests\Feature;

use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Middleware\TrustHosts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionProperty;
use Tests\TestCase;

class TrustedHostsTest extends TestCase
{
    protected function setUp(): void
    {
        Request::setTrustedHosts([]);
        TrustHosts::flushState();
        parent::setUp();

        if (! app()->environment('testing') || config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:' || config('database.connections.sqlite.url')) {
            throw new LogicException('Trusted-host tests require isolated in-memory SQLite configuration.');
        }
        foreach (['session.driver', 'cache.default', 'mail.default'] as $key) {
            if (config($key) !== 'array') {
                throw new LogicException('Trusted-host tests require array session, cache and mail drivers.');
            }
        }
    }

    protected function tearDown(): void
    {
        try {
            $this->app?->instance('env', 'testing');
            parent::tearDown();
        } finally {
            Request::setTrustedHosts([]);
            TrustHosts::flushState();
        }
    }

    private function useOrigin(string $origin, string $environment = 'production'): void
    {
        $serverUrl = $_SERVER['APP_URL'] ?? null;
        $environmentUrl = $_ENV['APP_URL'] ?? null;
        try {
            $_SERVER['APP_URL'] = $_ENV['APP_URL'] = $origin;
            config([
                'app.url' => (require config_path('app.php'))['url'],
                'filesystems.disks.public.url' => (require config_path('filesystems.php'))['disks']['public']['url'],
            ]);
        } finally {
            if ($serverUrl === null) {
                unset($_SERVER['APP_URL']);
            } else {
                $_SERVER['APP_URL'] = $serverUrl;
            }
            if ($environmentUrl === null) {
                unset($_ENV['APP_URL']);
            } else {
                $_ENV['APP_URL'] = $environmentUrl;
            }
        }
        $this->app->getProvider(AppServiceProvider::class)->boot();
        $this->app->instance('env', $environment);
    }

    public function test_bootstrap_registers_the_framework_trusted_host_middleware(): void
    {
        $kernel = $this->app->make(Kernel::class);
        $middleware = (new ReflectionProperty($kernel, 'middleware'))->getValue($kernel);

        $this->assertContains(TrustHosts::class, $middleware);
    }

    /** @return array<string, array{string, string}> */
    public static function enforcedEnvironments(): array
    {
        return ['production' => ['production', 'https://configured-host.example.test'], 'staging' => ['staging', 'https://configured-host.example.test']];
    }

    #[DataProvider('enforcedEnvironments')]
    public function test_configured_host_is_accepted(string $environment, string $origin): void
    {
        $this->useOrigin($origin, $environment);

        $this->get($origin.'/up')->assertOk();
        $this->get($origin.'/')->assertOk();
        $this->get($origin.'/xlipasignin.html')->assertOk();
    }

    /** @return array<string, array{string}> */
    public static function untrustedHosts(): array
    {
        return [
            'unrelated' => ['untrusted.example.test'],
            'subdomain' => ['sub.configured-host.example.test'],
            'suffix' => ['configured-host.example.test.evil.test'],
            'regex lookalike' => ['configured-hostXexampleXtest'],
            'malformed' => ['bad host'],
        ];
    }

    #[DataProvider('untrustedHosts')]
    public function test_untrusted_hosts_are_rejected_before_public_pages_render(string $host): void
    {
        $this->useOrigin('https://configured-host.example.test');

        foreach (['/up', '/', '/xlipasignin.html'] as $path) {
            $request = Request::create('https://configured-host.example.test'.$path);
            $request->headers->set('Host', $host);
            $response = $this->app->make(Kernel::class)->handle($request);
            $this->assertSame(400, $response->getStatusCode());
        }
    }

    public function test_host_matching_is_case_insensitive_and_does_not_restrict_ports(): void
    {
        $this->useOrigin('https://configured-host.example.test');

        $this->get('https://CONFIGURED-HOST.EXAMPLE.TEST/up')->assertOk();
        $this->get('https://configured-host.example.test:8443/up')->assertOk();
    }

    public function test_alternate_configured_origin_controls_hosts_routes_assets_and_mail_links(): void
    {
        $origin = 'https://alternate-fixture.example.test';
        $this->useOrigin($origin);

        $this->get($origin.'/up')->assertOk();
        $this->get('http://xlipa.test/up')->assertStatus(400);
        $this->assertSame($origin.'/xlipasignin.html', route('login'));
        $this->assertSame($origin.'/assets/xlipa-favicon.svg', asset('assets/xlipa-favicon.svg'));
        $this->assertSame($origin.'/storage/synthetic.txt', Storage::disk('public')->url('synthetic.txt'));

        $user = new User(['name' => 'Synthetic Fixture', 'email' => 'fixture@example.test']);
        $message = (new ResetPassword('synthetic-unissued-token'))->toMail($user);
        $this->assertStringStartsWith($origin.'/reset-password/', $message->actionUrl);
        $this->assertFalse($user->exists);
    }

    /** @return array<string, array{string}> */
    public static function invalidOrigins(): array
    {
        return [
            'empty' => [''],
            'relative' => ['/relative-path'],
            'missing hostname' => ['https://'],
            'malformed port' => ['https://configured-host.example.test:invalid'],
            'invalid hostname' => ['https://bad host'],
            'wildcard' => ['https://*'],
        ];
    }

    #[DataProvider('invalidOrigins')]
    public function test_invalid_hostname_configuration_fails_closed(string $origin): void
    {
        config(['app.url' => $origin]);
        $this->app->instance('env', 'production');

        $this->get('https://configured-host.example.test/up')->assertStatus(400);
    }

    /** @return array<string, array{string}> */
    public static function bypassEnvironments(): array
    {
        return ['local' => ['local'], 'testing' => ['testing']];
    }

    #[DataProvider('bypassEnvironments')]
    public function test_reviewed_local_and_testing_bypass_is_preserved(string $environment): void
    {
        $this->useOrigin('http://xlipa.test', $environment);

        $this->get('http://xlipa.test/')->assertOk();
        $this->get('http://untrusted.example.test/up')->assertOk();
        $this->assertSame('http://xlipa.test/xlipasignin.html', route('login'));
    }

    public function test_signed_urls_remain_valid_and_reject_tampering_and_expiry(): void
    {
        $origin = 'https://alternate-fixture.example.test';
        $this->useOrigin($origin);
        $parameters = ['id' => 999, 'hash' => str_repeat('a', 40)];
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(5), $parameters);

        $this->assertTrue(URL::hasValidSignature(Request::create($url)));
        $this->assertFalse(URL::hasValidSignature(Request::create($url.'&tampered=1')));
        $this->assertFalse(URL::hasValidSignature(Request::create(str_replace('/999/', '/998/', $url))));
        $this->assertFalse(URL::hasValidSignature(Request::create(str_replace($origin, 'https://other-fixture.example.test', $url))));
        $expired = URL::temporarySignedRoute('verification.verify', now()->subMinute(), $parameters);
        $this->assertFalse(URL::hasValidSignature(Request::create($expired)));
        $this->get($url)->assertRedirect($origin.'/xlipasignin.html');
    }

    public function test_guest_redirects_and_session_state_remain_intact_on_the_accepted_origin(): void
    {
        $origin = 'https://alternate-fixture.example.test';
        $this->useOrigin($origin);

        $this->withSession(['trusted_host_probe' => 'synthetic'])->get($origin.'/account')
            ->assertRedirect($origin.'/xlipasignin.html')->assertSessionHas('trusted_host_probe', 'synthetic');
        $sessionId = session()->getId();
        $token = session()->token();
        $this->withCookie(config('session.cookie'), $sessionId)->get($origin.'/xlipasignin.html')
            ->assertOk()->assertSessionHas('trusted_host_probe', 'synthetic');
        $this->assertSame($sessionId, session()->getId());
        $this->assertSame($token, session()->token());
        $this->assertNull(config('session.domain'));
        $this->assertTrue(config('session.http_only'));
        $this->assertSame('lax', config('session.same_site'));
    }
}
