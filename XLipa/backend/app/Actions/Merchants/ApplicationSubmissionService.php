<?php

namespace App\Actions\Merchants;

use App\Models\Merchant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/** Internal command service only. The public PD-04A endpoint remains unconditionally blocked. */
final class ApplicationSubmissionService
{
    public const EXPECTATIONS = ['application_id', 'membership_id', 'auth_version', 'email_binding_version', 'authority_version', 'attempt_number', 'profile_version', 'context_version', 'identity_id', 'identity_version', 'contact_id', 'contact_version', 'proof_id', 'submission_request_id'];

    public function __construct(
        private readonly SubmissionAdmission $admission,
        private readonly SubmissionEvidenceEnvironment $custody,
        private readonly VerificationEnvironment $verification,
        private readonly SubmissionEvidenceCipher $cipher,
        private readonly VerificationCipher $sourceCipher,
        private readonly SubmissionTransaction $transaction,
    ) {}

    /** Returns a historical event receipt, never current identity/payment approval. */
    public function submit(User $actor, string $merchant, array $expected): array
    {
        return $this->execute($actor, $merchant, $expected, false);
    }

    /** Read/validate only. Null means authoritative absence; it does not automatically retry. */
    public function resolve(User $actor, string $merchant, array $expected): ?array
    {
        return $this->execute($actor, $merchant, $expected, true);
    }

    private function input(string $merchant, array $expected): void
    {
        abort_unless(count($expected) === count(self::EXPECTATIONS) && ! array_diff(array_keys($expected), self::EXPECTATIONS), 422, 'Invalid submission expectations.');
        foreach (['merchant_id' => $merchant, ...$expected] as $field => $value) {
            if ($field === 'submission_request_id') {
                $valid = is_string($value) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/D', $value);
            } elseif (in_array($field, ['merchant_id', 'application_id', 'identity_id', 'contact_id', 'proof_id'], true)) {
                $valid = is_string($value) && preg_match('/^[0-7][0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{25}$/D', $value);
            } else {
                $valid = is_int($value) && $value > 0;
            }
            abort_unless($valid, 422, 'Invalid submission expectations.');
        }
    }

    private function authority(User $actor, string $merchant, array $expected): array
    {
        abort_unless(auth()->id() === $actor->id && auth()->user()->auth_version === $actor->auth_version && session('auth_version') === $expected['auth_version'], 403, 'Submission access denied.');
        $user = DB::table('users')->where('id', $actor->id)->first();
        $business = Merchant::query()->find($merchant);
        abort_unless($user && $business && $user->account_status === 'active' && $user->email_verified_at !== null && $user->auth_version === $actor->auth_version && $user->auth_version === $expected['auth_version'], 403, 'Submission access denied.');
        Gate::forUser($actor)->authorize('manageVerification', $business);
        $membership = DB::table('merchant_memberships')->where('id', $expected['membership_id'])->where('merchant_id', $merchant)->where('user_id', $actor->id)->where('role', 'owner')->where('status', 'active')->whereNull('revoked_at')->first();
        abort_unless($membership, 403, 'Submission access denied.');
        abort_if(in_array($business->getRawOriginal('operational_status'), ['suspended', 'closed'], true), 409, 'Merchant unavailable.');

        return [$user, $business, $membership];
    }

    private function operational(User $actor, string $merchant, array $expected): int
    {
        abort_unless($this->admission->authorized($actor->id, $merchant, $expected) && $this->admission->restoreReady() && $this->custody->authorized() && $this->custody->restoreReady() && $this->verification->authorized() && $this->verification->mode() === 'live' && $this->custody->namespace() === $this->verification->namespace(), 503, 'Submission unavailable.');
        $now = $this->admission->milliseconds();
        abort_unless($now >= 0 && $now <= 253402300799999 && $this->custody->milliseconds() >= $now && $this->verification->milliseconds() >= $now, 503, 'Submission time unavailable.');

        return $now;
    }

