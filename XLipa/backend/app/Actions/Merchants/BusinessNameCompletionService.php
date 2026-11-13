<?php

namespace App\Actions\Merchants;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Internal command only. Duplicate requests require explicit resolution, never replay. */
final class BusinessNameCompletionService
{
    public function __construct(private readonly BusinessNameCompletionContext $context, private readonly SubmissionTransaction $transaction) {}

    public function complete(User $actor, string $merchant, #[\SensitiveParameter] array $expected, #[\SensitiveParameter] mixed $name): array
    {
        return $this->context->safe(function () use ($actor, $merchant, $expected, $name): array {
            $name = $this->context->input($merchant, $expected, $name);
            [$initialTime] = $this->context->admit($actor, $merchant, $expected, false);

            return $this->transaction->run(function () use ($actor, $merchant, $expected, $name, $initialTime): array {
                [$time, $policy] = $this->context->admit($actor, $merchant, $expected, false);
                abort_unless($time >= $initialTime, 503);
                $business = $this->context->authority($actor, $merchant, $expected, false);
                abort_if($this->context->existing($actor, $merchant, $expected) !== null, 409);
                abort_unless($business->profile_version === $expected['merchant_profile_version'] && $business->profile_version === $expected['draft_profile_version'], 409);
                $this->context->draft($merchant, $expected, $business->profile_version);
                abort_if(DB::table(BusinessNameCompletionSchema::TABLE)->where('merchant_id', $merchant)->where('expected_profile_version', $business->profile_version)->exists(), 409);
                if ($business->business_name === $name) {
                    [$finalTime] = $this->context->admit($actor, $merchant, $expected, false);
                    $final = $this->context->authority($actor, $merchant, $expected, false);
                    $this->context->draft($merchant, $expected, $business->profile_version);
                    abort_unless($finalTime >= $time, 503);
                    abort_unless((array) $final === (array) $business, 409);

                    return ['status' => 'unchanged'];
                }
                abort_unless($business->business_name === '' && $business->profile_version < PHP_INT_MAX, 409);
                $record = [
                    'id' => (string) Str::ulid(), 'contract_version' => 'business-name-completion-v1',
                    'merchant_id' => $merchant, 'application_id' => $expected['application_id'], 'attempt_number' => $expected['attempt_number'],
                    'actor_user_id' => $actor->id, 'membership_id' => $expected['membership_id'],
                    'auth_version' => $expected['auth_version'], 'email_binding_version' => $expected['email_binding_version'], 'authority_version' => $expected['authority_version'],
                    'expected_profile_version' => $business->profile_version, 'resulting_profile_version' => $business->profile_version + 1,
                    'completion_request_id' => $expected['completion_request_id'], 'occurred_at_ms' => $time,
                    'occurred_at' => VerificationAuditReconciler::occurredAt($time), 'record_policy_version' => $policy,
                ];
                DB::table(BusinessNameCompletionSchema::TABLE)->insert($record);
                abort_unless(DB::table('merchants')->where('id', $merchant)->where('business_name', '')->where('profile_version', $business->profile_version)->update(['business_name' => $name, 'profile_version' => $record['resulting_profile_version'], 'updated_at' => $record['occurred_at']]) === 1, 409);
                [$finalTime] = $this->context->admit($actor, $merchant, $expected, false);
                $final = $this->context->authority($actor, $merchant, $expected, false);
                $draft = $this->context->draft($merchant, $expected, $record['resulting_profile_version']);
                $stored = $this->context->existing($actor, $merchant, $expected);
                abort_unless($stored && (array) $stored === $record && $finalTime >= $time && $final->business_name === $name && $final->profile_version === $record['resulting_profile_version'] && $final->updated_at === $record['occurred_at'] && $draft->updated_at === $record['occurred_at'], 409);
                $this->context->recordMatches($stored, $expected);
                $this->context->historicalPolicy($stored, $finalTime);

                return ['status' => 'completed', 'completion_id' => $record['id'], 'resulting_profile_version' => $record['resulting_profile_version']];
            });
        });
    }
}
