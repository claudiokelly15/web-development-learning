<?php

namespace App\Actions\Merchants;

use App\Models\Merchant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use LogicException;
use Throwable;

/** Application-phone and write-only identity workflow; never grants submission or financial authority. */
final class MerchantVerification
{
    public function __construct(
        private readonly VerificationEnvironment $environment,
        private readonly VerificationReadiness $readiness,
        private readonly VerificationCipher $cipher,
        private readonly VerificationStore $store,
        private readonly VerificationAuditReconciler $reconciler,
    ) {}

    private function authority(User $user, Merchant $merchant, bool $write = false): void
    {
        Gate::forUser($user)->authorize('manageVerification', $merchant);
        if ($write) {
            abort_unless(DB::table('merchants')->where('id', $merchant->id)->whereNotIn('operational_status', ['suspended', 'closed'])->exists()
                && DB::table('merchant_applications')->where('merchant_id', $merchant->id)->where('current_slot', 1)->where('status', 'draft')->exists(), 409, 'Verification changes are unavailable for this application.');
        }
    }

    private function available(): void
    {
        abort_unless($this->readiness->ready(), 503, 'Verification is unavailable. No information was saved.');
    }

    private function context(string $merchant): ?object
    {
        return DB::table('merchant_onboarding_contexts')->where('merchant_id', $merchant)->first();
    }

    private function pending(User $user, Merchant $merchant): bool
    {
        return DB::table('verification_limit_state')->where('merchant_id', $merchant->id)->where('actor_user_id', $user->id)->whereNotNull('pending_audit_event_id')->exists();
    }

    private function now(User $user, Merchant $merchant): int
    {
        return max($this->environment->milliseconds(), (int) ($this->context($merchant->id)?->updated_at ?? 0),
            (int) (DB::table('verification_limit_state')->where('merchant_id', $merchant->id)->where('actor_user_id', $user->id)->max('last_observed_at') ?? 0));
    }

    private function audit(User $user, Merchant $merchant, string $event, string $reason, ?string $request = null): void
    {
        DB::table('audit_logs')->insert(['id' => (string) Str::ulid(), 'event_type' => $event, 'actor_user_id' => $user->id, 'merchant_id' => $merchant->id, 'request_id' => $request ?? (string) Str::uuid(), 'metadata' => json_encode(['reason_code' => $reason], JSON_THROW_ON_ERROR), 'occurred_at' => VerificationAuditReconciler::occurredAt($this->now($user, $merchant))]);
    }

    public function binding(string $merchant, string $record, string $purpose, int $version, int $actor): array
    {
        return ['namespace' => $this->environment->namespace(), 'merchant' => $merchant, 'record' => $record, 'purpose' => $purpose, 'version' => $version, 'actor' => $actor];
    }

