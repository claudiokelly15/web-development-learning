<?php

namespace Tests\Feature;

use App\Actions\Merchants\ApplicationSubmissionService;
use App\Actions\Merchants\DeniedSubmissionAdmission;
use App\Actions\Merchants\SubmissionAdmission;
use App\Actions\Merchants\SubmissionEvidenceEnvironment;
use App\Actions\Merchants\SubmissionEvidenceSchema as SE;
use App\Actions\Merchants\SubmissionOutcomeUnknown;
use App\Actions\Merchants\SubmissionTransaction;
use App\Actions\Merchants\VerificationAuditReconciler;
use App\Actions\Merchants\VerificationCipher;
use App\Actions\Merchants\VerificationEnvironment;
use App\Models\Merchant;
use App\Models\User;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\Support\B32CRehearsal;
use Tests\Support\FakeSubmissionAdmission;
use Tests\Support\FakeSubmissionEvidenceEnvironment;
use Tests\Support\FakeSubmissionVerificationEnvironment;
use Tests\TestCase;

/** Every successful bundle here is fictional, with test-only issuance and custody. */
class ApplicationSubmissionServiceTest extends TestCase
{
    private array $evidence;

    private User $actor;

    private array $expected;

    private FakeSubmissionAdmission $admission;

    private FakeSubmissionEvidenceEnvironment $custody;

    private FakeSubmissionVerificationEnvironment $verification;

