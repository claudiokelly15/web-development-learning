<?php

namespace App\Actions\Payments;

use App\Actions\Merchants\SubmissionEvidenceSchema;
use App\Actions\Merchants\SubmissionOutcomeUnknown;
use App\Actions\Merchants\SubmissionTransaction;
use App\Actions\Merchants\WorkflowSchemaReadiness;
use App\Models\Merchant;
use App\Models\User;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Throwable;

final class PaymentEngine
{
    public const MAX_AMOUNT_TZS = 1_000_000_000_000;

    public function __construct(private readonly PaymentStore $store, private readonly PaymentProvider $provider, private readonly PaymentAdmission $admission) {}

    public function create(User $actor, string $merchant, mixed $amount, string $currency, string $idempotency, ?string $order = null): array
    {
        return $this->createIntent($actor, $merchant, $amount, $currency, $idempotency, $order);
    }

    /** Explicit guest capability; never installs the merchant's authentication or session. */
    public function createForCheckout(string $attempt, PaymentLinkAdmission $admission): array
    {
        $context = $this->checkoutContext($attempt);
        $context['admission'] = $admission;

        return $this->createIntent(User::query()->findOrFail($context['actor_id']), $context['merchant_id'], $context['amount_tzs'], $context['currency'], 'checkout_'.$attempt, $attempt, $context);
    }

    private function createIntent(User $actor, string $merchant, mixed $amount, string $currency, string $idempotency, ?string $order, ?array $checkout = null): array
    {
        abort_unless(is_int($amount) && $amount > 0 && $amount <= self::MAX_AMOUNT_TZS && $currency === 'TZS', 422);
        abort_unless(preg_match('/\A[A-Za-z0-9_-]{16,80}\z/D', $idempotency) === 1 && ($order === null || preg_match('/\A[A-Za-z0-9_-]{1,64}\z/D', $order) === 1), 422);

        return $this->authorized($actor, $merchant, 'create', function (array $authority) use ($merchant, $amount, $currency, $idempotency, $order, $checkout): array {
            $result = $this->store->transaction(function () use ($merchant, $amount, $currency, $idempotency, $order, $authority, $checkout): array {
                if ($checkout !== null) {
                    $current = $this->checkoutContext($checkout['attempt_id']);
                    abort_unless($this->checkoutAllowed($checkout) && $current['state'] === 'active' && ($current['expires_at'] === null || $current['expires_at'] > $this->now()), 410);
                    abort_unless($current['payment_reference'] === null && $current['customer_hash'] === hash('sha256', session()->getId()) && $current['amount_tzs'] === $amount && $current['currency'] === $currency, 409);
                    foreach ($authority as $field => $value) {
                        abort_unless($current[$field] === $value, 409);
                    }
                }
                $existing = $this->store->rows('SELECT * FROM payment_intents WHERE merchant_id=? AND provider=? AND idempotency_key=?', [$merchant, $this->provider->id(), $idempotency]);
                if ($existing !== []) {
                    $row = $existing[0];
                    $this->assertOwnership($row, $merchant, $authority);
                    abort_unless($row['amount_tzs'] === $amount && $row['currency'] === $currency && $row['order_reference'] === $order && $row['actor_id'] === $authority['actor_id'], 409);

                    return [$row, false];
                }
                $reference = (string) Str::uuid();
                $time = $this->now();
                $this->store->write('INSERT INTO payment_intents(reference,merchant_id,actor_id,auth_version,membership_id,authority_version,application_id,provider,amount_tzs,currency,idempotency_key,order_reference,status,dispatch_state,created_at,updated_at,version,last_sequence) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,0)',
                    [$reference, $merchant, $authority['actor_id'], $authority['auth_version'], $authority['membership_id'], $authority['authority_version'], $authority['application_id'], $this->provider->id(), $amount, $currency, $idempotency, $order, 'pending', 'unattempted', $time, $time]);
                if ($checkout !== null) {
                    abort_unless($this->store->write('UPDATE checkout_attempts SET payment_reference=? WHERE id=? AND payment_reference IS NULL', [$reference, $checkout['attempt_id']]) === 1, 409);
                }

                return [$this->payment($reference), true];
            });
            [$row, $created] = $result;
            if (! $created) {
                return $this->safe($row);
            }
            $this->store->transaction(function () use ($row): void {
                abort_unless($this->store->write("UPDATE payment_intents SET dispatch_state='attempted',status='unknown',updated_at=?,version=version+1 WHERE reference=? AND dispatch_state='unattempted'", [$this->now(), $row['reference']]) === 1, 409);
            });
            try {
                $response = $this->normalize($this->provider->create($row), $row);
            } catch (Throwable) {
                return $this->safe($this->payment($row['reference']));
            }
            // Provider creation acknowledges acceptance only. Terminal evidence enters through events.
            abort_unless(in_array($response['status'], ['pending', 'unknown'], true), 503);
            try {
                $this->store->transaction(function () use ($row, $response): void {
                    $this->store->write('UPDATE payment_intents SET provider_reference=?,status=?,updated_at=?,version=version+1 WHERE reference=?', [$response['correlation'], $response['status'], $this->now(), $row['reference']]);
                });
            } catch (Throwable) {
                throw new PaymentOutcomeUnknown;
            }

            return $this->safe($this->payment($row['reference']));
        }, $checkout);
    }

