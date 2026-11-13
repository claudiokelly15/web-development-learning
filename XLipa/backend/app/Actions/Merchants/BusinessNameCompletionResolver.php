<?php

namespace App\Actions\Merchants;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Explicit read/validate only. No command invocation and no historical name commitment. */
final class BusinessNameCompletionResolver
{
    public function __construct(private readonly BusinessNameCompletionContext $context, private readonly SubmissionTransaction $transaction) {}

    public function resolve(User $actor, string $merchant, #[\SensitiveParameter] array $expected, #[\SensitiveParameter] mixed $name): array
    {
        return $this->context->safe(function () use ($actor, $merchant, $expected, $name): array {
            $name = $this->context->input($merchant, $expected, $name);
            $this->context->authorized($actor, $merchant, $expected, true);
            $old = DB::connection();
            abort_unless($old->transactionLevel() === 0 && ! $old->getPdo()->inTransaction(), 503);
            DB::purge($old->getName());
            abort_unless(DB::getDriverName() === 'sqlite', 503);
            DB::statement('PRAGMA recursive_triggers=ON');
            [$initialTime] = $this->context->admit($actor, $merchant, $expected, true);

            return $this->transaction->run(function () use ($actor, $merchant, $expected, $name, $initialTime): array {
                [$time] = $this->context->admit($actor, $merchant, $expected, true);
                abort_unless($time >= $initialTime, 503);
                $business = $this->context->authority($actor, $merchant, $expected, true);
                $record = $this->context->existing($actor, $merchant, $expected);
                $result = ['status' => 'held'];
                if ($record) {
                    $this->context->recordMatches($record, $expected);
                    $this->context->historicalPolicy($record, $time);
                    $result = ['status' => 'historical', 'completion_id' => $record->id];
                    if ($business->profile_version === $record->resulting_profile_version && $business->business_name === $name) {
                        $draft = $this->context->draft($merchant, $expected, $record->resulting_profile_version);
                        if ($business->updated_at === $record->occurred_at && $draft->updated_at === $record->occurred_at) {
                            $result = ['status' => 'confirmed', 'completion_id' => $record->id, 'resulting_profile_version' => $record->resulting_profile_version];
                        }
                    }
                } elseif ($business->business_name === '' && $business->profile_version === $expected['merchant_profile_version'] && $business->profile_version === $expected['draft_profile_version'] && ! DB::table(BusinessNameCompletionSchema::TABLE)->where('merchant_id', $merchant)->exists()) {
                    $this->context->draft($merchant, $expected, $business->profile_version);
                    $result = ['status' => 'absent'];
                }
                [$finalTime] = $this->context->admit($actor, $merchant, $expected, true);
                $final = $this->context->authority($actor, $merchant, $expected, true);
                abort_unless($finalTime >= $time, 503);
                abort_unless((array) $final === (array) $business, 409);
                if (in_array($result['status'], ['confirmed', 'absent'], true)) {
                    $this->context->draft($merchant, $expected, $business->profile_version);
                }

                return $result;
            });
        });
    }
}
