<?php

namespace App\Actions\Merchants;

use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/** Shared internal validation, with no HTTP parsing or operational trust implementation. */
final class BusinessNameCompletionContext
{
    public const EXPECTATIONS = ['application_id', 'attempt_number', 'merchant_profile_version', 'draft_profile_version', 'membership_id', 'authority_version', 'auth_version', 'email_binding_version', 'completion_request_id'];

    private const SOURCES = [
        'app/Actions/Merchants/BusinessNameCompletionAdmission.php',
        'app/Actions/Merchants/DeniedBusinessNameCompletionAdmission.php',
        'app/Actions/Merchants/BusinessNameCompletionContext.php',
        'app/Actions/Merchants/BusinessNameCompletionService.php',
        'app/Actions/Merchants/BusinessNameCompletionResolver.php',
        'app/Actions/Merchants/BusinessNameCompletionOutcomeUnknown.php',
        'app/Providers/AppServiceProvider.php',
    ];

    public function __construct(private readonly BusinessNameCompletionAdmission $admission) {}

    public static function manifestPath(): string
    {
        return database_path('activation/bnc1/backend-manifest.json');
    }

    /** Release preparation only; admission never writes this manifest. */
    public static function releaseDefinition(): array
    {
        $pins = [];
        foreach (self::SOURCES as $path) {
            $pins[$path] = hash_file('sha256', base_path($path));
        }

        return ['version' => 'BNC-INT-02', 'mode' => 'isolated-only', 'integration_manifest_sha256' => 'f6e4c09840b5943268a21b2b6e33eb38d74a32e56abae0b4a5ef25a7ce97e47a', 'source_pins' => $pins];
    }

