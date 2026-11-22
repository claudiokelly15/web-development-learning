<?php

namespace Tests\Feature;

use App\Actions\Payments\CheckoutQr;
use App\Actions\Payments\FictionalWebhookTransport;
use App\Actions\Payments\MerchantDeveloperTools;
use App\Actions\Payments\MerchantWebhooks;
use App\Actions\Payments\MockPaymentLedger;
use App\Actions\Payments\PaymentLinkService;
use App\Actions\Payments\PaymentStore;
use App\Models\PlatformRoleAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\Support\DeveloperStoreFixture;
use Tests\Support\FakeLedgerAdmission;
use Tests\Support\FakePaymentLinkAdmission;
use Tests\Support\LedgerStoreFixture;
use Tests\Support\MerchantDeveloperHttpHarness;
use Tests\Support\MockPaymentJourney;
use Tests\Support\PaymentLinkHttpHarness;
use Tests\Support\PaymentLinkStoreFixture;
use Tests\TestCase;

class FunctionalMerchantDeveloperTest extends TestCase
{
    use MockPaymentJourney;

    private MerchantDeveloperTools $tools;

    private MerchantWebhooks $webhooks;

    private FictionalWebhookTransport $transport;

    private PaymentLinkService $links;

    private array $addresses = ['93.184.216.34'];

    private function ready(): array
    {
        [$owner, $merchant] = $this->eligible();
        LedgerStoreFixture::install($this->paymentPdo);
        PaymentLinkStoreFixture::install($this->paymentPdo);
        DeveloperStoreFixture::install($this->paymentPdo);
        $this->paymentAdmission->allow = true;
        $ledgerAdmission = new FakeLedgerAdmission;
        $ledgerAdmission->actor = $owner->id;
        $ledgerAdmission->merchant = $merchant->id;
        $ledgerAdmission->allow = true;
        $admission = new FakePaymentLinkAdmission;
        $admission->actor = $owner->id;
        $admission->merchant = $merchant->id;
        $admission->allow = $admission->customers = true;
        $store = new PaymentStore($this->paymentPdo);
        $this->links = new PaymentLinkService($this->payments->engine, $store, new MockPaymentLedger($this->payments->engine, $ledgerAdmission), $admission);
        (new PaymentLinkHttpHarness($this->links))->register();
        $this->tools = new MerchantDeveloperTools($this->payments->engine, $store, $this->links, fn (): int => $this->paymentAdmission->time);
        $this->transport = new FictionalWebhookTransport;
        $this->webhooks = new MerchantWebhooks($this->tools, $store, fn (string $host): array => $this->addresses, str_repeat('W', 32), $this->transport);
        (new MerchantDeveloperHttpHarness($this->tools, $this->webhooks, $store))->register();
        app('router')->getRoutes()->refreshNameLookups();

        return [$owner->fresh(), $merchant->fresh()];
    }

    private function denied(int $status, \Closure $work): void
    {
        try {
            $work();
            $this->fail('Expected denial '.$status);
        } catch (HttpExceptionInterface $error) {
            $this->assertSame($status, $error->getStatusCode());
        }
    }

    private function link(User $owner, string $merchant, string $key = 'p9-example-order-001', ?int $expires = null): array
    {
        return $this->tools->createLink($owner, $merchant, ['amount_tzs' => 12500, 'currency' => 'TZS', 'description' => 'P9 fictional order', 'expires_at' => $expires], $key);
    }