    private function execute(User $actor, string $merchant, array $expected, bool $resolve): ?array
    {
        $this->input($merchant, $expected);
        abort_unless(auth()->id() === $actor->id, 403, 'Submission access denied.');
        try {
            $this->operational($actor, $merchant, $expected);
            $schemaProfile = app(WorkflowSchemaReadiness::class)->submissionProfile();

            return $this->transaction->run(function () use ($actor, $merchant, $expected, $resolve, $schemaProfile): ?array {
                $now = $this->operational($actor, $merchant, $expected);
                abort_unless(app(WorkflowSchemaReadiness::class)->submissionProfile() === $schemaProfile, 503, 'Submission schema unavailable.');
                [$user, $business, $membership] = $this->authority($actor, $merchant, $expected);
                $existing = DB::table(SubmissionEvidenceSchema::TABLE)->where('merchant_id', $merchant)->where('actor_user_id', $actor->id)->where('submission_request_id', $expected['submission_request_id'])->first();
                if ($existing) {
                    $receipt = $this->receipt((array) $existing, $expected, $now);
                    $this->authority($actor, $merchant, $expected);

                    return $receipt;
                }
                $application = DB::table('merchant_applications')->where('id', $expected['application_id'])->where('merchant_id', $merchant)->first();
                abort_unless($application && $application->status === 'draft' && (string) $application->current_slot === '1' && $application->profile_snapshot === null && $application->submitted_at === null && $application->submitted_by_user_id === null, 409, 'Application state changed.');
                abort_if(DB::table(SubmissionEvidenceSchema::TABLE)->where('application_id', $application->id)->exists() || DB::table('audit_logs')->where('merchant_id', $merchant)->where('actor_user_id', $actor->id)->where('event_type', 'application.submitted')->where('request_id', $expected['submission_request_id'])->exists(), 409, 'Submission integrity hold.');
                $context = DB::table('merchant_onboarding_contexts')->where('merchant_id', $merchant)->first();
                abort_unless($user->email_binding_version === $expected['email_binding_version'] && $membership->authority_version === $expected['authority_version'] && $business->profile_version === $expected['profile_version'] && $application->profile_version === $expected['profile_version'] && $application->attempt_number === $expected['attempt_number'] && $context && $context->context_version === $expected['context_version'] && $context->current_identity_id === $expected['identity_id'] && $context->current_contact_id === $expected['contact_id'] && $context->contact_actor_user_id === $actor->id && $context->contact_purpose === 'application_phone' && $context->updated_at <= $now, 409, 'Submission expectations changed.');
                abort_if(DB::table('verification_limit_state')->where('merchant_id', $merchant)->where(function ($query) use ($now): void {
                    $query->whereNotNull('pending_audit_event_id')->orWhere('last_observed_at', '>', $now);
                })->exists(), 409, 'Verification reconciliation required.');
                if ($resolve) {
                    return null;
                }
                $evidence = [...$expected, 'merchant_id' => $merchant, 'actor_user_id' => $actor->id, 'contract_version' => 'submission-evidence-v1', 'purpose' => 'application_phone', 'submitted_at_ms' => $now, 'submitted_at' => VerificationAuditReconciler::occurredAt($now), 'audit_event_id' => (string) Str::ulid(), 'audit_event_type' => 'application.submitted', 'evidence_key_ref' => $this->custody->keyId('evidence'), 'binding_key_ref' => $this->custody->keyId('binding'), 'envelope_version' => 1, 'retention_policy_version' => $this->admission->retentionPolicy()];
                [$identity, $contact, $proof, $challenge, $intent] = $this->lineage($evidence);
                $policy = $this->verification->identityPolicy();
                abort_unless($identity['state'] === 'current' && $contact['state'] === 'current' && $policy && $identity['syntax_policy_version'] === $policy['syntax'] && $identity['declaration_version'] === $policy['declaration'] && $identity['notice_version'] === $policy['notice'] && in_array($identity['subject_claim'], ['individual_owner', 'authorized_representative'], true) && $identity['syntax_checked_at'] !== null && $identity['syntax_checked_at'] <= $now && $identity['declared_at'] <= $now && $contact['created_at'] <= $now && $proof['state'] === 'available' && $proof['consumed_at'] === null && $proof['revoked_at'] === null && $proof['expires_at'] > $now, 409, 'Current verification required.');
                $this->trust($evidence, $identity, $proof, $challenge, $intent);
                [$nin, $phone] = $this->contents($evidence, $identity, $contact);
                $payload = ['v' => 1, 'email' => $user->email, 'email_verified_at' => CarbonImmutable::parse($user->email_verified_at, 'UTC')->format('Y-m-d H:i:s.v'), 'business_name' => $business->business_name, 'market' => 'TZ', 'currency' => 'TZS', 'identity_content_mac' => $this->cipher->commitment('identity', $nin, $evidence), 'contact_content_mac' => $this->cipher->commitment('contact', $phone, $evidence)];
                $evidence['evidence_ciphertext'] = $this->cipher->encrypt($evidence, $payload);
                DB::table(SubmissionEvidenceSchema::TABLE)->insert($evidence);
                abort_unless(DB::table('contact_verification_proofs')->where('id', $proof['id'])->where('state', 'available')->where('state_version', $proof['state_version'])->whereNull('consumed_at')->whereNull('revoked_at')->update(['state' => 'consumed', 'state_version' => LifecycleMutation::next($proof['state_version']), 'consumed_application_id' => $application->id, 'consumed_at' => $now]) === 1, 409, 'Proof state changed.');
                abort_unless(DB::table('merchant_applications')->where('id', $application->id)->where('merchant_id', $merchant)->where('status', 'draft')->where('current_slot', '1')->where('profile_version', $expected['profile_version'])->where('attempt_number', $expected['attempt_number'])->update(['status' => 'pending_review', 'submitted_by_user_id' => $actor->id, 'submitted_at' => $evidence['submitted_at']]) === 1, 409, 'Application state changed.');
                $finalTime = $this->operational($actor, $merchant, $expected);
                $receipt = $this->receipt((array) DB::table(SubmissionEvidenceSchema::TABLE)->where('application_id', $application->id)->first(), $expected, $finalTime);
                [$finalUser, $finalMerchant, $finalMembership] = $this->authority($actor, $merchant, $expected);
                $finalContext = DB::table('merchant_onboarding_contexts')->where('merchant_id', $merchant)->first();
                abort_unless($finalTime >= $now && $finalTime < $proof['expires_at'] && $finalUser->email_binding_version === $expected['email_binding_version'] && $finalMembership->authority_version === $expected['authority_version'] && $finalMerchant->profile_version === $expected['profile_version'] && $finalContext && $finalContext->context_version === $expected['context_version'] && $finalContext->current_identity_id === $expected['identity_id'] && $finalContext->current_contact_id === $expected['contact_id'], 409, 'Submission state changed.');
                abort_if(DB::table('verification_limit_state')->where('merchant_id', $merchant)->where(function ($query) use ($finalTime): void {
                    $query->whereNotNull('pending_audit_event_id')->orWhere('last_observed_at', '>', $finalTime);
                })->exists(), 409, 'Verification reconciliation required.');

                return $receipt;
            });
        } catch (AuthorizationException) {
            abort(403, 'Submission access denied.');
        } catch (SubmissionOutcomeUnknown $error) {
            throw $error;
        } catch (HttpExceptionInterface $error) {
            abort($error->getStatusCode(), 'Submission could not be accepted.');
        } catch (Throwable) {
            abort(503, 'Submission unavailable.');
        }
    }