    public function input(string $merchant, #[\SensitiveParameter] array $expected, #[\SensitiveParameter] mixed $name): string
    {
        abort_unless(count($expected) === count(self::EXPECTATIONS) && ! array_diff(array_keys($expected), self::EXPECTATIONS), 422);
        foreach (['merchant_id' => $merchant, ...$expected] as $field => $value) {
            $valid = match ($field) {
                'merchant_id', 'application_id' => is_string($value) && preg_match('/^[0-7][0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{25}$/D', $value),
                'completion_request_id' => is_string($value) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/D', $value),
                default => is_int($value) && $value > 0,
            };
            abort_unless($valid, 422);
        }
        abort_unless(is_string($name) && mb_check_encoding($name, 'UTF-8') && ! preg_match('/[\p{Cc}\p{Cf}\p{Zl}\p{Zp}]/u', $name), 422);
        $name = trim($name, ' ');
        abort_unless($name !== '' && mb_strlen($name, 'UTF-8') <= 200 && ! preg_match('/^\p{Z}|\p{Z}$/u', $name), 422);

        return $name;
    }

    public function safe(Closure $operation): array
    {
        try {
            return $operation();
        } catch (SubmissionOutcomeUnknown) {
            throw new BusinessNameCompletionOutcomeUnknown;
        } catch (HttpExceptionInterface $error) {
            abort($error->getStatusCode(), 'Business name completion could not be accepted.');
        } catch (Throwable) {
            abort(503, 'Business name completion is unavailable.');
        }
    }

    /** Independent gate before database access, including resolver reconnection. */
    public function authorized(User $actor, string $merchant, array $expected, bool $resolving): void
    {
        abort_unless(auth()->id() === $actor->id, 403);
        abort_unless($this->admission->expectedProfile() === WorkflowSchemaReadiness::BNC && $this->admission->authorized($actor->id, $merchant, $expected, $resolving), 503);
    }

    /** Never invoked in the deliberate record-first/deferred-witness gap. */
    public function admit(User $actor, string $merchant, array $expected, bool $resolving): array
    {
        $this->authorized($actor, $merchant, $expected, $resolving);
        $raw = file_get_contents(self::manifestPath());
        $manifest = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        abort_unless($manifest === self::releaseDefinition() && $raw === json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n" && hash_equals($manifest['integration_manifest_sha256'], hash_file('sha256', WorkflowSchemaReadiness::manifestPath())), 503);
        abort_unless(app(WorkflowSchemaReadiness::class)->profile() === WorkflowSchemaReadiness::BNC, 503);
        abort_unless($this->admission->authoritative(DB::connection(), $actor->id, $merchant, $expected, $resolving) && $this->admission->reconciliationReady($actor->id, $merchant), 503);
        $time = $this->admission->milliseconds();
        $policy = $this->admission->recordPolicy();
        abort_unless($time >= 0 && $time <= 253402300799999 && preg_match('/^[a-z0-9._-]{1,50}$/D', $policy) && $this->admission->retentionAllowed($policy, $time), 503);
        abort_if(DB::table('verification_limit_state')->where('merchant_id', $merchant)->where(function ($query) use ($time): void {
            $query->whereNotNull('pending_audit_event_id')->orWhere('last_observed_at', '>', $time);
        })->exists(), 409);

        return [$time, $policy];
    }

    /** Resolution validates current session independently from immutable historical epochs. */
    public function authority(User $actor, string $merchant, array $expected, bool $resolving): object
    {
        $user = DB::table('users')->where('id', $actor->id)->first();
        $authenticated = auth()->user();
        abort_unless($authenticated && $user && auth()->id() === $actor->id && $user->account_status === 'active' && $user->email_verified_at !== null && $user->auth_version === $actor->auth_version && $user->auth_version === $authenticated->auth_version && session('auth_version') === $user->auth_version && $user->email === $actor->email && $user->email === $authenticated->email && $user->email_binding_version === $actor->email_binding_version && $user->email_binding_version === $authenticated->email_binding_version, 403);
        $membership = DB::table('merchant_memberships')->where('id', $expected['membership_id'])->where('merchant_id', $merchant)->where('user_id', $actor->id)->where('role', 'owner')->where('status', 'active')->whereNull('revoked_at')->first();
        abort_unless($membership, 403);
        if (! $resolving) {
            abort_unless($user->auth_version === $expected['auth_version'] && $user->email_binding_version === $expected['email_binding_version'] && $membership->authority_version === $expected['authority_version'], 403);
        }
        $business = DB::table('merchants')->where('id', $merchant)->first();
        abort_unless($business, 404);
        abort_if(in_array($business->operational_status, ['suspended', 'closed'], true), 409);

        return $business;
    }

    public function draft(string $merchant, array $expected, int $version): object
    {
        $draft = DB::table('merchant_applications')->where('id', $expected['application_id'])->where('merchant_id', $merchant)->first();
        abort_unless($draft && $draft->attempt_number === $expected['attempt_number'] && (string) $draft->current_slot === '1' && $draft->status === 'draft' && $draft->profile_version === $version, 409);
        foreach (['profile_snapshot', 'submitted_at', 'submitted_by_user_id', 'reviewed_at', 'reviewed_by_user_id', 'public_feedback', 'internal_review_notes'] as $field) {
            abort_unless($draft->$field === null, 409);
        }
        abort_if(DB::table(SubmissionEvidenceSchema::TABLE)->where('application_id', $draft->id)->exists() || DB::table('audit_logs')->where('application_id', $draft->id)->where('event_type', 'application.submitted')->exists(), 409);

        return $draft;
    }

    public function existing(User $actor, string $merchant, array $expected): ?object
    {
        return DB::table(BusinessNameCompletionSchema::TABLE)->where('merchant_id', $merchant)->where('actor_user_id', $actor->id)->where('completion_request_id', $expected['completion_request_id'])->first();
    }

    public function recordMatches(object $record, array $expected): void
    {
        foreach (['application_id', 'attempt_number', 'membership_id', 'authority_version', 'auth_version', 'email_binding_version', 'completion_request_id'] as $field) {
            abort_unless($expected[$field] === $record->$field, 409);
        }
        abort_unless($record->contract_version === 'business-name-completion-v1' && $record->expected_profile_version === $expected['merchant_profile_version'] && $record->expected_profile_version === $expected['draft_profile_version'] && $record->resulting_profile_version === $record->expected_profile_version + 1 && DB::table(BusinessNameCompletionSchema::WITNESSES)->where('completion_id', $record->id)->exists(), 409);
    }

    public function historicalPolicy(object $record, int $time): void
    {
        abort_unless($record->occurred_at_ms <= $time && $record->occurred_at === VerificationAuditReconciler::occurredAt($record->occurred_at_ms) && $this->admission->retentionAllowed($record->record_policy_version, $record->occurred_at_ms), 503);
    }
}
