<?php

namespace App\Actions\Payments;

use App\Models\Merchant;
use App\Models\User;
use App\Staging\StagingFinancialAuthority;
use Closure;
use Illuminate\Support\Str;

/** Fixed-amount isolated links. Public sessions have no merchant or accounting authority. */
final class PaymentLinkService
{
    public function __construct(private readonly PaymentEngine $payments, private readonly PaymentStore $store, private readonly MockPaymentLedger $ledger, private readonly PaymentLinkAdmission $admission) {}

    public function create(User $actor, string $merchant, mixed $amount, string $currency, string $description, string $request, ?int $expires = null): array
    {
        abort_unless(is_int($amount) && $amount > 0 && $amount <= PaymentEngine::MAX_AMOUNT_TZS && $currency === 'TZS', 422);
        abort_unless(mb_check_encoding($description, 'UTF-8') && $description === trim($description) && mb_strlen($description) >= 1 && mb_strlen($description) <= 200 && preg_match('/[\p{C}]/u', $description) === 0
            && preg_match('/\A[A-Za-z0-9_-]{16,80}\z/D', $request) === 1, 422);

        return $this->manage($actor, $merchant, function (PaymentStore $store, array $authority) use ($merchant, $amount, $currency, $description, $request, $expires): array {
            $existing = $store->rows('SELECT * FROM payment_links WHERE merchant_id=? AND request_key=?', [$merchant, $request]);
            if ($existing !== []) {
                $link = $existing[0];
                foreach ($authority as $field => $value) {
                    abort_unless($link[$field] === $value, 404);
                }
                abort_unless($link['amount_tzs'] === $amount && $link['currency'] === $currency && $link['description'] === $description && $link['expires_at'] === $expires, 409);

                return $this->safeLink($link);
            }
            $time = $this->now();
            abort_unless($expires === null || ($expires > $time && $expires <= 253402214399999), 422);
            $id = (string) Str::uuid();
            $token = bin2hex(random_bytes(32));
            $store->write('INSERT INTO payment_links(id,public_token,merchant_id,actor_id,auth_version,membership_id,authority_version,application_id,amount_tzs,currency,description,business_name,request_key,state,created_at,expires_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                [$id, $token, $merchant, $authority['actor_id'], $authority['auth_version'], $authority['membership_id'], $authority['authority_version'], $authority['application_id'], $amount, $currency, $description, Merchant::query()->findOrFail($merchant)->business_name, $request, 'active', $time, $expires]);

            return $this->safeLink($store->rows('SELECT * FROM payment_links WHERE id=?', [$id])[0]);
        });
    }

    public function disable(User $actor, string $merchant, string $id): array
    {
        return $this->manage($actor, $merchant, function (PaymentStore $store) use ($merchant, $id): array {
            $rows = $store->rows('SELECT * FROM payment_links WHERE id=? AND merchant_id=?', [$id, $merchant]);
            abort_unless(count($rows) === 1, 404);
            if ($rows[0]['state'] === 'active') {
                $store->write("UPDATE payment_links SET state='disabled',disabled_at=? WHERE id=? AND state='active'", [$this->now(), $id]);
            }

            return $this->safeLink($store->rows('SELECT * FROM payment_links WHERE id=?', [$id])[0]);
        });
    }

    public function history(User $actor, string $merchant): array
    {
        return $this->manage($actor, $merchant, function (PaymentStore $store) use ($merchant): array {
            $links = array_map(fn (array $row): array => $this->safeLink($row), $store->rows('SELECT * FROM payment_links WHERE merchant_id=? ORDER BY created_at,id', [$merchant]));
            $attempts = $store->rows('SELECT a.id,a.link_id,a.payment_reference,p.status,p.amount_tzs,p.currency,j.id AS journal_id FROM checkout_attempts a JOIN payment_links l ON l.id=a.link_id
                LEFT JOIN payment_intents p ON p.reference=a.payment_reference LEFT JOIN ledger_journals j ON j.payment_reference=p.reference
                WHERE l.merchant_id=? ORDER BY a.created_at,a.id', [$merchant]);

            return ['links' => $links, 'attempts' => $attempts];
        });
    }

    public function open(string $token): array
    {
        $this->isolated();
        $link = $this->link($token);
        abort_unless($this->admission->customerAuthorized($link['merchant_id'], 'resolve'), 503);
        $this->active($link);

        return $this->safeLink($link);
    }

    public function initiate(string $token, string $nonce): array
    {
        $this->isolated();
        abort_unless(auth()->guest() && preg_match('/\A[a-f0-9]{64}\z/D', $nonce) === 1 && hash_equals((string) session('checkout.nonces.'.$token), $nonce), 409);
        [$attempt, $created] = $this->store->transaction(function () use ($token, $nonce): array {
            $link = $this->link($token);
            abort_unless($this->admission->customerAuthorized($link['merchant_id'], 'initiate'), 503);
            $customer = hash('sha256', session()->getId());
            $rows = $this->store->rows('SELECT * FROM checkout_attempts WHERE link_id=? AND customer_hash=?', [$link['id'], $customer]);
            if ($rows !== []) {
                abort_unless(hash_equals($rows[0]['nonce_hash'], hash('sha256', $nonce)), 409);

                return [$rows[0], false];
            }
            $this->active($link);
            if ($this->store->rows("SELECT name FROM sqlite_master WHERE type='table' AND name='checkout_widgets'") !== []
                && $this->store->rows("SELECT id FROM checkout_widgets WHERE link_id=? AND mode='dynamic'", [$link['id']]) !== []) {
                abort_unless($this->store->rows('SELECT id FROM checkout_attempts WHERE link_id=?', [$link['id']]) === [], 410);
            }
            $id = (string) Str::uuid();
            $this->store->write('INSERT INTO checkout_attempts(id,link_id,customer_hash,nonce_hash,created_at) VALUES(?,?,?,?,?)', [$id, $link['id'], $customer, hash('sha256', $nonce), $this->now()]);

            return [$this->store->rows('SELECT * FROM checkout_attempts WHERE id=?', [$id])[0], true];
        });
        if ($created) {
            $this->payments->createForCheckout($attempt['id'], $this->admission);
        }

        return $this->customerStatus($token, $attempt['id']);
    }

    /** Historical status is independent of subsequent link disable/expiry and never retries dispatch. */
    public function customerStatus(string $token, string $attempt): array
    {
        $this->isolated();
        abort_unless(auth()->guest(), 403);
        $link = $this->link($token);
        abort_unless($this->admission->customerAuthorized($link['merchant_id'], 'status'), 503);
        $rows = $this->store->rows('SELECT a.id,a.payment_reference,p.status FROM checkout_attempts a LEFT JOIN payment_intents p ON p.reference=a.payment_reference WHERE a.id=? AND a.link_id=? AND a.customer_hash=?',
            [$attempt, $link['id'], hash('sha256', session()->getId())]);
        abort_unless(count($rows) === 1, 404);

        return ['link' => $this->safeLink($link), 'attempt_id' => $attempt, 'payment_reference' => $rows[0]['payment_reference'], 'status' => $rows[0]['status'] ?? 'unresolved'];
    }

    public function consume(User $actor, string $merchant, string $attempt, bool $reconcile = false): array
    {
        $source = $this->manage($actor, $merchant, function (PaymentStore $store) use ($merchant, $attempt): array {
            $rows = $store->rows('SELECT c.id,c.payment_reference,c.amount_tzs,c.currency FROM checkout_attempts a JOIN payment_links l ON l.id=a.link_id JOIN payment_confirmations c ON c.payment_reference=a.payment_reference WHERE a.id=? AND l.merchant_id=? AND c.merchant_id=?', [$attempt, $merchant, $merchant]);
            abort_unless(count($rows) === 1, 409);

            return $rows[0];
        });

        return $reconcile ? $this->ledger->reconcile($actor, $merchant, $source['id'])
            : $this->ledger->post($actor, $merchant, $source['id'], $source['payment_reference'], $source['amount_tzs'], $source['currency']);
    }

    private function manage(User $actor, string $merchant, Closure $work): array
    {
        $this->isolated();
        abort_unless($this->admission->authorized($actor->id, $merchant), 503);

        return $this->payments->accountingTransaction($actor, $merchant, function (PaymentStore $store, array $authority) use ($actor, $merchant, $work): array {
            abort_unless($this->admission->authorized($actor->id, $merchant), 503);
            $result = $work($store, $authority);
            abort_unless($this->admission->authorized($actor->id, $merchant), 503);

            return $result;
        });
    }

    private function link(string $token): array
    {
        abort_unless(preg_match('/\A[a-f0-9]{64}\z/D', $token) === 1, 404);
        $rows = $this->store->rows('SELECT * FROM payment_links WHERE public_token=?', [$token]);
        abort_unless(count($rows) === 1, 404);

        return $rows[0];
    }

    private function active(array $link): void
    {
        abort_unless($link['state'] === 'active' && ($link['expires_at'] === null || $link['expires_at'] > $this->now()), 410);
    }

    private function safeLink(array $link): array
    {
        return array_intersect_key($link, array_flip(['id', 'public_token', 'business_name', 'amount_tzs', 'currency', 'description', 'state', 'created_at', 'expires_at']));
    }

    private function now(): int
    {
        $time = $this->admission->milliseconds();
        abort_unless($time >= 0 && $time <= 253402214399999, 503);

        return $time;
    }

    private function isolated(): void
    {
        abort_unless((PHP_SAPI === 'cli' && getenv('XLIPA_SYNTHETIC_ONLY') === '1' && app()->environment('testing')) || StagingFinancialAuthority::allows(StagingFinancialAuthority::ACTIONS), 503);
    }
}