    private function lineage(array $evidence): array
    {
        $identity = (array) DB::table('merchant_identity_records')->where('id', $evidence['identity_id'])->where('merchant_id', $evidence['merchant_id'])->where('declared_by_user_id', $evidence['actor_user_id'])->where('version', $evidence['identity_version'])->first();
        $contact = (array) DB::table('merchant_contact_versions')->where('id', $evidence['contact_id'])->where('merchant_id', $evidence['merchant_id'])->where('actor_user_id', $evidence['actor_user_id'])->where('purpose', 'application_phone')->where('version', $evidence['contact_version'])->first();
        $proof = (array) DB::table('contact_verification_proofs')->where('id', $evidence['proof_id'])->where('merchant_id', $evidence['merchant_id'])->where('actor_user_id', $evidence['actor_user_id'])->where('contact_id', $evidence['contact_id'])->where('purpose', 'application_phone')->first();
        abort_unless($identity && $contact && $proof, 409, 'Verification lineage changed.');
        $challenge = (array) DB::table('phone_otp_challenges')->where('id', $proof['challenge_id'])->first();
        $intent = (array) DB::table('otp_delivery_intents')->where('challenge_id', $proof['challenge_id'])->first();
        abort_unless($challenge && $intent, 409, 'Verification lineage unavailable.');

        return [$identity, $contact, $proof, $challenge, $intent];
    }

