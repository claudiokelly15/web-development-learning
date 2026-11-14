<?php

namespace App\Actions\Merchants;

use App\Actions\Auth\ResolvePlatformRole;
use App\Enums\PlatformRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Decision authorization preflight. Mutation requires the composed decision contract. */
final class ApplicationDecisionService
{
    public function __construct(
        private readonly ResolvePlatformRole $roles,
        private readonly WorkflowSchemaReadiness $readiness,
        private readonly SubmissionTransaction $transaction,
    ) {}

    /** No decision receipt or eligibility claim is returned while the schema/admission hold applies. */
    public function decide(User $actor, string $merchant, string $application, array $expected, string $outcome, ?string $reason = null): never
    {
        abort_unless(in_array($outcome, ['approved', 'rejected'], true), 422, 'Invalid application decision.');
        abort_unless($outcome === 'approved' ? $reason === null : is_string($reason) && preg_match('/\A[a-z][a-z0-9_]{0,49}\z/D', $reason) === 1, 422, 'Invalid application decision.');
        foreach ([$merchant, $application] as $identifier) {
            abort_unless(preg_match('/\A[0-7][0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{25}\z/D', $identifier) === 1, 422, 'Invalid application decision.');
        }
        $fields = ['attempt_number', 'profile_version', 'reviewer_auth_version', 'submission_request_id'];
        abort_unless(count($expected) === count($fields) && ! array_diff(array_keys($expected), $fields), 422, 'Invalid application decision.');
        foreach (['attempt_number', 'profile_version', 'reviewer_auth_version'] as $field) {
            abort_unless(is_int($expected[$field]) && $expected[$field] > 0, 422, 'Invalid application decision.');
        }
        abort_unless(is_string($expected['submission_request_id']) && preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/D', $expected['submission_request_id']) === 1, 422, 'Invalid application decision.');
        abort_unless(auth()->id() === $actor->id, 403, 'Application decision access denied.');
        $this->readiness->profile();
        $this->transaction->run(function () use ($actor, $merchant, $application, $expected): never {
            abort_unless($this->readiness->profile() === WorkflowSchemaReadiness::BNC, 503, 'Application decision is unavailable.');
            $current = User::query()->find($actor->id);
            abort_unless($current && $current->account_status === 'active' && $current->email_verified_at !== null
                && $current->auth_version === $actor->auth_version && $current->auth_version === $expected['reviewer_auth_version']
                && session('auth_version') === $current->auth_version && auth()->id() === $current->id
                && auth()->user()->auth_version === $current->auth_version
                && $this->roles->execute($current) === PlatformRole::ChiefAdmin, 403, 'Application decision access denied.');
            $row = DB::table('merchant_applications')->where('id', $application)->where('merchant_id', $merchant)->first();
            $business = DB::table('merchants')->where('id', $merchant)->first();
            abort_unless($row && $business && $business->created_by_user_id !== $current->id && $row->submitted_by_user_id !== $current->id
                && ! DB::table('merchant_memberships')->where('merchant_id', $merchant)->where('user_id', $current->id)->exists(), 404, 'Application unavailable.');
            abort_unless($row->status === 'pending_review' && (string) $row->current_slot === '1' && $row->attempt_number === $expected['attempt_number']
                && $row->profile_version === $expected['profile_version'] && $row->reviewed_at === null && $row->reviewed_by_user_id === null, 409, 'Application changed.');
            abort_if(DB::table('audit_logs')->where('merchant_id', $merchant)->where('application_id', $application)->where('event_type', 'application.reviewed')->exists(), 409, 'Application changed.');
            $evidence = DB::table(SubmissionEvidenceSchema::TABLE)->where('application_id', $application)->where('merchant_id', $merchant)
                ->where('submission_request_id', $expected['submission_request_id'])->first();
            abort_unless($evidence && $evidence->attempt_number === $row->attempt_number && $evidence->profile_version === $row->profile_version
                && $evidence->actor_user_id === $row->submitted_by_user_id && $evidence->submitted_at === $row->submitted_at, 409, 'Application changed.');

            // Frozen catalogue has no immutable decision bundle or reviewer-authority history.
            abort(503, 'Application decision is unavailable.');
        });
        abort(503, 'Application decision is unavailable.');
    }
}