    /** Only safe status fields; never return ciphertext, fingerprints, targets or identity suffixes. */
    public function status(User $user, Merchant $merchant): array
    {
        $this->authority($user, $merchant);
        $base = ['available' => false, 'canChange' => false, 'identityAvailable' => false, 'contextVersion' => 0, 'contact' => 'Not recorded', 'identity' => 'Not recorded', 'phoneProof' => 'Not verified', 'challenge' => null, 'policyText' => null];
        if (! $this->readiness->ready()) {
            return $base;
        }
        $context = $this->context($merchant->id);
        $contact = $context && $context->contact_actor_user_id === $user->id
            ? DB::table('merchant_contact_versions')->where('id', $context->current_contact_id)->where('merchant_id', $merchant->id)->where('actor_user_id', $user->id)->where('state', 'current')->first(['id']) : null;
        $identity = $context ? DB::table('merchant_identity_records')->where('id', $context->current_identity_id)->where('merchant_id', $merchant->id)->where('declared_by_user_id', $user->id)->where('state', 'current')->exists() : false;
        $now = $this->now($user, $merchant);
        $pending = $this->pending($user, $merchant);
        $proof = $contact ? DB::table('contact_verification_proofs')->where('contact_id', $contact->id)->where('merchant_id', $merchant->id)->where('actor_user_id', $user->id)->where('state', 'available')->where('verified_at', '<=', $now)->where('expires_at', '>', $now)->first(['provenance']) : null;
        $challenge = $contact ? DB::table('phone_otp_challenges')->where('contact_id', $contact->id)->where('merchant_id', $merchant->id)->where('actor_user_id', $user->id)->where('state', 'active')->where('expires_at', '>', $now)->first(['id']) : null;
        $canChange = ! $pending && DB::table('merchants')->where('id', $merchant->id)->whereNotIn('operational_status', ['suspended', 'closed'])->exists()
            && DB::table('merchant_applications')->where('merchant_id', $merchant->id)->where('current_slot', 1)->where('status', 'draft')->exists();
        $policy = $this->environment->identityPolicy();

        return [...$base, 'available' => true, 'canChange' => $canChange, 'identityAvailable' => $policy !== null, 'contextVersion' => $context?->context_version ?? 0,
            'contact' => $contact ? 'Private contact recorded' : 'Not recorded',
            'identity' => $identity ? 'Private declaration recorded; identity not independently verified' : 'Not recorded',
            'phoneProof' => $pending ? 'Verification held for audit reconciliation' : ($proof ? ($proof->provenance === 'simulated' ? 'Simulated test proof only; not real verification' : 'Bound phone possession proof; not identity or payment approval') : 'Not verified or proof expired'),
            'challenge' => $pending ? null : $challenge?->id, 'policyText' => $policy['text'] ?? null];
    }