    public function read(User $actor, string $merchant, string $reference): array
    {
        return $this->authorized($actor, $merchant, 'read', function (array $authority) use ($merchant, $reference): array {
            $row = $this->payment($reference);
            $this->assertOwnership($row, $merchant, $authority);

            return $this->safe($row);
        });
    }

    /** Query observations do not manufacture terminal confirmation evidence. */
    public function query(User $actor, string $merchant, string $reference): array
    {
        return $this->authorized($actor, $merchant, 'query', function (array $authority) use ($merchant, $reference): array {
            $row = $this->payment($reference);
            $this->assertOwnership($row, $merchant, $authority);
            abort_unless($row['dispatch_state'] === 'attempted', 404);
            abort_unless(($this->provider->capabilities()['query'] ?? false) === true, 503);
            try {
                $response = $this->normalize($this->provider->query($row), $row);
            } catch (Throwable) {
                throw new PaymentOutcomeUnknown;
            }

            return [...$this->safe($row), 'provider_observation' => $response['status']];
        });
    }

    public function receive(string $body, string $signature): array
    {
        abort_unless($this->admission->eventsAuthorized($this->provider->id()), 503);
        $time = $this->now();
        $event = $this->provider->verifyEvent($body, $signature, $time);

        return $this->store->transaction(function () use ($event, $body, $time): array {
            $hash = hash('sha256', $body);
            $existing = $this->store->rows('SELECT body_hash,outcome FROM payment_events WHERE provider=? AND provider_event_id=?', [$this->provider->id(), $event['id']]);
            if ($existing !== []) {
                abort_unless(hash_equals($existing[0]['body_hash'], $hash), 409);

                return ['status' => 'duplicate'];
            }
            $row = $this->payment($event['reference']);
            abort_unless($row['provider'] === $this->provider->id() && $row['dispatch_state'] === 'attempted' && $row['amount_tzs'] === $event['amount_tzs'] && $row['currency'] === $event['currency']
                && $event['occurred_at'] >= $row['created_at'] && ($row['provider_reference'] === null || $row['provider_reference'] === $event['correlation']), 409);
            $outcome = 'applied';
            if ($event['sequence'] <= $row['last_sequence']) {
                $outcome = 'out_of_order';
            } elseif (in_array($row['status'], ['confirmed', 'failed'], true)) {
                $outcome = $row['status'] === $event['status'] ? 'duplicate_outcome' : (in_array($event['status'], ['pending', 'unknown'], true) ? 'out_of_order' : 'conflict');
            }
            $eventReference = (string) Str::uuid();
            $this->store->write('INSERT INTO payment_events(id,provider,provider_event_id,payment_reference,body_hash,raw_body,received_at,retain_until,normalized_status,sequence,outcome,occurred_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)',
                [$eventReference, $this->provider->id(), $event['id'], $row['reference'], $hash, $body, $time, $time + 86400000, $event['status'], $event['sequence'], $outcome, $event['occurred_at']]);
            if ($outcome === 'applied') {
                $this->store->write('UPDATE payment_intents SET provider_reference=?,status=?,last_sequence=?,updated_at=?,version=version+1 WHERE reference=?', [$event['correlation'], $event['status'], $event['sequence'], $time, $row['reference']]);
                if ($event['status'] === 'confirmed') {
                    $this->store->write('INSERT INTO payment_confirmations(id,payment_reference,event_id,merchant_id,amount_tzs,currency,confirmed_at) VALUES(?,?,?,?,?,?,?)', [(string) Str::uuid(), $row['reference'], $eventReference, $row['merchant_id'], $row['amount_tzs'], $row['currency'], $time]);
                }
            }

            return ['status' => $outcome];
        });
    }