    private function trust(array $evidence, array $identity, array $proof, array $challenge, array $intent): void
    {
        abort_unless($proof['provenance'] === 'live' && $proof['channel'] === 'sms' && $proof['policy_version'] === 'application-phone-proof-v1' && $proof['verified_at'] <= $evidence['submitted_at_ms'] && $challenge['transport_mode'] === 'live' && $challenge['state'] === 'consumed' && $challenge['terminal_at'] === $proof['verified_at'] && $intent['transport_mode'] === 'live' && $intent['state'] === 'accepted' && $intent['completed_at'] < $intent['lease_expires_at'] && $intent['completed_at'] <= $proof['verified_at'] && $this->admission->trusted($proof, $challenge, $intent, $identity, $evidence['submitted_at_ms']) && $this->admission->retentionAllowed($evidence['retention_policy_version'], $evidence['submitted_at_ms'], $identity['retention_due_at']), 503, 'Trusted submission evidence unavailable.');
    }

    /** Plaintext stays internal and never enters receipts, exceptions or audits. */
    private function contents(array $evidence, array $identity, array $contact): array
    {
        $binding = fn (string $id, string $purpose, int $version): array => ['namespace' => $this->verification->namespace(), 'merchant' => $evidence['merchant_id'], 'record' => $id, 'purpose' => $purpose, 'version' => $version, 'actor' => $evidence['actor_user_id']];

        $nin = $this->sourceCipher->decrypt('nin', $identity['nin_ciphertext'], $binding($identity['id'], 'identity_declaration', $identity['version']), $identity['encryption_key_ref'], $identity['envelope_revision']);
        $phone = $this->sourceCipher->decrypt('phone', $contact['phone_ciphertext'], $binding($contact['id'], 'application_phone', $contact['version']), $contact['phone_key_ref'], $contact['envelope_revision']);
        abort_unless(hash_equals($contact['target_fingerprint'], hash_hmac('sha256', VerificationCipher::lp('xlipa-contact-fp-v1', $phone), $this->verification->key('contact', $contact['fingerprint_key_ref']))), 503, 'Contact integrity hold.');

        return [$nin, $phone];
    }

    private function receipt(array $evidence, array $expected, int $now): array
    {
        foreach (self::EXPECTATIONS as $field) {
            abort_unless(($evidence[$field] ?? null) === $expected[$field], 409, 'Submission request conflicts.');
        }
        abort_unless($evidence['submitted_at_ms'] <= $now, 503, 'Submission time unavailable.');
        [$identity, $contact, $proof, $challenge, $intent] = $this->lineage($evidence);
        $this->trust($evidence, $identity, $proof, $challenge, $intent);
        $application = DB::table('merchant_applications')->where('id', $evidence['application_id'])->where('merchant_id', $evidence['merchant_id'])->first();
        $audit = DB::table('audit_logs')->where('id', $evidence['audit_event_id'])->first();
        $metadata = json_encode(['from' => 'draft', 'to' => 'pending_review', 'profile_version' => $evidence['profile_version'], 'reason_code' => 'minimum_evidence_bound'], JSON_THROW_ON_ERROR);
        abort_unless($application && in_array($application->status, ['pending_review', 'approved', 'rejected', 'information_requested'], true) && $application->submitted_by_user_id === $evidence['actor_user_id'] && $application->submitted_at === $evidence['submitted_at'] && $application->profile_version === $evidence['profile_version'] && $application->attempt_number === $evidence['attempt_number'] && $application->profile_snapshot === null && in_array($proof['state'], ['consumed', 'revoked'], true) && $proof['consumed_application_id'] === $evidence['application_id'] && $proof['consumed_at'] === $evidence['submitted_at_ms'] && $audit && $audit->event_type === 'application.submitted' && $audit->target_user_id === null && $audit->actor_user_id === $evidence['actor_user_id'] && $audit->merchant_id === $evidence['merchant_id'] && $audit->application_id === $evidence['application_id'] && $audit->request_id === $evidence['submission_request_id'] && $audit->occurred_at === $evidence['submitted_at'] && $audit->metadata === $metadata, 409, 'Submission integrity hold.');
        $context = array_intersect_key($evidence, array_flip(SubmissionEvidenceCipher::FIELDS));
        $payload = $this->cipher->decrypt($context, $evidence['evidence_ciphertext']);
        [$nin, $phone] = $this->contents($evidence, $identity, $contact);
        abort_unless($this->cipher->matchesContents($context, $payload, $nin, $phone), 503, 'Submission integrity hold.');

        return ['application_id' => $evidence['application_id'], 'merchant_id' => $evidence['merchant_id'], 'submission_request_id' => $evidence['submission_request_id'], 'submitted_at' => $evidence['submitted_at'], 'event' => 'application.submitted'];
    }
}