    /** Fresh expected context version prevents conflicting capture overwrites. */
    public function capture(User $user, Merchant $merchant, string $kind, #[\SensitiveParameter] array $input): void
    {
        $this->authority($user, $merchant, true);
        $this->available();
        $this->store->run(function () use ($user, $merchant, $kind, $input): void {
            $this->authority($user, $merchant, true);
            $this->available();
            abort_if($this->pending($user, $merchant), 409, 'Verification is held for reconciliation.');
            $context = $this->context($merchant->id);
            $now = $this->now($user, $merchant);
            abort_unless((int) $input['context_version'] === (int) ($context?->context_version ?? 0), 409, 'The onboarding state changed. Reload before trying again.');
            if (! $context) {
                DB::table('merchant_onboarding_contexts')->insert(['merchant_id' => $merchant->id, 'updated_at' => $now]);
                $context = $this->context($merchant->id);
            }
            $id = (string) Str::ulid();
            if ($kind === 'contact') {
                $phone = $input['phone'];
                abort_unless(is_string($phone) && preg_match('/^\+255[67][0-9]{8}$/D', $phone), 422, 'Use a supported Tanzania mobile number.');
                $kid = $this->environment->keyId('phone');
                $fingerprintKid = $this->environment->keyId('contact');
                $old = DB::table('merchant_contact_versions')->where('merchant_id', $merchant->id)->where('actor_user_id', $user->id)->where('state', 'current')->first();
                if ($old && $old->fingerprint_key_ref !== $fingerprintKid) {
                    abort(503, 'Contact key transition requires reviewed recovery.');
                }
                $version = (int) DB::table('merchant_contact_versions')->where('merchant_id', $merchant->id)->where('actor_user_id', $user->id)->max('version') + 1;
                if ($old) {
                    $this->revokeContact($user, $merchant, $old->id, $now);
                    $this->store->change('merchant_contact_versions', ['id' => $old->id, 'row_version' => $old->row_version], ['state' => 'retired', 'retired_at' => $now, 'row_version' => $old->row_version + 1]);
                }
                if ($context->current_contact_id && $context->current_contact_id !== $old?->id) {
                    $previous = DB::table('merchant_contact_versions')->where('id', $context->current_contact_id)->where('merchant_id', $merchant->id)->first();
                    if ($previous) {
                        $this->revokeContact($user, $merchant, $previous->id, $now);
                        $this->store->change('merchant_contact_versions', ['id' => $previous->id, 'row_version' => $previous->row_version], ['state' => 'retired', 'retired_at' => $now, 'row_version' => $previous->row_version + 1]);
                    }
                }
                DB::table('merchant_contact_versions')->insert(['id' => $id, 'merchant_id' => $merchant->id, 'actor_user_id' => $user->id, 'purpose' => 'application_phone', 'version' => $version,
                    'phone_ciphertext' => $this->cipher->encrypt('phone', $phone, $this->binding($merchant->id, $id, 'application_phone', $version, $user->id), $kid),
                    'phone_key_ref' => $kid, 'envelope_version' => 1, 'target_fingerprint' => hash_hmac('sha256', VerificationCipher::lp('xlipa-contact-fp-v1', $phone), $this->environment->key('contact', $fingerprintKid)), 'fingerprint_key_ref' => $fingerprintKid, 'created_at' => $now]);
                $changes = ['current_contact_id' => $id, 'contact_actor_user_id' => $user->id, 'contact_purpose' => 'application_phone'];
                $this->audit($user, $merchant, $context->current_contact_id ? 'contact.changed' : 'contact.created', 'owner_declared');
            } elseif ($kind === 'identity') {
                $policy = $this->environment->identityPolicy();
                abort_unless($policy, 503, 'Identity capture is unavailable pending policy approval.');
                $nin = $this->environment->normalizeIdentity($input['nin']);
                abort_unless(strlen($nin) > 0 && strlen($nin) <= 256 && ! preg_match('/[\x00-\x1f\x7f]/', $nin)
                    && in_array($input['subject_claim'], ['individual_owner', 'authorized_representative'], true) && in_array($input['declaration'], [true, 1, '1', 'yes', 'on'], true), 422, 'A valid identity declaration is required.');
                $version = (int) DB::table('merchant_identity_records')->where('merchant_id', $merchant->id)->max('version') + 1;
                if ($context->current_identity_id) {
                    $old = DB::table('merchant_identity_records')->where('id', $context->current_identity_id)->where('merchant_id', $merchant->id)->first();
                    $this->store->change('merchant_identity_records', ['id' => $old->id, 'row_version' => $old->row_version], ['state' => 'superseded', 'superseded_at' => $now, 'row_version' => $old->row_version + 1]);
                }
                $kid = $this->environment->keyId('nin');
                DB::table('merchant_identity_records')->insert(['id' => $id, 'merchant_id' => $merchant->id, 'version' => $version, 'subject_claim' => $input['subject_claim'],
                    'nin_ciphertext' => $this->cipher->encrypt('nin', $nin, $this->binding($merchant->id, $id, 'identity_declaration', $version, $user->id), $kid),
                    'encryption_key_ref' => $kid, 'envelope_version' => 1, 'syntax_policy_version' => $policy['syntax'], 'syntax_checked_at' => $now, 'declaration_version' => $policy['declaration'], 'notice_version' => $policy['notice'], 'declared_by_user_id' => $user->id, 'declared_at' => $now]);
                $changes = ['current_identity_id' => $id];
                $this->audit($user, $merchant, $context->current_identity_id ? 'identity.changed' : 'identity.recorded', 'owner_declared');
            } else {
                throw new LogicException('Capture unavailable.');
            }
            $this->store->change('merchant_onboarding_contexts', ['merchant_id' => $merchant->id, 'context_version' => $context->context_version], [...$changes, 'context_version' => $context->context_version + 1, 'updated_at' => $now]);
        });
    }

    private function revokeContact(User $user, Merchant $merchant, string $contact, int $now): void
    {
        $changed = DB::table('contact_verification_proofs')->where('merchant_id', $merchant->id)->where('contact_id', $contact)->whereIn('state', ['available', 'consumed'])->update(['state' => 'revoked', 'revoked_at' => $now, 'reason_code' => 'contact_changed', 'state_version' => DB::raw('state_version+1')]);
        $changed += DB::table('phone_otp_challenges')->where('merchant_id', $merchant->id)->where('contact_id', $contact)->where('active_slot', 1)->update(['state' => 'revoked', 'active_slot' => null, 'terminal_at' => $now, 'state_version' => DB::raw('state_version+1')]);
        if ($changed > 0) {
            $this->audit($user, $merchant, 'contact.proof_changed', 'contact_changed');
        }
    }

    private function contact(User $user, Merchant $merchant): object
    {
        $context = $this->context($merchant->id);
        $contact = $context && $context->contact_actor_user_id === $user->id
            ? DB::table('merchant_contact_versions')->where('id', $context->current_contact_id)->where('merchant_id', $merchant->id)->where('actor_user_id', $user->id)->where('purpose', 'application_phone')->where('state', 'current')->first() : null;
        abort_unless($contact, 409, 'Record a current private contact before requesting verification.');
        abort_unless($contact->fingerprint_key_ref === $this->environment->keyId('contact'), 503, 'Contact key transition requires reviewed recovery.');

        return $contact;
    }

    private function target(object $contact): string
    {
        $phone = $this->cipher->decrypt('phone', $contact->phone_ciphertext, $this->binding($contact->merchant_id, $contact->id, 'application_phone', $contact->version, $contact->actor_user_id), $contact->phone_key_ref, $contact->envelope_revision);
        if (! preg_match('/^\+255[67][0-9]{8}$/D', $phone) || ! hash_equals($contact->target_fingerprint, hash_hmac('sha256', VerificationCipher::lp('xlipa-contact-fp-v1', $phone), $this->environment->key('contact', $contact->fingerprint_key_ref)))) {
            throw new LogicException('Verification unavailable.');
        }

        return $phone;
    }

    private function limit(object $contact, int $now): array
    {
        $scope = hash_hmac('sha256', VerificationCipher::lp('xlipa-otp-fail-scope-v1', $contact->merchant_id, (string) $contact->actor_user_id, $this->target($contact)), $this->environment->key('contact', $contact->fingerprint_key_ref));
        $where = ['scope_key' => $scope, 'scope_key_version' => $contact->fingerprint_key_ref, 'policy_rule' => 'otp.fail.rolling30.v1'];
        $row = DB::table('verification_limit_state')->where($where)->first();
        if (! $row) {
            DB::table('verification_limit_state')->insert([...$where, 'merchant_id' => $contact->merchant_id, 'actor_user_id' => $contact->actor_user_id, 'last_observed_at' => $now, 'updated_at' => $now]);
            $row = DB::table('verification_limit_state')->where($where)->first();
        }
        if ($row->merchant_id !== $contact->merchant_id || $row->actor_user_id !== $contact->actor_user_id) {
            throw new LogicException('Verification unavailable.');
        }
        $now = max($now, $row->last_observed_at);
        $failures = [];
        for ($i = 1; $i <= 10; $i++) {
            $field = sprintf('failure_%02d_at', $i);
            if ($row->$field !== null && $now - 1800000 < $row->$field) {
                $failures[] = $row->$field;
            }
        }

        return [$row, $now, $failures];
    }

    private function persistLimit(object $row, int $now, array $failures, ?int $lastSend, array $pending = []): void
    {
        $values = ['last_observed_at' => $now, 'updated_at' => $now, 'last_send_at' => $lastSend, 'state_version' => $row->state_version + 1];
        for ($i = 1; $i <= 10; $i++) {
            $values[sprintf('failure_%02d_at', $i)] = $failures[$i - 1] ?? null;
        }
        $this->store->change('verification_limit_state', ['scope_key' => $row->scope_key, 'scope_key_version' => $row->scope_key_version, 'policy_rule' => $row->policy_rule, 'state_version' => $row->state_version], [...$values, ...$pending]);
    }

    public function requestCode(User $user, Merchant $merchant, string $request, string $ip): ?string
    {
        $this->authority($user, $merchant, true);
        $this->available();
        abort_unless(Str::isUuid($request) && strtolower($request) === $request, 422, 'Invalid request identifier.');
        $id = $this->store->run(function () use ($user, $merchant, $request): ?string {
            $this->authority($user, $merchant, true);
            $this->available();
            if ($this->pending($user, $merchant)) {
                return null;
            }
            $contact = $this->contact($user, $merchant);
            [$limit,$now,$failures] = $this->limit($contact, $this->now($user, $merchant));
            $existing = DB::table('otp_delivery_intents')->where('merchant_id', $merchant->id)->where('actor_user_id', $user->id)->where('request_id', $request)->first();
            if ($existing) {
                abort_unless($existing->contact_id === $contact->id, 409, 'Request identifier already used.');

                return null;
            }
            $sends = DB::table('otp_delivery_intents as i')->join('merchant_contact_versions as c', 'c.id', '=', 'i.contact_id')->where('i.merchant_id', $merchant->id)->where('i.actor_user_id', $user->id)->where('c.target_fingerprint', $contact->target_fingerprint)->where('c.fingerprint_key_ref', $contact->fingerprint_key_ref);
            $allowed = count($failures) < 10 && ($limit->last_send_at === null || $now >= $limit->last_send_at + 60000)
                && (clone $sends)->where('i.requested_at', '>', $now - 900000)->count() < 3 && (clone $sends)->where('i.requested_at', '>', $now - 3600000)->count() < 5;
            $this->persistLimit($limit, $now, $failures, $allowed ? $now : $limit->last_send_at);
            if (! $allowed) {
                return null;
            }
            $old = DB::table('phone_otp_challenges')->where('contact_id', $contact->id)->where('active_slot', 1)->first();
            if ($old) {
                $this->store->change('phone_otp_challenges', ['id' => $old->id, 'state_version' => $old->state_version], ['state' => $now >= $old->expires_at ? 'expired' : 'revoked', 'active_slot' => null, 'terminal_at' => $now, 'state_version' => $old->state_version + 1]);
            }
            $id = (string) Str::ulid();
            $base = ['merchant_id' => $merchant->id, 'actor_user_id' => $user->id, 'contact_id' => $contact->id, 'purpose' => 'application_phone', 'transport_mode' => $this->environment->mode()];
            DB::table('phone_otp_challenges')->insert(['id' => $id, ...$base, 'active_slot' => 1, 'created_at' => $now, 'expires_at' => $now + 300000]);
            DB::table('otp_delivery_intents')->insert(['id' => (string) Str::ulid(), ...$base, 'challenge_id' => $id, 'request_id' => $request, 'requested_at' => $now]);
            $this->audit($user, $merchant, 'otp.requested', $old ? 'resend' : 'initial', $request);

            return $id;
        });
        if ($id) {
            $this->dispatch($user, $merchant, $id, $ip);
        }

        return $id;
    }

    private function digest(object $challenge, object $contact, #[\SensitiveParameter] string $code, string $kid): string
    {
        return hash_hmac('sha256', VerificationCipher::lp('xlipa-otp-code-v1', $challenge->id, $challenge->merchant_id, (string) $challenge->actor_user_id, $contact->id, (string) $contact->version, $contact->purpose, $contact->target_fingerprint, $contact->fingerprint_key_ref, $code), $this->environment->key('otp', $kid));
    }

    private function dispatch(User $user, Merchant $merchant, string $id, string $ip): void
    {
        $code = sprintf('%06d', random_int(0, 999999));
        try {
            $claimed = $this->store->run(function () use ($user, $merchant, $id, $code): ?array {
                $this->authority($user, $merchant, true);
                $this->available();
                if ($this->pending($user, $merchant)) {
                    return null;
                }
                $contact = $this->contact($user, $merchant);
                $now = $this->now($user, $merchant);
                $c = DB::table('phone_otp_challenges')->where('id', $id)->where('contact_id', $contact->id)->where('merchant_id', $merchant->id)->where('actor_user_id', $user->id)->where('state', 'queued')->where('expires_at', '>', $now)->first();
                if (! $c) {
                    return null;
                }
                $intent = DB::table('otp_delivery_intents')->where('challenge_id', $id)->where('state', 'queued')->first();
                if (! $intent) {
                    return null;
                }
                $kid = $this->environment->keyId('otp');
                $this->store->change('phone_otp_challenges', ['id' => $id, 'state_version' => $c->state_version], ['state' => 'dispatching', 'code_digest' => $this->digest($c, $contact, $code, $kid), 'digest_key_ref' => $kid, 'digest_created_at' => $now, 'state_version' => $c->state_version + 1]);
                $this->store->change('otp_delivery_intents', ['id' => $intent->id, 'claim_version' => $intent->claim_version], ['state' => 'claimed', 'claimed_at' => $now, 'lease_expires_at' => min($now + 30000, $c->expires_at), 'claim_version' => $intent->claim_version + 1]);

                return [$contact, $intent];
            });
            if (! $claimed) {
                return;
            }
            [$contact,$intent] = $claimed;
            $outcome = 'unknown';
            try {
                $this->authority($user, $merchant, true);
                $this->available();
                if ($this->environment->reserveDeliveryBudget($contact->target_fingerprint, $intent->request_id, $ip)) {
                    $outcome = $this->environment->deliver($this->target($contact), $code, $intent->request_id);
                    if (! in_array($outcome, ['accepted', 'rejected', 'unknown'], true)) {
                        $outcome = 'unknown';
                    }
                } else {
                    $outcome = 'rejected';
                }
            } catch (Throwable) {
                $outcome = 'unknown';
            }
            $this->store->run(function () use ($user, $merchant, $id, $contact, $outcome): void {
                $c = DB::table('phone_otp_challenges')->where('id', $id)->first();
                $i = DB::table('otp_delivery_intents')->where('challenge_id', $id)->first();
                if ($i->state !== 'claimed') {
                    return;
                }
                $now = $this->now($user, $merchant);
                $state = $outcome;
                $authorized = $this->readiness->ready() && Gate::forUser($user)->allows('manageVerification', $merchant)
                    && $this->context($merchant->id)?->current_contact_id === $contact->id && ! $this->pending($user, $merchant)
                    && DB::table('merchants')->where('id', $merchant->id)->whereNotIn('operational_status', ['suspended', 'closed'])->exists()
                    && DB::table('merchant_applications')->where('merchant_id', $merchant->id)->where('current_slot', 1)->where('status', 'draft')->exists();
                if (! $authorized) {
                    $state = 'unknown';
                }
                if ($now >= $i->lease_expires_at) {
                    $state = 'unknown';
                }
                $failure = match ($state) {
                    'accepted' => null,'rejected' => 'provider_rejected',default => 'ambiguous_timeout'
                };
                $this->store->change('otp_delivery_intents', ['id' => $i->id, 'claim_version' => $i->claim_version], ['state' => $state, 'failure_code' => $failure, 'completed_at' => $now, 'claim_version' => $i->claim_version + 1]);
                if ($c->state === 'dispatching') {
                    $next = $now >= $c->expires_at ? 'expired' : (! $authorized ? 'revoked' : match ($state) {
                        'accepted' => 'active','rejected' => 'delivery_failed',default => 'delivery_unknown'
                    });
                    $terminal = in_array($next, ['expired', 'revoked', 'delivery_failed'], true);
                    $this->store->change('phone_otp_challenges', ['id' => $id, 'state_version' => $c->state_version], ['state' => $next, 'active_slot' => $terminal ? null : 1, 'terminal_at' => $terminal ? $now : null, 'state_version' => $c->state_version + 1]);
                }
                $this->audit($user, $merchant, 'otp.changed', match ($state) {
                    'accepted' => 'dispatch_accepted','rejected' => 'dispatch_rejected',default => 'delivery_unknown'
                }, $i->request_id);
            });
        } finally {
            sodium_memzero($code);
        }
    }

    public function verify(User $user, Merchant $merchant, string $challenge, #[\SensitiveParameter] string $code): bool
    {
        $this->authority($user, $merchant, true);
        $this->available();
        $obligation = null;
        $result = $this->store->run(function () use ($user, $merchant, $challenge, $code, &$obligation): bool {
            $this->authority($user, $merchant, true);
            $this->available();
            if ($this->pending($user, $merchant)) {
                return false;
            }
            $contact = $this->contact($user, $merchant);
            [$limit,$now,$failures] = $this->limit($contact, $this->now($user, $merchant));
            $c = DB::table('phone_otp_challenges')->where('id', $challenge)->where('merchant_id', $merchant->id)->where('actor_user_id', $user->id)->where('contact_id', $contact->id)->where('purpose', 'application_phone')->where('state', 'active')->first();
            if (! $c || count($failures) >= 10 || $now < $c->created_at || $now >= $c->expires_at) {
                if ($c && $now >= $c->expires_at) {
                    $this->store->change('phone_otp_challenges', ['id' => $c->id, 'state_version' => $c->state_version], ['state' => 'expired', 'active_slot' => null, 'terminal_at' => $now, 'state_version' => $c->state_version + 1]);
                }
                $this->persistLimit($limit, $now, $failures, $limit->last_send_at);

                return false;
            }
            $intent = DB::table('otp_delivery_intents')->where('challenge_id', $c->id)->where('state', 'accepted')->first();
            if (! $intent) {
                return false;
            }
            $match = preg_match('/^[0-9]{6}$/D', $code) && hash_equals($c->code_digest, $this->digest($c, $contact, $code, $c->digest_key_ref));
            if (! $match) {
                $failures[] = $now;
                $count = $c->failed_attempts + 1;
                $exhausted = $count === 5;
                $this->store->change('phone_otp_challenges', ['id' => $c->id, 'state_version' => $c->state_version], ['failed_attempts' => $count, 'state' => $exhausted ? 'exhausted' : 'active', 'active_slot' => $exhausted ? null : 1, 'terminal_at' => $exhausted ? $now : null, 'state_version' => $c->state_version + 1]);
                $pending = [];
                if ($exhausted || count($failures) === 10) {
                    $pending = ['pending_audit_event_id' => (string) Str::ulid(), 'pending_audit_challenge_id' => $c->id, 'pending_audit_reason_code' => count($failures) === 10 ? 'aggregate_exhausted' : 'exhausted', 'pending_audit_request_id' => $intent->request_id, 'pending_audit_occurred_at' => VerificationAuditReconciler::occurredAt($now)];
                    $obligation = [$limit->scope_key, $limit->scope_key_version, $pending['pending_audit_event_id']];
                }
                $this->persistLimit($limit,$now,$failures,$limit->last_send_at,$pending);

                return false;
            }
            $this->persistLimit($limit,$now,$failures,$limit->last_send_at);
            $this->store->change('phone_otp_challenges',['id' => $c->id, 'state_version' => $c->state_version],['state' => 'consumed', 'active_slot' => null, 'terminal_at' => $now, 'state_version' => $c->state_version + 1]);
            DB::table('contact_verification_proofs')->insert(['id' => (string) Str::ulid(), 'merchant_id' => $merchant->id, 'actor_user_id' => $user->id, 'contact_id' => $contact->id, 'purpose' => 'application_phone', 'challenge_id' => $c->id, 'channel' => 'sms', 'provenance' => $c->transport_mode, 'policy_version' => 'application-phone-proof-v1', 'verified_at' => $now, 'expires_at' => $now + 1800000]);
            $this->audit($user,$merchant,'otp.verified','code_matched',$intent->request_id);

            return true;
        });
        if ($obligation) {
            try {
                $this->reconciler->reconcile(...$obligation);
            } catch (Throwable) {
            }
        }

        return $result;
    }
}
