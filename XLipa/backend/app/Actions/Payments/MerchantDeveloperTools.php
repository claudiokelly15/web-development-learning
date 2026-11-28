<?php

namespace App\Actions\Payments;

use App\Models\User;
use App\Staging\StagingFinancialAuthority;
use App\Staging\StagingIdentity;
use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/** Isolated P9 services. Deliberately not bound in operational providers or routes. */
final class MerchantDeveloperTools
{
    private bool $widgetScope = false;

    public const SCOPES = ['payments:read', 'links:read', 'links:write'];

    public function __construct(private readonly PaymentEngine $payments, private readonly PaymentStore $store, private readonly PaymentLinkService $links, private readonly Closure $clock) {}

    public function now(): int
    {
        $this->guard();
        $time = ($this->clock)();
        abort_unless(is_int($time) && $time >= 0, 503);

        return $time;
    }

    public function guard(): void
    {
        abort_unless((PHP_SAPI === 'cli' && getenv('XLIPA_SYNTHETIC_ONLY') === '1' && app()->environment('testing')) || ($this->widgetScope && StagingFinancialAuthority::widgetAllowed()), 503);
    }

    public function manage(User $actor, string $merchant, Closure $work): mixed
    {
        $this->guard();

        return $this->payments->accountingTransaction($actor, $merchant, $work);
    }

    public function issue(User $actor, string $merchant, array $scopes, int $expires, ?string $rotate = null): array
    {
        abort_unless($scopes !== [] && array_is_list($scopes) && count($scopes) === count(array_unique($scopes)) && array_diff($scopes, self::SCOPES) === []
            && $expires > $this->now() && $expires <= $this->now() + 7776000000, 422);

        return $this->manage($actor, $merchant, function (PaymentStore $store, array $authority) use ($merchant, $scopes, $expires, $rotate): array {
            if ($rotate !== null) {
                $old = $store->rows('SELECT * FROM api_credentials WHERE id=? AND merchant_id=? AND revoked_at IS NULL', [$rotate, $merchant])[0] ?? null;
                abort_unless($old !== null, 404);
                $store->write('UPDATE api_credentials SET revoked_at=? WHERE id=?', [$this->now(), $rotate]);
            }
            $id = (string) Str::uuid();
            $secret = bin2hex(random_bytes(32));
            $token = 'xl_test_'.$id.'.'.$secret;
            $store->write('INSERT INTO api_credentials VALUES(?,?,?,?,?,?,?,?,?,NULL)', [$id, $merchant, json_encode($authority, JSON_THROW_ON_ERROR), hash('sha256', $token), json_encode($scopes, JSON_THROW_ON_ERROR), $this->now(), $expires, substr($secret, -4), $rotate]);

            return ['id' => $id, 'token' => $token, 'scopes' => $scopes, 'expires_at' => $expires];
        });
    }

    public function revoke(User $actor, string $merchant, string $id): void
    {
        $this->manage($actor, $merchant, function (PaymentStore $store) use ($merchant, $id): void {
            abort_unless($store->rows('SELECT id FROM api_credentials WHERE id=? AND merchant_id=?', [$id, $merchant]) !== [], 404);
            $store->write('UPDATE api_credentials SET revoked_at=? WHERE id=? AND revoked_at IS NULL', [$this->now(), $id]);
        });
    }

    public function overview(User $actor, string $merchant): array
    {
        return $this->manage($actor, $merchant, fn (PaymentStore $store): array => $this->redacted($store, $merchant));
    }