    public function test_bearer_api_scopes_tenant_isolation_idempotency_and_session_restoration(): void
    {
        [$owner, $merchant] = $this->ready();
        $credential = $this->tools->issue($owner, $merchant->id, ['links:read', 'links:write'], $this->tools->now() + 60000);
        $url = '/__isolated-api/v1/merchants/'.$merchant->id;
        $this->getJson($url.'/links')->assertUnauthorized();
        $this->withHeader('Authorization', 'Bearer wrong')->getJson($url.'/links')->assertUnauthorized();
        $this->withHeader('Authorization', 'Bearer '.$credential['token']);
        $this->getJson($url.'/links?token=x')->assertUnprocessable();
        auth()->logout();
        session()->invalidate();
        session()->start();
        $before = session()->all();
        $this->getJson($url.'/links')->assertOk()->assertJsonPath('api_version', 'v1');
        $this->assertNull(auth()->id());
        $this->assertSame($before, session()->all());
        $payload = ['amount_tzs' => 12500, 'currency' => 'TZS', 'description' => 'P9 API order'];
        $this->withHeader('Idempotency-Key', 'p9-api-order-0001');
        $first = $this->postJson($url.'/links', $payload)->assertOk()->json('data');
        $this->assertSame($first, $this->postJson($url.'/links', $payload)->assertOk()->json('data'));
        $this->postJson($url.'/links', [...$payload, 'amount_tzs' => 1])->assertConflict();
        $this->postJson($url.'/links', [...$payload, 'merchant_id' => $merchant->id])->assertUnprocessable();
        $this->getJson(str_replace($merchant->id, (string) Str::ulid(), $url).'/links')->assertUnauthorized();
        $this->getJson($url.'/payments/'.(string) Str::uuid())->assertForbidden();
        $this->assertSame(0, $this->provider->creates);
        $this->assertSame(0, (int) $this->paymentPdo->query('SELECT count(*) FROM ledger_journals')->fetchColumn());
    }

    public function test_credentials_are_hashed_once_scoped_rotated_revoked_expired_and_current_authority_bound(): void
    {
        [$owner, $merchant] = $this->ready();
        $expiry = $this->tools->now() + 10000;
        $first = $this->tools->issue($owner, $merchant->id, ['links:read'], $expiry);
        $stored = $this->paymentPdo->query('SELECT * FROM api_credentials')->fetch(\PDO::FETCH_ASSOC);
        $this->assertSame(hash('sha256', $first['token']), $stored['token_hash']);
        $this->assertStringNotContainsString($first['token'], json_encode($stored));
        $this->assertStringNotContainsString($stored['token_hash'], json_encode($this->tools->overview($owner, $merchant->id)));
        $this->denied(422, fn () => $this->tools->issue($owner, $merchant->id, ['*'], $expiry));
        $this->denied(422, fn () => $this->tools->issue($owner, $merchant->id, [], $expiry));
        $second = $this->tools->issue($owner, $merchant->id, ['links:read'], $expiry, $first['id']);
        $this->denied(401, fn () => $this->tools->api($first['token'], $merchant->id, 'links:read', fn (): array => []));
        $this->assertSame(['ok'], $this->tools->api($second['token'], $merchant->id, 'links:read', fn (): array => ['ok']));
        $this->tools->revoke($owner, $merchant->id, $second['id']);
        $this->tools->revoke($owner, $merchant->id, $second['id']);
        $this->denied(401, fn () => $this->tools->api($second['token'], $merchant->id, 'links:read', fn (): array => []));
        $third = $this->tools->issue($owner, $merchant->id, ['links:read'], $expiry);
        $this->paymentAdmission->time = $expiry;
        $this->denied(401, fn () => $this->tools->api($third['token'], $merchant->id, 'links:read', fn (): array => []));
        $this->paymentAdmission->time -= 1000;
        DB::table('merchant_memberships')->where('merchant_id', $merchant->id)->update(['status' => 'revoked', 'revoked_at' => now(), 'authority_version' => 2]);
        $this->denied(403, fn () => $this->tools->api($third['token'], $merchant->id, 'links:read', fn (): array => []));
    }

