<?php

namespace App\Actions\Merchants;

use App\Models\Merchant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** Submission guard over accepted contracts. No evidence schema or live authority is invented. */
final class MerchantApplicationSubmission
{
    public const EVIDENCE_BLOCKER = 'Immutable submission evidence bindings are awaiting an approved implementation contract.';

    public function __construct(
        private readonly VerificationEnvironment $environment,
        private readonly VerificationReadiness $readiness,
        private readonly VerificationStore $store,
    ) {}

    /** @return list<string> Safe reasons only; never exposes stored evidence. */
    public function blockers(User $user, Merchant $merchant): array
    {
        Gate::forUser($user)->authorize('manageVerification', $merchant);
        $reasons = [self::EVIDENCE_BLOCKER];
        $account = DB::table('users')->where('id', $user->id)->first(['auth_version', 'email', 'email_verified_at']);
        if (! $account || $account->auth_version !== $user->auth_version || ! $account->email_verified_at || ! filter_var($account->email, FILTER_VALIDATE_EMAIL)) {
            $reasons[] = 'Current verified account contact is required.';
        }
        $current = DB::table('merchants')->where('id', $merchant->id)->first(['business_name', 'operational_status']);
        if (! $current || in_array($current->operational_status, ['suspended', 'closed'], true) || trim($current->business_name) === '') {
            $reasons[] = 'An available merchant with a business name is required.';
        }
        if (! DB::table('merchant_applications')->where('merchant_id', $merchant->id)->where('current_slot', 1)->where('status', 'draft')->exists()) {
            $reasons[] = 'A current draft application is required.';
        }
        if (! $this->readiness->ready()) {
            return [...$reasons, 'Private verification services are unavailable.'];
        }
        $context = DB::table('merchant_onboarding_contexts')->where('merchant_id', $merchant->id)->first();
        $now = $this->environment->milliseconds();
        $lastObserved = DB::table('verification_limit_state')->where('merchant_id', $merchant->id)->max('last_observed_at');
        if ($now < max((int) ($context?->updated_at ?? 0), (int) $lastObserved)) {
            $reasons[] = 'Verification time requires operational review.';
        }
        if (DB::table('verification_limit_state')->where('merchant_id', $merchant->id)->whereNotNull('pending_audit_event_id')->exists()) {
            $reasons[] = 'Verification is held for audit reconciliation.';
        }
        $policy = $this->environment->identityPolicy();
        $identity = $policy && $context ? DB::table('merchant_identity_records')->where('id', $context->current_identity_id)
            ->where('merchant_id', $merchant->id)->where('declared_by_user_id', $user->id)->where('state', 'current')
            ->where('syntax_policy_version', $policy['syntax'])->whereNotNull('syntax_checked_at')
            ->where('declaration_version', $policy['declaration'])->where('notice_version', $policy['notice'])->exists() : false;
        if (! $identity) {
            $reasons[] = 'A current declaration under the approved identity policy is required.';
        }
        $contact = $context && $context->contact_actor_user_id === $user->id ? DB::table('merchant_contact_versions')
            ->where('id', $context->current_contact_id)->where('merchant_id', $merchant->id)->where('actor_user_id', $user->id)
            ->where('purpose', 'application_phone')->where('state', 'current')->first(['id']) : null;
        $proof = $contact ? DB::table('contact_verification_proofs')->where('merchant_id', $merchant->id)->where('actor_user_id', $user->id)
            ->where('contact_id', $contact->id)->where('purpose', 'application_phone')->where('policy_version', 'application-phone-proof-v1')
            ->where('state', 'available')->whereNull('revoked_at')->whereNull('consumed_at')->where('verified_at', '<=', $now)->where('expires_at', '>', $now)->first(['provenance']) : null;
        if (! $proof) {
            $reasons[] = 'A current phone possession proof is required.';
        } elseif ($proof->provenance !== 'live' || $this->environment->mode() !== 'live') {
            $reasons[] = 'A current live phone possession proof is required. Simulated proofs cannot authorize submission.';
        }

        return $reasons;
    }

    /** No mutation until frozen email/identity/contact/proof evidence and consumption contracts exist. */
    public function submit(User $user, Merchant $merchant, string $applicationId, int $profileVersion, int $contextVersion): never
    {
        Gate::forUser($user)->authorize('manageVerification', $merchant);
        abort_unless($this->readiness->ready(), 503, 'Application submission is unavailable.');
        $this->store->run(function () use ($user, $merchant, $applicationId, $profileVersion, $contextVersion): void {
            Gate::forUser($user)->authorize('manageVerification', $merchant);
            abort_unless($this->readiness->ready(), 503, 'Application submission is unavailable.');
            $application = DB::table('merchant_applications')->where('merchant_id', $merchant->id)->where('current_slot', 1)->first(['id', 'profile_version', 'status']);
            $context = DB::table('merchant_onboarding_contexts')->where('merchant_id', $merchant->id)->first(['context_version']);
            abort_unless($application && $application->id === $applicationId && $application->status === 'draft'
                && $application->profile_version === $profileVersion && (int) ($context?->context_version ?? 0) === $contextVersion, 409, 'The application changed. Reload before trying again.');
            $this->blockers($user, $merchant);
            abort(503, 'Application submission is unavailable pending approved immutable evidence bindings. No application was submitted.');
        });
        abort(503, 'Application submission is unavailable.');
    }
}