    public function redacted(PaymentStore $store, string $merchant): array
    {
        $this->guard();

        return ['credentials' => $store->rows('SELECT id,scopes,created_at,expires_at,last_four,rotated_from,revoked_at FROM api_credentials WHERE merchant_id=? ORDER BY created_at,id', [$merchant]),
            'widgets' => $store->rows('SELECT w.id,w.kind,w.mode,w.link_id,l.description,l.public_token,l.amount_tzs,l.state FROM checkout_widgets w JOIN payment_links l ON l.id=w.link_id WHERE w.merchant_id=? ORDER BY w.id', [$merchant]),
            'endpoints' => $store->rows('SELECT id,url,enabled,created_at FROM merchant_webhook_endpoints WHERE merchant_id=? ORDER BY id', [$merchant]),
            'deliveries' => $store->rows('SELECT id,event_id,status,attempts,next_at,http_status FROM merchant_webhook_deliveries WHERE merchant_id=? ORDER BY id', [$merchant])];
    }

    /** Bearer authority is revalidated against P4's full current approval and evidence checks. */
    public function api(#[\SensitiveParameter] string $token, string $merchant, string $scope, Closure $work): mixed
    {
        $this->guard();
        abort_unless(strlen($token) === 109 && preg_match('/\Axl_test_([0-9a-f-]{36})\.[0-9a-f]{64}\z/D', $token, $match) === 1, 401);
        $row = $this->store->rows('SELECT * FROM api_credentials WHERE id=? AND merchant_id=?', [$match[1], $merchant])[0] ?? null;
        abort_unless($row !== null && hash_equals($row['token_hash'], hash('sha256', $token)) && $row['revoked_at'] === null && $row['expires_at'] > $this->now(), 401);
        abort_unless(in_array($scope, json_decode($row['scopes'], true, flags: JSON_THROW_ON_ERROR), true), 403);
        $authority = json_decode($row['authority'], true, flags: JSON_THROW_ON_ERROR);
        $actor = User::query()->findOrFail($authority['actor_id']);
        $previousUser = Auth::guard()->user();
        $previousSession = session()->all();
        try {
            Auth::guard()->setUser($actor);
            session()->flush();
            session()->replace(['auth_version' => $authority['auth_version']]);
            $this->manage($actor, $merchant, function (PaymentStore $store, array $current) use ($authority, $row): void {
                abort_unless($current === $authority, 401);
                $fresh = $store->rows('SELECT revoked_at,expires_at FROM api_credentials WHERE id=?', [$row['id']])[0];
                abort_unless($fresh['revoked_at'] === null && $fresh['expires_at'] > $this->now(), 401);
                $window = intdiv($this->now(), 60000);
                $rate = $store->rows('SELECT window,count FROM api_rate_windows WHERE credential_id=?', [$row['id']])[0] ?? null;
                abort_unless($rate === null || $rate['window'] !== $window || $rate['count'] < 120, 429);
                $store->write('INSERT INTO api_rate_windows VALUES(?,?,1) ON CONFLICT(credential_id) DO UPDATE SET window=excluded.window,count=CASE WHEN api_rate_windows.window=excluded.window THEN api_rate_windows.count+1 ELSE 1 END', [$row['id'], $window]);
            });

            return $this->payments->withCapabilityGuard(function () use ($row): void {
                $fresh = $this->store->rows('SELECT revoked_at,expires_at FROM api_credentials WHERE id=?', [$row['id']])[0];
                abort_unless($fresh['revoked_at'] === null && $fresh['expires_at'] > $this->now(), 401);
            }, fn (): mixed => $work($actor));
        } finally {
            session()->flush();
            session()->replace($previousSession);
            $previousUser === null ? Auth::guard()->forgetUser() : Auth::guard()->setUser($previousUser);
        }
    }

    public function createLink(User $actor, string $merchant, array $input, string $key): array
    {
        abort_unless(array_diff(array_keys($input), ['amount_tzs', 'currency', 'description', 'expires_at']) === [] && is_string($input['currency'] ?? null) && is_string($input['description'] ?? null)
            && (! isset($input['expires_at']) || is_int($input['expires_at'])), 422);

        return $this->links->create($actor, $merchant, $input['amount_tzs'] ?? null, $input['currency'], $input['description'], $key, $input['expires_at'] ?? null);
    }

    public function history(User $actor, string $merchant): array
    {
        return $this->manage($actor, $merchant, fn (PaymentStore $store): array => ['links' => $store->rows('SELECT id,public_token,business_name,amount_tzs,currency,description,state,created_at,expires_at FROM payment_links WHERE merchant_id=? ORDER BY created_at DESC,id DESC LIMIT 100', [$merchant]),
            'attempts' => $store->rows('SELECT a.id,a.link_id,a.payment_reference,p.status,p.amount_tzs,p.currency FROM checkout_attempts a JOIN payment_links l ON l.id=a.link_id LEFT JOIN payment_intents p ON p.reference=a.payment_reference WHERE l.merchant_id=? ORDER BY a.created_at DESC,a.id DESC LIMIT 100', [$merchant]), 'limit' => 100]);
    }

    public function payment(User $actor, string $merchant, string $reference): array
    {
        return $this->payments->read($actor, $merchant, $reference);
    }

    public function widget(User $actor, string $merchant, string $linkId, string $kind, string $mode): array
    {
        abort_unless(in_array($kind, ['qr', 'button'], true) && in_array($mode, ['dynamic', 'reusable'], true), 422);

        return $this->widgetCall(fn (): array => $this->manage($actor, $merchant, function (PaymentStore $store) use ($merchant, $linkId, $kind, $mode): array {
            $link = $store->rows('SELECT * FROM payment_links WHERE id=? AND merchant_id=?', [$linkId, $merchant])[0] ?? null;
            abort_unless($link !== null, 404);
            abort_unless($link['state'] === 'active' && ($link['expires_at'] === null || $link['expires_at'] > $this->now()), 410);
            abort_unless($mode !== 'dynamic' || $link['expires_at'] !== null, 422);
            abort_unless($store->rows('SELECT id FROM checkout_widgets WHERE link_id=? AND mode<>?', [$linkId, $mode]) === [], 409);
            $existing = $store->rows('SELECT * FROM checkout_widgets WHERE link_id=? AND kind=? AND mode=?', [$linkId, $kind, $mode]);
            if ($existing !== []) {
                return $existing[0];
            }
            $id = (string) Str::uuid();
            $store->write('INSERT INTO checkout_widgets VALUES(?,?,?,?,?)', [$id, $merchant, $linkId, $kind, $mode]);

            return ['id' => $id, 'merchant_id' => $merchant, 'link_id' => $linkId, 'kind' => $kind, 'mode' => $mode];
        }));
    }

    public function resolveWidget(string $id): array
    {
        return $this->widgetCall(fn (): array => $this->resolveScopedWidget($id));
    }

    private function widgetCall(Closure $work): mixed
    {
        $previous = $this->widgetScope;
        $this->widgetScope = StagingFinancialAuthority::widgetAllowed();
        try {
            return $work();
        } finally {
            $this->widgetScope = $previous;
        }
    }

    private function resolveScopedWidget(string $id): array
    {
        $this->guard();
        $row = $this->store->rows('SELECT w.*,l.public_token FROM checkout_widgets w JOIN payment_links l ON l.id=w.link_id WHERE w.id=?', [$id])[0] ?? null;
        abort_unless($row !== null, 404);
        $link = $this->links->open($row['public_token']);
        if ($row['mode'] === 'dynamic') {
            abort_unless($this->store->rows('SELECT id FROM checkout_attempts WHERE link_id=?', [$row['link_id']]) === [], 410);
        }

        return $link;
    }

    public function checkoutUrl(string $token): string
    {
        abort_unless(preg_match('/\A[a-f0-9]{64}\z/D', $token) === 1, 422);

        return StagingFinancialAuthority::widgetAllowed()
            ? StagingIdentity::ORIGIN.'/fictional-checkout/'.$token
            : 'http://xlipa.test/__isolated-checkout/'.$token;
    }

    public function button(string $id): string
    {
        $link = $this->resolveWidget($id);

        return '<a href="'.htmlspecialchars($this->checkoutUrl($link['public_token']), ENT_QUOTES, 'UTF-8').'" target="_blank" rel="noopener noreferrer">Pay with XLipa</a>';
    }
}