    public function test_dynamic_and_reusable_qr_button_associations_use_existing_checkout(): void
    {
        [$owner, $merchant] = $this->ready();
        $link = $this->link($owner, $merchant->id, expires: $this->tools->now() + 60000);
        $widget = $this->tools->widget($owner, $merchant->id, $link['id'], 'qr', 'dynamic');
        $this->assertSame($widget['id'], $this->tools->widget($owner, $merchant->id, $link['id'], 'qr', 'dynamic')['id']);
        $this->assertSame($link['id'], $this->tools->resolveWidget($widget['id'])['id']);
        $this->get('/__isolated-widgets/'.$widget['id'])->assertRedirect($this->tools->checkoutUrl($link['public_token']));
        $svg = $this->get('/__isolated-widgets/'.$widget['id'].'/qr.svg')->assertOk()->assertHeader('Content-Type', 'image/svg+xml')->getContent();
        $this->assertStringContainsString('viewBox="0 0 49 49"', $svg);
        $this->assertStringNotContainsString('<script', $svg);
        $this->denied(409, fn () => $this->tools->widget($owner, $merchant->id, $link['id'], 'button', 'reusable'));
        $this->denied(404, fn () => $this->tools->widget($owner, $merchant->id, (string) Str::uuid(), 'qr', 'dynamic'));
        auth()->logout();
        session()->invalidate();
        session()->start();
        $nonce = bin2hex(random_bytes(32));
        session(['checkout.nonces.'.$link['public_token'] => $nonce]);
        $first = $this->links->initiate($link['public_token'], $nonce);
        $this->assertSame($first, $this->links->initiate($link['public_token'], $nonce));
        $this->assertSame(1, $this->provider->creates);
        session()->invalidate();
        session()->start();
        session(['checkout.nonces.'.$link['public_token'] => $nonce]);
        $this->denied(410, fn () => $this->links->initiate($link['public_token'], $nonce));
        $this->signIn($owner);
        $reusable = $this->link($owner, $merchant->id, 'p9-reusable-order-0001');
        $button = $this->tools->widget($owner, $merchant->id, $reusable['id'], 'button', 'reusable');
        $html = $this->tools->button($button['id']);
        $this->assertStringContainsString('rel="noopener noreferrer"', $html);
        $this->assertStringNotContainsString('script', $html);
        $this->assertStringContainsString($reusable['public_token'], $html);
        $this->links->disable($owner, $merchant->id, $reusable['id']);
        $this->denied(410, fn () => $this->tools->resolveWidget($button['id']));
        $this->assertSame(1, (int) $this->paymentPdo->query('SELECT count(*) FROM checkout_attempts')->fetchColumn());
    }

    public function test_webhook_outbox_signing_deduplication_retries_and_no_financial_effects(): void
    {
        [$owner, $merchant] = $this->ready();
        $endpoint = $this->webhooks->register($owner, $merchant->id, 'https://merchant.example/hooks');
        $raw = $this->paymentPdo->query('SELECT encrypted_secret FROM merchant_webhook_endpoints')->fetchColumn();
        $this->assertStringNotContainsString($endpoint['secret'], $raw);
        $this->assertSame([], $this->webhooks->enqueue($owner, $merchant->id));
        $payment = $this->createPayment($merchant)->assertOk()->json();
        $reference = $payment['reference'];
        $this->receiveEvent($this->event($reference))->assertOk();
        $deliveries = $this->webhooks->enqueue($owner, $merchant->id);
        $this->assertCount(1, $deliveries);
        $this->assertSame($deliveries, $this->webhooks->enqueue($owner, $merchant->id));
        $id = $deliveries[0]['id'];
        $this->transport->status = 503;
        $this->assertSame('retry', $this->webhooks->deliver($owner, $merchant->id, $id)['status']);
        $this->webhooks->deliver($owner, $merchant->id, $id);
        $this->assertCount(1, $this->transport->requests);
        $request = $this->transport->requests[0];
        $this->assertSame('93.184.216.34', $request['address']);
        $expected = 'v1='.hash_hmac('sha256', $request['headers']['X-XLipa-Timestamp'].'.'.$request['body'], $endpoint['secret']);
        $this->assertSame($expected, $request['headers']['X-XLipa-Signature']);
        $effects = 0;
        $business = function () use (&$effects): void {
            $effects++;
        };
        $this->assertTrue($this->webhooks->accept($request['body'], $request['headers'], $endpoint['secret'], $business));
        $this->assertFalse($this->webhooks->accept($request['body'], $request['headers'], $endpoint['secret'], $business));
        $this->assertSame(1, $effects);
        $this->denied(401, fn () => $this->webhooks->accept($request['body'].' ', $request['headers'], $endpoint['secret'], $business));
        $this->paymentAdmission->time += 4000;
        $this->assertSame(2, $this->webhooks->deliver($owner, $merchant->id, $id)['attempts']);
        $this->paymentAdmission->time += 8000;
        $this->assertSame('exhausted', $this->webhooks->deliver($owner, $merchant->id, $id)['status']);
        $this->paymentAdmission->time += 600000;
        $this->webhooks->deliver($owner, $merchant->id, $id);
        $this->assertCount(3, $this->transport->requests);
        $this->denied(401, fn () => $this->webhooks->accept($request['body'], $request['headers'], $endpoint['secret'], $business));
        $this->assertSame(0, (int) $this->paymentPdo->query('SELECT count(*) FROM ledger_journals')->fetchColumn());
        $this->assertSame(1, (int) $this->paymentPdo->query('SELECT count(*) FROM payment_intents')->fetchColumn());
    }