    private int $time = 1794096000000;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admission = new FakeSubmissionAdmission;
        $this->custody = new FakeSubmissionEvidenceEnvironment;
        $this->verification = new FakeSubmissionVerificationEnvironment;
        $this->app->instance(SubmissionAdmission::class, $this->admission);
        $this->app->instance(SubmissionEvidenceEnvironment::class, $this->custody);
        $this->app->instance(VerificationEnvironment::class, $this->verification);
        $this->fixture();
    }

    private function fixture(string $transport = 'live', ?int $retentionDue = null): void
    {
        B32CRehearsal::foundation();
        B32CRehearsal::activate();
        Notification::fake();
        DB::statement('PRAGMA recursive_triggers=ON');
        $user = User::factory()->create(['email_verified_at' => '2026-11-07 00:00:00', 'email' => 'owner@fixture.test']);
        $merchant = Merchant::factory()->create(['created_by_user_id' => $user->id]);
        $application = $merchant->applications()->where('current_slot', 1)->firstOrFail();
        $identity = (string) Str::ulid();
        $contact = (string) Str::ulid();
        $challenge = (string) Str::ulid();
        $proof = (string) Str::ulid();
        DB::table('merchant_identity_records')->insert(['id' => $identity, 'merchant_id' => $merchant->id, 'version' => 1, 'subject_claim' => 'individual_owner', 'nin_ciphertext' => $this->envelope('nin', 'FIXTURE-ABCDEFGH', $merchant->id, $identity, $user->id), 'encryption_key_ref' => 'nin.v1', 'envelope_version' => 1, 'syntax_policy_version' => 'fixture.syntax', 'syntax_checked_at' => $this->time, 'declaration_version' => 'fixture.declaration', 'notice_version' => 'fixture.notice', 'declared_by_user_id' => $user->id, 'declared_at' => $this->time, 'retention_due_at' => $retentionDue]);
        DB::table('merchant_contact_versions')->insert(['id' => $contact, 'merchant_id' => $merchant->id, 'actor_user_id' => $user->id, 'purpose' => 'application_phone', 'version' => 1, 'phone_ciphertext' => $this->envelope('phone', '+255712345678', $merchant->id, $contact, $user->id), 'phone_key_ref' => 'phone.v1', 'envelope_version' => 1, 'target_fingerprint' => hash_hmac('sha256', VerificationCipher::lp('xlipa-contact-fp-v1', '+255712345678'), $this->verification->keys['contact']), 'fingerprint_key_ref' => 'contact.v1', 'created_at' => $this->time]);
        DB::table('merchant_onboarding_contexts')->insert(['merchant_id' => $merchant->id, 'current_identity_id' => $identity, 'current_contact_id' => $contact, 'contact_actor_user_id' => $user->id, 'contact_purpose' => 'application_phone', 'updated_at' => $this->time]);
        $base = ['merchant_id' => $merchant->id, 'actor_user_id' => $user->id, 'contact_id' => $contact, 'purpose' => 'application_phone'];
        DB::table('phone_otp_challenges')->insert(['id' => $challenge, ...$base, 'active_slot' => 1, 'created_at' => $this->time, 'expires_at' => $this->time + 300000, 'transport_mode' => $transport]);
        DB::table('phone_otp_challenges')->where('id', $challenge)->update(['state' => 'dispatching', 'state_version' => 2, 'code_digest' => str_repeat('b', 64), 'digest_key_ref' => 'fixture.otp', 'digest_created_at' => $this->time]);
        DB::table('otp_delivery_intents')->insert(['id' => (string) Str::ulid(), ...$base, 'challenge_id' => $challenge, 'transport_mode' => $transport, 'request_id' => (string) Str::uuid(), 'requested_at' => $this->time]);
        DB::table('otp_delivery_intents')->where('challenge_id', $challenge)->update(['state' => 'claimed', 'claim_version' => 2, 'claimed_at' => $this->time, 'lease_expires_at' => $this->time + 30000]);
        DB::table('otp_delivery_intents')->where('challenge_id', $challenge)->update(['state' => 'accepted', 'claim_version' => 3, 'completed_at' => $this->time + 1]);
        DB::table('phone_otp_challenges')->where('id', $challenge)->update(['state' => 'active', 'state_version' => 3]);
        DB::table('phone_otp_challenges')->where('id', $challenge)->update(['state' => 'consumed', 'state_version' => 4, 'active_slot' => null, 'terminal_at' => $this->time + 2]);
        DB::table('contact_verification_proofs')->insert(['id' => $proof, ...$base, 'challenge_id' => $challenge, 'channel' => 'sms', 'provenance' => $transport, 'policy_version' => 'application-phone-proof-v1', 'verified_at' => $this->time + 2, 'expires_at' => $this->time + 1800002]);
        $this->evidence = ['application_id' => $application->id, 'merchant_id' => $merchant->id, 'contract_version' => 'submission-evidence-v1', 'actor_user_id' => $user->id, 'membership_id' => $merchant->memberships()->value('id'), 'auth_version' => 1, 'authority_version' => 1, 'email_binding_version' => 1, 'attempt_number' => 1, 'profile_version' => 1, 'context_version' => 1, 'identity_id' => $identity, 'identity_version' => 1, 'contact_id' => $contact, 'contact_version' => 1, 'purpose' => 'application_phone', 'proof_id' => $proof, 'submission_request_id' => (string) Str::uuid(), 'submitted_at_ms' => $this->time + 3, 'submitted_at' => VerificationAuditReconciler::occurredAt($this->time + 3), 'audit_event_id' => (string) Str::ulid(), 'audit_event_type' => 'application.submitted', 'evidence_ciphertext' => 'FICTIONAL-OPAQUE-EVIDENCE-NOT-REAL-CRYPTO', 'evidence_key_ref' => 'fixture.evidence', 'binding_key_ref' => 'fixture.binding', 'retention_policy_version' => 'fixture.retention', 'envelope_version' => 1];
        config(['se1.selected' => realpath(SE::artifact())]);
        $this->assertSame(0, Artisan::call('migrate', ['--database' => 'sqlite', '--path' => [SE::artifact()], '--realpath' => true, '--no-interaction' => true]));
        $this->assertTrue(SE::ready());
        $this->actor = $user;
        $this->actingAs($user)->withSession(['auth_version' => 1]);
        $this->expected = array_intersect_key($this->evidence, array_flip(ApplicationSubmissionService::EXPECTATIONS));
        $p = (array) DB::table('contact_verification_proofs')->first();
        $h = (array) DB::table('phone_otp_challenges')->first();
        $d = (array) DB::table('otp_delivery_intents')->first();
        $i = (array) DB::table('merchant_identity_records')->first();
        $this->admission->issued = [$p['id'], $p['challenge_id'], $p['merchant_id'], $p['actor_user_id'], $p['contact_id'], $p['verified_at'], $p['expires_at'], $d['id'], $d['request_id'], $d['completed_at'], $h['id'], $h['terminal_at'], $i['id'], $i['syntax_policy_version']];
    }

    private function envelope(string $domain, string $value, string $merchant, string $record, int $actor, int $revision = 1): string
    {
        return app(VerificationCipher::class)->encrypt($domain, $value, ['namespace' => 'fixture', 'merchant' => $merchant, 'record' => $record, 'purpose' => $domain === 'nin' ? 'identity_declaration' : 'application_phone', 'version' => 1, 'actor' => $actor], $domain.'.v1', $revision);
    }

    private function submit(?array $expected = null): array
    {
        return app(ApplicationSubmissionService::class)->submit($this->actor, $this->evidence['merchant_id'], $expected ?? $this->expected);
    }

    public function test_fictional_bundle_commits_once_with_canonical_audit_and_private_receipt(): void
    {
        $receipt = $this->submit();
        $this->assertSame('application.submitted', $receipt['event']);
        $this->assertSame(['application_id', 'merchant_id', 'submission_request_id', 'submitted_at', 'event'], array_keys($receipt));
        $this->assertSame('pending_review', DB::table('merchant_applications')->value('status'));
        $this->assertSame('consumed', DB::table('contact_verification_proofs')->value('state'));
        $this->assertSame(1, DB::table(SE::TABLE)->count());
        $this->assertSame(1, DB::table('audit_logs')->where('event_type', 'application.submitted')->count());
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
        $before = $this->fingerprint();
        $this->assertSame($receipt, $this->submit());
        $this->assertSame($receipt, app(ApplicationSubmissionService::class)->resolve($this->actor, $this->evidence['merchant_id'], $this->expected));
        $this->assertSame($before, $this->fingerprint());
        $row = (array) DB::table(SE::TABLE)->sole();
        foreach (['owner@fixture.test', '+255712345678', 'FIXTURE-ABCDEFGH'] as $private) {
            $this->assertStringNotContainsString($private, json_encode([$receipt, $row, DB::table('audit_logs')->where('event_type', 'application.submitted')->first()], JSON_THROW_ON_ERROR));
        }
        $this->assertSame('onboarding', DB::table('merchants')->value('operational_status'));
        $this->assertSame('unverified', DB::table('merchants')->value('verification_status'));
        $this->assertNull(DB::table('merchant_applications')->value('profile_snapshot'));
        Notification::assertNothingSent();
    }

    public static function stale(): array
    {
        return array_map(fn (string $field): array => [$field], array_values(array_diff(ApplicationSubmissionService::EXPECTATIONS, ['submission_request_id'])));
    }

    #[DataProvider('stale')]
    public function test_each_stale_expectation_is_denied_without_writes(string $field): void
    {
        $expected = $this->expected;
        $expected[$field] = is_int($expected[$field]) ? $expected[$field] + 1 : (string) Str::ulid();
        $status = in_array($field, ['auth_version', 'membership_id'], true) ? 403 : 409;
        $this->denied(fn () => $this->submit($expected), $status);
    }

    public static function missingOperations(): array
    {
        return [['authorization'], ['restore'], ['custody'], ['custody_restore'], ['issuer'], ['retention'], ['time'], ['simulated'], ['encryption'], ['source_cipher']];
    }

    #[DataProvider('missingOperations')]
    public function test_missing_or_untrusted_operational_dependencies_deny(string $kind): void
    {
        match ($kind) {
            'authorization' => $this->admission->enabled = false,
            'restore' => $this->admission->restore = false,
            'custody' => $this->custody->enabled = false,
            'custody_restore' => $this->custody->restore = false,
            'issuer' => $this->admission->issuer = false,
            'retention' => $this->admission->retention = false,
            'time' => $this->admission->time = 0,
            'simulated' => $this->verification->transport = 'simulated',
            'encryption' => $this->custody->keys['evidence.v1'] = str_repeat('x', 3),
            'source_cipher' => $this->verification->keys['nin'] = random_bytes(32),
        };
        $this->denied(fn () => $this->submit(), $kind === 'time' ? 409 : 503);
    }

    public function test_default_admission_and_public_endpoint_deny_even_with_fixture_crypto(): void
    {
        $this->app->instance(SubmissionAdmission::class, new DeniedSubmissionAdmission);
        $this->denied(fn () => $this->submit(), 503);
        $this->app->instance(SubmissionAdmission::class, $this->admission);
        $before = $this->fingerprint();
        $this->post(route('merchants.applications.submit', ['merchant' => $this->evidence['merchant_id']]), ['application_id' => $this->expected['application_id'], 'profile_version' => 1, 'context_version' => 1])->assertStatus(503);
        $this->assertSame($before, $this->fingerprint());
        $this->assertSame(0, DB::table(SE::TABLE)->count());
    }

    public function test_guest_foreign_actor_and_cross_tenant_cannot_submit_or_resolve(): void
    {
        auth()->forgetGuards();
        $this->denied(fn () => $this->submit(), 403);
        $foreign = User::factory()->create();
        $this->actingAs($foreign);
        $this->denied(fn () => $this->submit(), 403);
        $this->actingAs($this->actor);
        $business = Merchant::factory()->create();
        $this->denied(fn () => app(ApplicationSubmissionService::class)->submit($this->actor, $business->id, $this->expected), 403);
        $this->denied(fn () => app(ApplicationSubmissionService::class)->resolve($this->actor, $business->id, $this->expected), 403);
    }

    public function test_manager_and_revoked_owner_cannot_use_cached_actor_or_receipt(): void
    {
        DB::table('merchant_memberships')->where('id', $this->expected['membership_id'])->update(['role' => 'manager', 'authority_version' => 2]);
        $this->denied(fn () => $this->submit(), 403);
        DB::table('merchant_memberships')->where('id', $this->expected['membership_id'])->update(['role' => 'owner', 'authority_version' => 3]);
        $this->expected['authority_version'] = 3;
        $this->submit();
        DB::table('merchant_memberships')->where('id', $this->expected['membership_id'])->update(['status' => 'revoked', 'revoked_at' => '2026-11-08 00:00:00', 'authority_version' => 4]);
        $this->denied(fn () => $this->submit(), 403);
    }

    public function test_invalid_extra_inputs_and_string_epochs_deny(): void
    {
        $this->denied(fn () => $this->submit([...$this->expected, 'allow' => true]), 422);
        $this->denied(fn () => $this->submit([...$this->expected, 'profile_version' => '1']), 422);
    }

    public function test_revoked_and_exact_expiry_proofs_deny_new_submission(): void
    {
        $this->admission->time = $this->time + 1800002;
        $this->custody->time = $this->verification->time = $this->admission->time;
        $this->denied(fn () => $this->submit(), 409);
        $this->admission->time = $this->custody->time = $this->verification->time = $this->time + 100;
        DB::table('contact_verification_proofs')->update(['state' => 'revoked', 'state_version' => 2, 'revoked_at' => $this->time + 100, 'reason_code' => 'operator_revoked']);
        $this->denied(fn () => $this->submit(), 409);
    }

    public function test_historical_receipt_survives_proof_revocation_but_different_request_conflicts(): void
    {
        $receipt = $this->submit();
        DB::table('contact_verification_proofs')->update(['state' => 'revoked', 'state_version' => 3, 'revoked_at' => $this->time + 100, 'reason_code' => 'operator_revoked']);
        $this->admission->time = $this->custody->time = $this->verification->time = $this->time + 2000000;
        $this->assertSame($receipt, $this->submit());
        $this->denied(fn () => $this->submit([...$this->expected, 'submission_request_id' => (string) Str::uuid()]), 409);
        $this->denied(fn () => $this->submit([...$this->expected, 'profile_version' => 2]), 409);
    }

    public function test_audit_acknowledgement_failure_rolls_back_complete_bundle(): void
    {
        DB::listen(function ($query): void {
            if (str_starts_with($query->sql, 'insert into "'.SE::TABLE.'"')) {
                $this->assertSame(1, DB::table('audit_logs')->where('event_type', 'application.submitted')->count());
                throw new RuntimeException('Fictional post-audit failure');
            }
        });
        $this->denied(fn () => $this->submit(), 503);
        $this->assertSame('available', DB::table('contact_verification_proofs')->value('state'));
        $this->assertSame(0, DB::table(SE::TABLE)->count());
    }

    public function test_schema_drift_denies_instead_of_repairing_partial_bundle(): void
    {
        DB::unprepared('DROP TRIGGER se1_evidence_audit');
        $this->denied(fn () => $this->submit(), 503);
    }

    public function test_source_envelope_rotation_preserves_receipt_but_changed_logical_content_does_not(): void
    {
        $receipt = $this->submit();
        $evidence = (array) DB::table(SE::TABLE)->sole();
        DB::table('merchant_identity_records')->update(['nin_ciphertext' => $this->envelope('nin', 'FIXTURE-ABCDEFGH', $this->evidence['merchant_id'], $this->expected['identity_id'], $this->actor->id, 2), 'envelope_revision' => 2, 'row_version' => 2]);
        $this->assertSame($receipt, $this->submit());
        $this->assertSame($evidence, (array) DB::table(SE::TABLE)->sole());
        DB::table('merchant_identity_records')->update(['nin_ciphertext' => $this->envelope('nin', 'FIXTURE-CHANGED1', $this->evidence['merchant_id'], $this->expected['identity_id'], $this->actor->id, 3), 'envelope_revision' => 3, 'row_version' => 3]);
        $this->denied(fn () => $this->submit(), 503);
        $this->assertSame($evidence, (array) DB::table(SE::TABLE)->sole());
    }

    public function test_simulated_database_lineage_denies_even_with_live_mode_and_injected_issuer(): void
    {
        $this->fixture('simulated');
        $this->assertSame('live', $this->verification->mode());
        $this->denied(fn () => $this->submit(), 503);
    }

    public function test_authenticated_session_epoch_is_required_and_default_container_binding_denies(): void
    {
        session(['auth_version' => 0]);
        $this->denied(fn () => $this->submit(), 403);
        session(['auth_version' => 1]);
        $this->app->forgetInstance(SubmissionAdmission::class);
        $this->assertInstanceOf(DeniedSubmissionAdmission::class, app(SubmissionAdmission::class));
        $this->denied(fn () => $this->submit(), 503);
    }

    public static function writeFailures(): array
    {
        return [['contact_verification_proofs'], ['merchant_applications']];
    }

    #[DataProvider('writeFailures')]
    public function test_late_proof_or_application_failure_rolls_back_all_parts(string $table): void
    {
        DB::listen(function ($query) use ($table): void {
            if (str_starts_with($query->sql, 'update "'.$table.'"')) {
                throw new RuntimeException('Fictional write acknowledgement failure');
            }
        });
        $this->denied(fn () => $this->submit(), 503);
    }

    public function test_authority_change_between_evidence_and_consumption_cannot_commit(): void
    {
        DB::listen(function ($query): void {
            if (str_starts_with($query->sql, 'insert into "'.SE::TABLE.'"')) {
                DB::table('merchant_memberships')->where('id', $this->expected['membership_id'])->update(['role' => 'manager', 'authority_version' => 2]);
            }
        });
        $this->denied(fn () => $this->submit(), 503);
        $this->assertSame(1, DB::table('merchant_memberships')->value('authority_version'));
    }

    public function test_high_water_and_identity_retention_admission_deny_without_discharge(): void
    {
        DB::table('verification_limit_state')->insert(['scope_key' => str_repeat('e', 64), 'scope_key_version' => 'contact.v1', 'policy_rule' => 'otp.fail.rolling30.v1', 'merchant_id' => $this->evidence['merchant_id'], 'actor_user_id' => $this->actor->id, 'last_observed_at' => $this->time + 101, 'updated_at' => $this->time + 101]);
        $this->denied(fn () => $this->submit(), 409);
        $this->admission->time = $this->custody->time = $this->verification->time = $this->time + 102;
        $this->assertSame($this->time + 101, DB::table('verification_limit_state')->value('last_observed_at'));
        $this->fixture('live', $this->time + 99);
        $this->denied(fn () => $this->submit(), 503);
    }

    private function fileFixture(\Closure $operation): void
    {
        $path = tempnam(sys_get_temp_dir(), 'xlipa-b3-');
        $this->assertNotFalse($path);
        $this->assertSame(realpath(sys_get_temp_dir()), realpath(dirname($path)));
        unlink($path);
        try {
            DB::statement('VACUUM INTO '.DB::connection()->getPdo()->quote($path));
            DB::purge('sqlite');
            config(['database.connections.sqlite.database' => $path, 'database.connections.sqlite.busy_timeout' => 0]);
            DB::statement('PRAGMA recursive_triggers=ON');
            $operation($path);
        } finally {
            DB::purge('sqlite');
            config(['database.connections.sqlite.database' => ':memory:']);
            foreach ([$path, $path.'-journal', $path.'-wal', $path.'-shm'] as $temporary) {
                if (is_file($temporary)) {
                    unlink($temporary);
                }
            }
        }
    }

    public function test_generated_database_writer_contention_then_one_winner_and_replay(): void
    {
        $this->fileFixture(function (string $path): void {
            $other = new \PDO('sqlite:'.$path, options: [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
            $other->exec('PRAGMA busy_timeout=0');
            $other->exec('BEGIN IMMEDIATE');
            try {
                $this->denied(fn () => $this->submit(), 503);
            } finally {
                $other->exec('ROLLBACK');
                $other = null;
            }
            $receipt = $this->submit();
            $this->assertSame($receipt, $this->submit());
            $this->denied(fn () => $this->submit([...$this->expected, 'submission_request_id' => (string) Str::uuid()]), 409);
            $this->assertSame(1, DB::table(SE::TABLE)->count());
        });
    }

    public static function acknowledgements(): array
    {
        return [[false], [true]];
    }

    #[DataProvider('acknowledgements')]
    public function test_unknown_commit_requires_fresh_resolution_without_automatic_retry(bool $committed): void
    {
        $this->fileFixture(function () use ($committed): void {
            $transaction = new class($committed) extends SubmissionTransaction
            {
                public int $attempts = 0;

                public function __construct(private bool $committed) {}

                protected function commit(Connection $connection): void
                {
                    $this->attempts++;
                    if ($this->committed) {
                        parent::commit($connection);
                    }
                    throw new RuntimeException('Fictional lost acknowledgement');
                }
            };
            $this->app->instance(SubmissionTransaction::class, $transaction);
            $old = DB::connection();
            try {
                $this->submit();
                $this->fail('Outcome must be unknown.');
            } catch (SubmissionOutcomeUnknown $error) {
                $this->assertStringContainsString('not confirmed', $error->getMessage());
                $this->assertNull($error->getPrevious());
            }
            $this->assertSame(1, $transaction->attempts);
            $this->assertNotSame($old, DB::connection());
            DB::statement('PRAGMA recursive_triggers=ON');
            $this->app->instance(SubmissionTransaction::class, new SubmissionTransaction);
            $this->admission->restore = false;
            $this->denied(fn () => app(ApplicationSubmissionService::class)->resolve($this->actor, $this->evidence['merchant_id'], $this->expected), 503);
            $this->admission->restore = true;
            $resolved = app(ApplicationSubmissionService::class)->resolve($this->actor, $this->evidence['merchant_id'], $this->expected);
            $this->assertSame($committed, $resolved !== null);
            $receipt = $this->submit();
            $this->assertSame($resolved ?? $receipt, $receipt);
            $this->assertSame(1, DB::table(SE::TABLE)->count());
            $this->assertSame(1, DB::table('audit_logs')->where('event_type', 'application.submitted')->count());
        });
    }

    private function freshProof(bool $exhaust = false): string
    {
        $original = (array) DB::table('contact_verification_proofs')->where('id', $this->evidence['proof_id'])->first();
        $challenge = (array) DB::table('phone_otp_challenges')->where('id', $original['challenge_id'])->first();
        $intent = (array) DB::table('otp_delivery_intents')->where('challenge_id', $original['challenge_id'])->first();
        $challenge = [...$challenge, 'id' => (string) Str::ulid(), 'state' => 'queued', 'state_version' => 1, 'active_slot' => 1, 'code_digest' => null, 'digest_key_ref' => null, 'digest_created_at' => null, 'terminal_at' => null, 'failed_attempts' => 0];
        DB::table('phone_otp_challenges')->insert($challenge);
        $intent = [...$intent, 'id' => (string) Str::ulid(), 'challenge_id' => $challenge['id'], 'request_id' => (string) Str::uuid(), 'state' => 'queued', 'claim_version' => 1, 'claimed_at' => null, 'lease_expires_at' => null, 'completed_at' => null, 'failure_code' => null];
        DB::table('otp_delivery_intents')->insert($intent);
        DB::table('phone_otp_challenges')->where('id', $challenge['id'])->update(['state' => 'dispatching', 'state_version' => 2, 'code_digest' => str_repeat('d', 64), 'digest_key_ref' => 'fixture.otp', 'digest_created_at' => $this->time]);
        DB::table('otp_delivery_intents')->where('id', $intent['id'])->update(['state' => 'claimed', 'claim_version' => 2, 'claimed_at' => $this->time, 'lease_expires_at' => $this->time + 30000]);
        DB::table('otp_delivery_intents')->where('id', $intent['id'])->update(['state' => 'accepted', 'claim_version' => 3, 'completed_at' => $this->time + 1]);
        DB::table('phone_otp_challenges')->where('id', $challenge['id'])->update(['state' => 'active', 'state_version' => 3]);
        if ($exhaust) {
            for ($count = 1; $count <= 5; $count++) {
                DB::table('phone_otp_challenges')->where('id', $challenge['id'])->update(['state' => $count === 5 ? 'exhausted' : 'active', 'state_version' => 3 + $count, 'failed_attempts' => $count, 'active_slot' => $count === 5 ? null : 1, 'terminal_at' => $count === 5 ? $this->time + 3 : null]);
            }

            return $challenge['id'];
        }
        DB::table('phone_otp_challenges')->where('id', $challenge['id'])->update(['state' => 'consumed', 'state_version' => 4, 'active_slot' => null, 'terminal_at' => $this->time + 2]);
        $proof = [...$original, 'id' => (string) Str::ulid(), 'challenge_id' => $challenge['id'], 'state' => 'available', 'state_version' => 1, 'consumed_at' => null, 'consumed_application_id' => null, 'revoked_at' => null, 'reason_code' => null];
        DB::table('contact_verification_proofs')->insert($proof);

        return $proof['id'];
    }

    public function test_pending_r06_blocks_an_otherwise_valid_bundle_without_discharge(): void
    {

        $challenge = $this->freshProof(true);
        $scope = str_repeat('e', 64);
        $stamp = $this->time + 3;
        DB::table('verification_limit_state')->insert(['scope_key' => $scope, 'scope_key_version' => 'fixture.fp', 'policy_rule' => 'otp.fail.rolling30.v1', 'merchant_id' => $this->evidence['merchant_id'], 'actor_user_id' => $this->evidence['actor_user_id'], 'last_observed_at' => $stamp, 'updated_at' => $stamp]);
        $event = (string) Str::ulid();
        $changes = ['state_version' => 2, 'pending_audit_event_id' => $event, 'pending_audit_challenge_id' => $challenge, 'pending_audit_request_id' => DB::table('otp_delivery_intents')->where('challenge_id', $challenge)->value('request_id'), 'pending_audit_reason_code' => 'exhausted', 'pending_audit_occurred_at' => VerificationAuditReconciler::occurredAt($stamp)];
        for ($count = 1; $count <= 5; $count++) {
            $changes[sprintf('failure_%02d_at', $count)] = $stamp;
        }
        DB::table('verification_limit_state')->where('scope_key', $scope)->update($changes);
        $this->denied(fn () => $this->submit(), 409);
        $this->assertSame($event, DB::table('verification_limit_state')->value('pending_audit_event_id'));
        $this->assertSame($stamp, DB::table('verification_limit_state')->value('failure_05_at'));
    }

    private function fingerprint(): string
    {
        return hash('sha256', json_encode(array_map(fn (string $table): array => DB::select('SELECT * FROM '.$table.' ORDER BY rowid'), [SE::TABLE, 'users', 'merchants', 'merchant_memberships', 'merchant_applications', 'merchant_onboarding_contexts', 'merchant_identity_records', 'merchant_contact_versions', 'phone_otp_challenges', 'otp_delivery_intents', 'verification_limit_state', 'contact_verification_proofs', 'audit_logs']), JSON_THROW_ON_ERROR));
    }

    private function denied(\Closure $operation, int $status): void
    {
        $before = $this->fingerprint();
        try {
            $operation();
            $this->fail('Submission must be denied.');
        } catch (HttpExceptionInterface $error) {
            $this->assertSame($status, $error->getStatusCode());
            $this->assertStringNotContainsString('FIXTURE-ABCDEFGH', $error->getTraceAsString());
            $this->assertStringNotContainsString('+255712345678', $error->getTraceAsString());
        }
        $this->assertSame($before, $this->fingerprint());
        $this->assertSame(0, DB::connection()->transactionLevel());
    }
}
