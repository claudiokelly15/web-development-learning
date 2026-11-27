<?php

namespace App\Actions\Merchants;

use App\Actions\Auth\ResolvePlatformRole;
use App\Enums\PlatformRole;
use App\Models\User;
use App\Staging\StagingWorkflowAuthority;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/** Internal decision writer. Existing append-only application.reviewed audit is authoritative. */
final class ApplicationDecisionService
{
    public function __construct(
        private readonly ResolvePlatformRole $roles,
        private readonly WorkflowSchemaReadiness $readiness,
        private readonly SubmissionTransaction $transaction,
        private readonly ApplicationDecisionAdmission $admission,
        private readonly SubmissionAdmission $issuance,
        private readonly SubmissionEvidenceEnvironment $custody,
        private readonly VerificationEnvironment $verification,
        private readonly SubmissionEvidenceCipher $cipher,
        private readonly VerificationCipher $sourceCipher,
    ) {}

    public function decide(User $actor, string $merchant, string $application, array $expected, string $outcome, ?string $reason = null): array
    {
        abort_unless(in_array($outcome, ['approved', 'rejected'], true), 422, 'Invalid application decision.');
        abort_unless($outcome === 'approved' ? $reason === null : is_string($reason) && preg_match('/\A[a-z][a-z0-9_]{0,49}\z/D', $reason) === 1, 422, 'Invalid application decision.');
        foreach ([$merchant, $application] as $identifier) {
            abort_unless(preg_match('/\A[0-7][0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{25}\z/D', $identifier) === 1, 422, 'Invalid application decision.');
        }
        $fields = ['attempt_number', 'profile_version', 'reviewer_auth_version', 'reviewer_email_binding_version', 'reviewer_authority_version', 'submission_request_id', 'decision_request_id'];
        abort_unless(count($expected) === count($fields) && ! array_diff(array_keys($expected), $fields), 422, 'Invalid application decision.');
        foreach (['attempt_number', 'profile_version', 'reviewer_auth_version', 'reviewer_email_binding_version', 'reviewer_authority_version'] as $field) {
            abort_unless(is_int($expected[$field]) && $expected[$field] > 0, 422, 'Invalid application decision.');
        }
        foreach (['submission_request_id', 'decision_request_id'] as $field) {
            abort_unless(is_string($expected[$field]) && preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/D', $expected[$field]) === 1, 422, 'Invalid application decision.');
        }
        abort_unless(auth()->id() === $actor->id, 403, 'Application decision access denied.');
        try {
            $initialTime = $this->admit($actor, $merchant, $application, $expected, $outcome, $reason);
            $this->readiness->profile();

            return $this->transaction->run(function () use ($actor, $merchant, $application, $expected, $outcome, $reason, $initialTime): array {
                abort_unless($this->readiness->profile() === WorkflowSchemaReadiness::BNC, 503, 'Application decision is unavailable.');
                $time = $this->admit($actor, $merchant, $application, $expected, $outcome, $reason);
                abort_unless($time >= $initialTime, 503);
                $this->authority($actor, $merchant, $application, $expected);
                $row = DB::table('merchant_applications')->where('id', $application)->where('merchant_id', $merchant)->first();
                abort_unless($row->status === 'pending_review' && (string) $row->current_slot === '1' && $row->attempt_number === $expected['attempt_number']
                    && $row->profile_version === $expected['profile_version'] && $row->reviewed_at === null && $row->reviewed_by_user_id === null, 409, 'Application changed.');
                abort_if(DB::table('audit_logs')->where('merchant_id', $merchant)->where('application_id', $application)->where('event_type', 'application.reviewed')->exists(), 409, 'Application changed.');
                $evidence = DB::table(SubmissionEvidenceSchema::TABLE)->where('application_id', $application)->where('merchant_id', $merchant)
                    ->where('submission_request_id', $expected['submission_request_id'])->first();
                abort_unless($evidence && $evidence->attempt_number === $row->attempt_number && $evidence->profile_version === $row->profile_version
                    && $evidence->actor_user_id === $row->submitted_by_user_id && $evidence->submitted_at === $row->submitted_at, 409, 'Application changed.');

                $evidence = (array) $evidence;
                $this->evidence($evidence, $row, $time);
                abort_if(DB::table('audit_logs')->where('actor_user_id', $actor->id)->where('event_type', 'application.reviewed')->where('request_id', $expected['decision_request_id'])->exists(), 409);
                $event = ['id' => (string) Str::ulid(), 'event_type' => 'application.reviewed', 'actor_user_id' => $actor->id,
                    'target_user_id' => null, 'merchant_id' => $merchant, 'application_id' => $application,
                    'request_id' => $expected['decision_request_id'], 'metadata' => json_encode(['from' => 'pending_review', 'to' => $outcome,
                        'profile_version' => $row->profile_version, 'reason_code' => $reason ?? 'requirements_satisfied'], JSON_THROW_ON_ERROR),
                    'occurred_at' => VerificationAuditReconciler::occurredAt($time)];
                DB::table('audit_logs')->insert($event);
                abort_unless(DB::table('merchant_applications')->where('id', $application)->where('merchant_id', $merchant)->where('status', 'pending_review')
                    ->where('attempt_number', $expected['attempt_number'])->where('profile_version', $expected['profile_version'])->whereNull('reviewed_at')->whereNull('reviewed_by_user_id')
                    ->update(['status' => $outcome, 'reviewed_by_user_id' => $actor->id, 'reviewed_at' => $event['occurred_at']]) === 1, 409);
                $finalTime = $this->admit($actor, $merchant, $application, $expected, $outcome, $reason);
                $this->authority($actor, $merchant, $application, $expected);
                $final = DB::table('merchant_applications')->where('id', $application)->sole();
                $this->evidence($evidence, $final, $finalTime);
                abort_unless($this->readiness->profile() === WorkflowSchemaReadiness::BNC, 503);
                abort_unless($finalTime >= $time && $final->status === $outcome && $final->reviewed_by_user_id === $actor->id && $final->reviewed_at === $event['occurred_at']
                    && DB::table('audit_logs')->where('merchant_id', $merchant)->where('application_id', $application)->where('event_type', 'application.reviewed')->count() === 1
                    && (array) DB::table('audit_logs')->where('id', $event['id'])->sole() === $event
                    && (array) DB::table(SubmissionEvidenceSchema::TABLE)->where('application_id', $application)->sole() === $evidence, 409);

                return ['status' => $outcome, 'application_id' => $application, 'decision_id' => $event['id']];
            });
        } catch (SubmissionOutcomeUnknown $error) {
            throw $error;
        } catch (HttpExceptionInterface $error) {
            abort($error->getStatusCode(), 'Application decision could not be accepted.');
        } catch (Throwable) {
            abort(503, 'Application decision is unavailable.');
        }
    }

    private function admit(User $actor, string $merchant, string $application, array $expected, string $outcome, ?string $reason): int
    {
        abort_unless($this->admission->authorized($actor->id, $merchant, $application, $expected, $outcome, $reason)
            && $this->admission->authorityVersion($actor->id) === $expected['reviewer_authority_version'], 503);
        $time = $this->admission->milliseconds();
        abort_unless($time >= 0 && $time <= 253402300799999, 503);

        return $time;
    }

    private function authority(User $actor, string $merchant, string $application, array $expected): void
    {
        $current = User::query()->find($actor->id);
        abort_unless($current && $current->getRawOriginal('account_status') === 'active' && $current->email_verified_at !== null
            && $current->auth_version === $actor->auth_version && $current->auth_version === $expected['reviewer_auth_version']
            && session('auth_version') === $current->auth_version && auth()->id() === $current->id
            && auth()->user()->auth_version === $current->auth_version && $current->email === $actor->email
            && $current->email_binding_version === $actor->email_binding_version && $current->email_binding_version === $expected['reviewer_email_binding_version']
            && $this->roles->execute($current) === PlatformRole::ChiefAdmin, 403);
        $row = DB::table('merchant_applications')->where('id', $application)->where('merchant_id', $merchant)->first();
        $business = DB::table('merchants')->where('id', $merchant)->first();
        abort_unless($row && $business && $business->created_by_user_id !== $current->id && $row->submitted_by_user_id !== $current->id
            && ! DB::table('merchant_memberships')->where('merchant_id', $merchant)->where('user_id', $current->id)->exists(), 404);
    }

    /** Validate historical capsule and current eligibility without impersonating its submitting owner. */
    private function evidence(array $record, object $application, int $time): void
    {
        abort_unless($this->issuance->restoreReady() && $this->custody->authorized() && $this->custody->restoreReady()
            && $this->verification->authorized() && ($this->verification->mode() === 'live' || StagingWorkflowAuthority::modeAllowed($this->verification->mode())) && $this->custody->namespace() === $this->verification->namespace()
            && $this->custody->milliseconds() >= $time && $this->verification->milliseconds() >= $time && $time >= $record['submitted_at_ms'], 503);
        $owner = DB::table('users')->where('id', $record['actor_user_id'])->sole();
        $business = DB::table('merchants')->where('id', $record['merchant_id'])->sole();
        $membership = DB::table('merchant_memberships')->where('id', $record['membership_id'])->sole();
        $context = DB::table('merchant_onboarding_contexts')->where('merchant_id', $record['merchant_id'])->sole();
        $identity = (array) DB::table('merchant_identity_records')->where('id', $record['identity_id'])->sole();
        $contact = (array) DB::table('merchant_contact_versions')->where('id', $record['contact_id'])->sole();
        $proof = (array) DB::table('contact_verification_proofs')->where('id', $record['proof_id'])->sole();
        $challenge = (array) DB::table('phone_otp_challenges')->where('id', $proof['challenge_id'])->sole();
        $intent = (array) DB::table('otp_delivery_intents')->where('challenge_id', $challenge['id'])->sole();
        $policy = $this->verification->identityPolicy();
        abort_unless($policy && $identity['syntax_policy_version'] === $policy['syntax'] && $identity['declaration_version'] === $policy['declaration']
            && $identity['notice_version'] === $policy['notice'] && $identity['syntax_checked_at'] !== null, 503);
        abort_unless($owner->account_status === 'active' && $owner->email_verified_at !== null && $owner->auth_version === $record['auth_version']
            && $owner->email_binding_version === $record['email_binding_version'] && $membership->merchant_id === $business->id && $membership->user_id === $owner->id
            && $membership->role === 'owner' && $membership->status === 'active' && $membership->revoked_at === null && $membership->authority_version === $record['authority_version']
            && ! in_array($business->operational_status, ['suspended', 'closed'], true) && $business->profile_version === $record['profile_version']
            && $context->context_version === $record['context_version'] && $context->current_identity_id === $record['identity_id'] && $context->current_contact_id === $record['contact_id']
            && $context->contact_actor_user_id === $owner->id && $context->contact_purpose === $record['purpose'] && $context->updated_at <= $time
            && $identity['state'] === 'current' && $contact['state'] === 'current' && $proof['state'] === 'consumed' && $proof['revoked_at'] === null
            && $proof['consumed_application_id'] === $application->id && $proof['consumed_at'] === $record['submitted_at_ms'] && $proof['verified_at'] <= $record['submitted_at_ms']
            && $proof['expires_at'] > $record['submitted_at_ms'] && ($proof['provenance'] === 'live' || StagingWorkflowAuthority::provenance($proof, $challenge, $intent)) && $proof['channel'] === 'sms' && $proof['policy_version'] === 'application-phone-proof-v1'
            && $challenge['state'] === 'consumed' && ($challenge['transport_mode'] === 'live' || StagingWorkflowAuthority::provenance($proof, $challenge, $intent)) && $challenge['terminal_at'] === $proof['verified_at']
            && $intent['state'] === 'accepted' && ($intent['transport_mode'] === 'live' || StagingWorkflowAuthority::provenance($proof, $challenge, $intent)) && $intent['completed_at'] < $intent['lease_expires_at'] && $intent['completed_at'] <= $proof['verified_at'], 409);
        abort_if(DB::table('verification_limit_state')->where('merchant_id', $business->id)->where(function ($query) use ($time): void {
            $query->whereNotNull('pending_audit_event_id')->orWhere('last_observed_at', '>', $time);
        })->exists(), 409);
        abort_unless($this->issuance->trusted($proof, $challenge, $intent, $identity, $time)
            && $this->issuance->retentionAllowed($record['retention_policy_version'], $time, $identity['retention_due_at']), 503);
        $audit = DB::table('audit_logs')->where('id', $record['audit_event_id'])->sole();
        $metadata = json_encode(['from' => 'draft', 'to' => 'pending_review', 'profile_version' => $record['profile_version'], 'reason_code' => 'minimum_evidence_bound'], JSON_THROW_ON_ERROR);
        abort_unless($audit->event_type === 'application.submitted' && $audit->actor_user_id === $owner->id && $audit->target_user_id === null && $audit->merchant_id === $business->id
            && $audit->application_id === $application->id && $audit->request_id === $record['submission_request_id'] && $audit->occurred_at === $record['submitted_at'] && $audit->metadata === $metadata, 409);
        $cipherContext = array_intersect_key($record, array_flip(SubmissionEvidenceCipher::FIELDS));
        $payload = $this->cipher->decrypt($cipherContext, $record['evidence_ciphertext']);
        $binding = fn (string $id, string $purpose, int $version): array => ['namespace' => $this->verification->namespace(), 'merchant' => $business->id, 'record' => $id, 'purpose' => $purpose, 'version' => $version, 'actor' => $owner->id];
        $nin = $this->sourceCipher->decrypt('nin', $identity['nin_ciphertext'], $binding($identity['id'], 'identity_declaration', $identity['version']), $identity['encryption_key_ref'], $identity['envelope_revision']);
        $phone = $this->sourceCipher->decrypt('phone', $contact['phone_ciphertext'], $binding($contact['id'], $record['purpose'], $contact['version']), $contact['phone_key_ref'], $contact['envelope_revision']);
        abort_unless($payload['email'] === $owner->email && $payload['email_verified_at'] === CarbonImmutable::parse($owner->email_verified_at, 'UTC')->format('Y-m-d H:i:s.v')
            && $payload['business_name'] === $business->business_name && $this->cipher->matchesContents($cipherContext, $payload, $nin, $phone)
            && hash_equals($contact['target_fingerprint'], hash_hmac('sha256', VerificationCipher::lp('xlipa-contact-fp-v1', $phone), $this->verification->key('contact', $contact['fingerprint_key_ref']))), 503);
    }
}