    public function test_destinations_reject_ssrf_and_revalidate_dns_before_each_attempt(): void
    {
        [$owner, $merchant] = $this->ready();
        foreach (['http://merchant.example/hook', 'https://127.0.0.1/hook', 'https://localhost/hook', 'https://merchant.example:444/hook', 'https://user:pass@merchant.example/hook', 'https://merchant.example/hook?token=secret', 'https://merchant.example/hook#fragment', 'https://[::1]/hook'] as $url) {
            $this->denied(422, fn () => $this->webhooks->register($owner, $merchant->id, $url));
        }
        foreach (['127.0.0.1', '10.0.0.1', '169.254.169.254', '100.100.100.200', '192.168.1.1', '0.0.0.0', '224.0.0.1', '::ffff:127.0.0.1'] as $ip) {
            $this->addresses = ['93.184.216.34', $ip];
            $this->denied(422, fn () => $this->webhooks->destination('https://merchant.example/hooks'));
        }
        $this->addresses = ['93.184.216.34'];
        $endpoint = $this->webhooks->register($owner, $merchant->id, 'https://merchant.example/hooks');
        $reference = $this->createPayment($merchant)->assertOk()->json('reference');
        $this->receiveEvent($this->event($reference))->assertOk();
        $id = $this->webhooks->enqueue($owner, $merchant->id)[0]['id'];
        $this->addresses = ['127.0.0.1'];
        $this->denied(422, fn () => $this->webhooks->deliver($owner, $merchant->id, $id));
        $this->assertCount(0, $this->transport->requests);
        $this->addresses = ['93.184.216.34'];
        $this->assertSame('delivered', $this->webhooks->deliver($owner, $merchant->id, $id)['status']);
        $this->webhooks->deliver($owner, $merchant->id, $id);
        $this->assertCount(1, $this->transport->requests);
        $this->webhooks->disable($owner, $merchant->id, $endpoint['id']);
        $this->assertSame('delivered', $this->webhooks->deliver($owner, $merchant->id, $id)['status']);
    }

    public function test_merchant_screens_and_chief_governance_are_authorized_and_redacted(): void
    {
        [$owner, $merchant] = $this->ready();
        $credential = $this->tools->issue($owner, $merchant->id, ['links:read'], $this->tools->now() + 60000);
        foreach (['api', 'credentials', 'qr', 'buttons', 'webhooks', 'docs'] as $tab) {
            $this->get('/merchants/'.$merchant->id.'/__isolated-developers/'.$tab)->assertOk()->assertDontSee($credential['token']);
        }
        $url = '/admin/__isolated-api-governance/'.$merchant->id;
        $this->get($url)->assertForbidden();
        $chief = User::findOrFail(PlatformRoleAssignment::where('role', 'chief_admin')->value('user_id'));
        $this->signIn($chief);
        $this->get($url)->assertOk()->assertSee('Chief Admin API governance')->assertDontSee($credential['token']);
        $staff = User::factory()->create();
        PlatformRoleAssignment::factory()->create(['user_id' => $staff->id, 'role' => 'admin']);
        $this->signIn($staff);
        $this->get($url)->assertForbidden();
        $this->get('/merchants/'.$merchant->id.'/__isolated-developers/credentials')->assertNotFound();
    }