    public function expireEventBodies(): int
    {
        abort_unless($this->admission->eventsAuthorized($this->provider->id()), 503);

        return $this->store->transaction(fn (): int => $this->store->write('UPDATE payment_events SET raw_body=NULL WHERE raw_body IS NOT NULL AND retain_until<=?', [$this->now()]));
    }

    /** Internal accounting boundary: current authority and this exact payment store's writer stay reserved. */
    public function accountingTransaction(User $actor, string $merchant, Closure $work): mixed
    {
        return $this->authorized($actor, $merchant, 'read', fn (array $authority): mixed => $this->store->transaction(function () use ($actor, $merchant, $work, $authority): mixed {
            $result = $work($this->store, $authority);
            $current = User::query()->findOrFail($actor->id);
            $business = Merchant::query()->findOrFail($merchant);
            Gate::forUser($current)->authorize('manageVerification', $business);
            abort_unless($this->admission->authorized($actor->id, $merchant, 'read') && $current->getRawOriginal('account_status') === 'active' && $current->email_verified_at !== null
                && $current->auth_version === $authority['auth_version'] && session('auth_version') === $current->auth_version && $current->email_binding_version === $actor->email_binding_version
                && ! in_array($business->operational_status->value, ['suspended', 'closed'], true)
                && DB::table('merchant_memberships')->where('id', $authority['membership_id'])->where('merchant_id', $merchant)->where('user_id', $actor->id)->where('role', 'owner')->where('status', 'active')->whereNull('revoked_at')->where('authority_version', $authority['authority_version'])->exists()
                && DB::table('merchant_applications')->where('id', $authority['application_id'])->where('merchant_id', $merchant)->where('current_slot', 1)->where('status', 'approved')->where('profile_version', $business->profile_version)->exists(), 409);

            return $result;
        }));
    }