    public function test_qr_encoding_has_bounded_safe_svg_output(): void
    {
        $svg = CheckoutQr::svg('http://xlipa.test/__isolated-checkout/'.str_repeat('a', 64));
        $this->assertSame($svg, CheckoutQr::svg('http://xlipa.test/__isolated-checkout/'.str_repeat('a', 64)));
        $this->assertStringNotContainsString('http://xlipa.test', $svg);
        $this->denied(422, fn () => CheckoutQr::svg(str_repeat('x', 135)));
        // Independently generated using Project Nayuki's reference, forced 6-L/byte/mask 0.
        foreach ([$svg => 'b50081d616e314c8a5dcb0956cf228ac2f40520b14e6910f571d3af798937ae5', CheckoutQr::svg(str_repeat('x', 134)) => 'fcf6199d8b88b33dd28c873089c04a9a165e4d759a090faed11746b70110779b'] as $image => $expected) {
            preg_match_all('/M([0-9]+),([0-9]+)h1v1h-1z/', $image, $matches, PREG_SET_ORDER);
            $matrix = array_fill(0, 1681, '0');
            foreach ($matches as $match) {
                $matrix[((int) $match[2] - 4) * 41 + (int) $match[1] - 4] = '1';
            }
            $this->assertSame($expected, hash('sha256', implode('', $matrix)));
        }
    }

    public function test_api_rate_limit_and_revocation_inside_the_reserved_mutation(): void
    {
        [$owner, $merchant] = $this->ready();
        $credential = $this->tools->issue($owner, $merchant->id, ['links:read', 'links:write'], $this->tools->now() + 600000);
        $store = new PaymentStore($this->paymentPdo);
        $this->tools->api($credential['token'], $merchant->id, 'links:read', fn (): array => []);
        $store->transaction(fn (): int => $store->write('UPDATE api_rate_windows SET count=120 WHERE credential_id=?', [$credential['id']]));
        $this->denied(429, fn () => $this->tools->api($credential['token'], $merchant->id, 'links:read', fn (): array => []));
        $this->paymentAdmission->time += 60000;
        $this->assertSame(['ok'], $this->tools->api($credential['token'], $merchant->id, 'links:read', fn (): array => ['ok']));
        $this->denied(401, fn () => $this->tools->api($credential['token'], $merchant->id, 'links:write', function () use ($owner, $merchant, $credential): array {
            $statement = $this->paymentPdo->prepare('UPDATE api_credentials SET revoked_at=? WHERE id=?');
            $statement->execute([$this->tools->now(), $credential['id']]);

            return $this->link($owner, $merchant->id);
        }));
        $this->assertSame(0, (int) $this->paymentPdo->query('SELECT count(*) FROM payment_links')->fetchColumn());
    }

    public function test_owner_management_http_csrf_and_one_time_secrets_and_read_only_payment_api(): void
    {
        [$owner, $merchant] = $this->ready();
        $base = '/merchants/'.$merchant->id.'/__isolated-developers';
        session()->save();
        $this->withCookie(config('session.cookie'), session()->getId())->withCredentials();
        $claims = ['scopes' => ['payments:read'], 'expires_at' => $this->tools->now() + 60000];
        $this->postJson($base.'/credentials', $claims)->assertStatus(419);
        $credential = $this->postJson($base.'/credentials', ['_token' => session()->token(), ...$claims])->assertOk()->assertHeader('Cache-Control', 'no-store, private')->json();
        $this->get($base.'/credentials')->assertOk()->assertDontSee($credential['token']);
        $link = $this->link($owner, $merchant->id);
        $this->postJson($base.'/widgets', ['_token' => session()->token(), 'link_id' => $link['id'], 'kind' => 'button', 'mode' => 'reusable'])->assertOk()->assertJsonPath('link_id', $link['id']);
        $endpoint = $this->postJson($base.'/webhooks', ['_token' => session()->token(), 'url' => 'https://merchant.example/hooks'])->assertOk()->json();
        $this->get($base.'/webhooks')->assertOk()->assertDontSee($endpoint['secret']);
        $this->postJson($base.'/webhooks/'.$endpoint['id'].'/disable', ['_token' => session()->token()])->assertOk();
        $reference = $this->createPayment($merchant)->assertOk()->json('reference');
        $this->withHeader('Authorization', 'Bearer '.$credential['token'])->getJson('/__isolated-api/v1/merchants/'.$merchant->id.'/payments/'.$reference)->assertOk()->assertJsonPath('data.status', 'pending');
        $this->assertSame(1, $this->provider->creates);
        $this->postJson($base.'/credentials/'.$credential['id'].'/revoke', ['_token' => session()->token()])->assertOk();
        $this->getJson('/__isolated-api/v1/merchants/'.$merchant->id.'/payments/'.$reference)->assertUnauthorized();
        $this->withHeader('Origin', 'https://attacker.example')->postJson($base.'/credentials', ['_token' => session()->token(), ...$claims])->assertForbidden();
        $this->getJson('/api/v1/merchants/'.$merchant->id.'/links')->assertNotFound();
    }