    private function authorized(User $actor, string $merchant, string $operation, Closure $work, ?array $checkout = null): mixed
    {
        abort_unless(($checkout === null ? auth()->id() === $actor->id : $this->checkoutAllowed($checkout)) && $this->admission->authorized($actor->id, $merchant, $operation), 503);
        try {
            return app(SubmissionTransaction::class)->run(function () use ($actor, $merchant, $operation, $work, $checkout): mixed {
                abort_unless(app(WorkflowSchemaReadiness::class)->profile() === WorkflowSchemaReadiness::BNC && $this->admission->authorized($actor->id, $merchant, $operation) && ($checkout === null || $this->checkoutAllowed($checkout)), 503);
                $current = User::query()->findOrFail($actor->id);
                abort_unless($current->getRawOriginal('account_status') === 'active' && $current->email_verified_at !== null && $current->auth_version === $actor->auth_version
                    && ($checkout === null ? session('auth_version') === $current->auth_version : $checkout['auth_version'] === $current->auth_version), 403);
                $business = Merchant::query()->findOrFail($merchant);
                Gate::forUser($current)->authorize('manageVerification', $business);
                abort_if(in_array($business->operational_status->value, ['suspended', 'closed'], true), 409);
                $application = DB::table('merchant_applications')->where('merchant_id', $merchant)->where('current_slot', 1)->where('status', 'approved')->first();
                $metadata = json_encode(['from' => 'pending_review', 'to' => 'approved', 'profile_version' => $business->profile_version, 'reason_code' => 'requirements_satisfied'], JSON_THROW_ON_ERROR);
                abort_unless($application && $application->submitted_by_user_id === $current->id && $application->profile_version === $business->profile_version
                    && DB::table('audit_logs')->where('merchant_id', $merchant)->where('application_id', $application->id)->where('actor_user_id', $application->reviewed_by_user_id)->where('event_type', 'application.reviewed')->where('occurred_at', $application->reviewed_at)->where('metadata', $metadata)->exists(), 409);
                $membership = DB::table('merchant_memberships')->where('merchant_id', $merchant)->where('user_id', $current->id)->where('role', 'owner')->where('status', 'active')->whereNull('revoked_at')->sole();
                $evidence = DB::table(SubmissionEvidenceSchema::TABLE)->where('application_id', $application->id)->where('merchant_id', $merchant)->first();
                abort_unless($evidence && $evidence->actor_user_id === $current->id && $evidence->auth_version === $current->auth_version
                    && $evidence->email_binding_version === $current->email_binding_version && $evidence->membership_id === $membership->id
                    && $evidence->authority_version === $membership->authority_version && $evidence->profile_version === $business->profile_version, 409);

                return $work(['actor_id' => $current->id, 'auth_version' => $current->auth_version, 'membership_id' => $membership->id, 'authority_version' => $membership->authority_version, 'application_id' => $application->id]);
            });
        } catch (AuthorizationException) {
            abort(403);
        } catch (SubmissionOutcomeUnknown) {
            throw new PaymentOutcomeUnknown;
        }
    }

    private function assertOwnership(array $payment, string $merchant, array $authority): void
    {
        abort_unless($payment['merchant_id'] === $merchant && $payment['provider'] === $this->provider->id(), 404);
        foreach ($authority as $field => $value) {
            abort_unless($payment[$field] === $value, 404);
        }
    }

    private function checkoutContext(string $attempt): array
    {
        $rows = $this->store->rows('SELECT l.*,a.id AS attempt_id,a.customer_hash,a.payment_reference FROM checkout_attempts a JOIN payment_links l ON l.id=a.link_id WHERE a.id=?', [$attempt]);
        abort_unless(count($rows) === 1, 404);

        return $rows[0];
    }

    private function checkoutAllowed(array $context): bool
    {
        return PHP_SAPI === 'cli' && getenv('XLIPA_SYNTHETIC_ONLY') === '1' && app()->environment('testing') && auth()->guest()
            && $context['customer_hash'] === hash('sha256', session()->getId()) && $context['admission']->customerAuthorized($context['merchant_id'], 'initiate');
    }

    private function payment(string $reference): array
    {
        $rows = $this->store->rows('SELECT * FROM payment_intents WHERE reference=?', [$reference]);
        abort_unless(count($rows) === 1, 404);

        return $rows[0];
    }

    private function normalize(array $response, array $payment): array
    {
        abort_unless(array_keys($response) === ['reference', 'correlation', 'amount_tzs', 'currency', 'status'] && $response['reference'] === $payment['reference']
            && $response['amount_tzs'] === $payment['amount_tzs'] && $response['currency'] === $payment['currency'] && is_string($response['correlation'])
            && preg_match('/\A[A-Za-z0-9_-]{1,80}\z/D', $response['correlation']) === 1 && ($payment['provider_reference'] === null || $payment['provider_reference'] === $response['correlation'])
            && in_array($response['status'], ['pending', 'unknown', 'confirmed', 'failed'], true), 503);

        return $response;
    }

    private function now(): int
    {
        $time = $this->admission->milliseconds();
        abort_unless($time >= 0 && $time <= 253402214399999, 503);

        return $time;
    }

    private function safe(array $payment): array
    {
        return array_intersect_key($payment, array_flip(['reference', 'merchant_id', 'provider', 'amount_tzs', 'currency', 'status', 'order_reference', 'created_at']));
    }
}