    public function test_webhook_crash_lease_recovery_receiver_rollback_and_conflicting_signed_replay(): void
    {
        [$owner, $merchant] = $this->ready();
        $endpoint = $this->webhooks->register($owner, $merchant->id, 'https://merchant.example/hooks');
        $reference = $this->createPayment($merchant)->assertOk()->json('reference');
        $this->receiveEvent($this->event($reference))->assertOk();
        $id = $this->webhooks->enqueue($owner, $merchant->id)[0]['id'];
        $store = new PaymentStore($this->paymentPdo);
        $store->transaction(fn (): int => $store->write("UPDATE merchant_webhook_deliveries SET status='sending',attempts=1,claim='lost-ack',next_at=? WHERE id=?", [$this->tools->now() + 60000, $id]));
        $this->assertSame('sending', $this->webhooks->deliver($owner, $merchant->id, $id)['status']);
        $this->assertCount(0, $this->transport->requests);
        $this->paymentAdmission->time += 60000;
        $this->assertSame('delivered', $this->webhooks->deliver($owner, $merchant->id, $id)['status']);
        $request = $this->transport->requests[0];
        try {
            $this->webhooks->accept($request['body'], $request['headers'], $endpoint['secret'], function (): void {
                throw new \RuntimeException('Fictional receiver failure');
            });
            $this->fail('Receiver failure must propagate.');
        } catch (\RuntimeException $error) {
            $this->assertSame('Fictional receiver failure', $error->getMessage());
        }
        $this->assertSame(0, (int) $this->paymentPdo->query('SELECT count(*) FROM merchant_webhook_receipts')->fetchColumn());
        $this->paymentPdo->exec('CREATE TABLE receiver_effects(event_id TEXT PRIMARY KEY)');
        $business = fn (PaymentStore $target, array $event): int => $target->write('INSERT INTO receiver_effects VALUES(?)', [$event['id']]);
        $this->assertTrue($this->webhooks->accept($request['body'], $request['headers'], $endpoint['secret'], $business));
        $this->assertFalse($this->webhooks->accept($request['body'], $request['headers'], $endpoint['secret'], $business));
        $this->assertSame(1, (int) $this->paymentPdo->query('SELECT count(*) FROM receiver_effects')->fetchColumn());
        $changed = json_decode($request['body'], true, flags: JSON_THROW_ON_ERROR);
        $changed['data']['amount_tzs'] = 1;
        $changedBody = json_encode($changed, JSON_THROW_ON_ERROR);
        $headers = $request['headers'];
        $headers['X-XLipa-Signature'] = 'v1='.hash_hmac('sha256', $headers['X-XLipa-Timestamp'].'.'.$changedBody, $endpoint['secret']);
        $this->denied(409, fn () => $this->webhooks->accept($changedBody, $headers, $endpoint['secret'], $business));
        $headers['X-XLipa-Event'] = 'forged-event-id';
        $this->denied(400, fn () => $this->webhooks->accept($changedBody, $headers, $endpoint['secret'], $business));
    }
}
