<?php

namespace Tests\Feature;

use App\Actions\Merchants\SubmissionEvidenceSchema as SE;
use App\Actions\Merchants\VerificationAuditReconciler;
use App\Actions\Merchants\VerificationStore;
use App\Models\Merchant;
use App\Models\MerchantApplication;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\B32CRehearsal;
use Tests\TestCase;

/** Schema-only fixtures: opaque fictional envelopes and raw live-shaped lineage are NOT operational proofs. */
class SubmissionEvidenceSchemaTest extends TestCase
{
    private array $evidence;

    private int $time = 1794096000000;

    protected function setUp(): void
    {
        parent::setUp();
        B32CRehearsal::foundation();
        B32CRehearsal::activate();
        Notification::fake();
        DB::statement('PRAGMA recursive_triggers=ON');
        $user = User::factory()->create();
        $merchant = Merchant::factory()->create(['created_by_user_id' => $user->id]);
        $application = $merchant->applications()->where('current_slot', 1)->firstOrFail();
        $identity = (string) Str::ulid();
        $contact = (string) Str::ulid();
        $challenge = (string) Str::ulid();
        $proof = (string) Str::ulid();
        DB::table('merchant_identity_records')->insert(['id' => $identity, 'merchant_id' => $merchant->id, 'version' => 1, 'subject_claim' => 'individual_owner', 'nin_ciphertext' => 'FICTIONAL-OPAQUE-IDENTITY-ENVELOPE', 'encryption_key_ref' => 'fixture.nin', 'envelope_version' => 1, 'syntax_policy_version' => 'fixture.syntax', 'syntax_checked_at' => $this->time, 'declaration_version' => 'fixture.declaration', 'notice_version' => 'fixture.notice', 'declared_by_user_id' => $user->id, 'declared_at' => $this->time]);
        DB::table('merchant_contact_versions')->insert(['id' => $contact, 'merchant_id' => $merchant->id, 'actor_user_id' => $user->id, 'purpose' => 'application_phone', 'version' => 1, 'phone_ciphertext' => 'FICTIONAL-OPAQUE-CONTACT-ENVELOPE', 'phone_key_ref' => 'fixture.phone', 'envelope_version' => 1, 'target_fingerprint' => str_repeat('a', 64), 'fingerprint_key_ref' => 'fixture.fp', 'created_at' => $this->time]);
        DB::table('merchant_onboarding_contexts')->insert(['merchant_id' => $merchant->id, 'current_identity_id' => $identity, 'current_contact_id' => $contact, 'contact_actor_user_id' => $user->id, 'contact_purpose' => 'application_phone', 'updated_at' => $this->time]);
        $base = ['merchant_id' => $merchant->id, 'actor_user_id' => $user->id, 'contact_id' => $contact, 'purpose' => 'application_phone'];
        DB::table('phone_otp_challenges')->insert(['id' => $challenge, ...$base, 'active_slot' => 1, 'created_at' => $this->time, 'expires_at' => $this->time + 300000, 'transport_mode' => 'live']);
        DB::table('phone_otp_challenges')->where('id', $challenge)->update(['state' => 'dispatching', 'state_version' => 2, 'code_digest' => str_repeat('b', 64), 'digest_key_ref' => 'fixture.otp', 'digest_created_at' => $this->time]);
        DB::table('otp_delivery_intents')->insert(['id' => (string) Str::ulid(), ...$base, 'challenge_id' => $challenge, 'transport_mode' => 'live', 'request_id' => (string) Str::uuid(), 'requested_at' => $this->time]);
        DB::table('otp_delivery_intents')->where('challenge_id', $challenge)->update(['state' => 'claimed', 'claim_version' => 2, 'claimed_at' => $this->time, 'lease_expires_at' => $this->time + 30000]);
        DB::table('otp_delivery_intents')->where('challenge_id', $challenge)->update(['state' => 'accepted', 'claim_version' => 3, 'completed_at' => $this->time + 1]);
        DB::table('phone_otp_challenges')->where('id', $challenge)->update(['state' => 'active', 'state_version' => 3]);
        DB::table('phone_otp_challenges')->where('id', $challenge)->update(['state' => 'consumed', 'state_version' => 4, 'active_slot' => null, 'terminal_at' => $this->time + 2]);
        DB::table('contact_verification_proofs')->insert(['id' => $proof, ...$base, 'challenge_id' => $challenge, 'channel' => 'sms', 'provenance' => 'live', 'policy_version' => 'application-phone-proof-v1', 'verified_at' => $this->time + 2, 'expires_at' => $this->time + 1800002]);
        $this->evidence = ['application_id' => $application->id, 'merchant_id' => $merchant->id, 'contract_version' => 'submission-evidence-v1', 'actor_user_id' => $user->id, 'membership_id' => $merchant->memberships()->value('id'), 'auth_version' => 1, 'authority_version' => 1, 'email_binding_version' => 1, 'attempt_number' => 1, 'profile_version' => 1, 'context_version' => 1, 'identity_id' => $identity, 'identity_version' => 1, 'contact_id' => $contact, 'contact_version' => 1, 'purpose' => 'application_phone', 'proof_id' => $proof, 'submission_request_id' => (string) Str::uuid(), 'submitted_at_ms' => $this->time + 3, 'submitted_at' => VerificationAuditReconciler::occurredAt($this->time + 3), 'audit_event_id' => (string) Str::ulid(), 'audit_event_type' => 'application.submitted', 'evidence_ciphertext' => 'FICTIONAL-OPAQUE-EVIDENCE-NOT-REAL-CRYPTO', 'evidence_key_ref' => 'fixture.evidence', 'binding_key_ref' => 'fixture.binding', 'retention_policy_version' => 'fixture.retention', 'envelope_version' => 1];
    }

    private function activate(): void
    {
        config(['se1.selected' => realpath(SE::artifact())]);
        $this->assertSame(0, Artisan::call('migrate', ['--database' => 'sqlite', '--path' => [SE::artifact()], '--realpath' => true, '--no-interaction' => true]));
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

    private function consume(): void
    {
        DB::table('contact_verification_proofs')->where('id', $this->evidence['proof_id'])->update(['state' => 'consumed', 'state_version' => 2, 'consumed_at' => $this->evidence['submitted_at_ms'], 'consumed_application_id' => $this->evidence['application_id']]);
    }

    private function transition(): void
    {
        DB::table('merchant_applications')->where('id', $this->evidence['application_id'])->update(['status' => 'pending_review', 'submitted_at' => $this->evidence['submitted_at'], 'submitted_by_user_id' => $this->evidence['actor_user_id']]);
    }

    private function bundle(): void
    {
        app(VerificationStore::class)->run(function (): void {
            DB::table(SE::TABLE)->insert($this->evidence);
            $this->consume();
            $this->transition();
        });
    }

    private function state(): string
    {
        return hash('sha256', json_encode(array_map(fn (string $table): array => DB::select('SELECT * FROM '.$table.' ORDER BY rowid'), [SE::TABLE, 'merchant_applications', 'contact_verification_proofs', 'audit_logs']), JSON_THROW_ON_ERROR));
    }

    private function denied(\Closure $operation): void
    {
        $before = $this->state();
        try {
            $operation();
            $this->fail('A security constraint must deny this operation.');
        } catch (\PDOException $error) {
            $this->assertContains($error->errorInfo[1], [19, 787, 1811, 2067, 1555, 275, 1299]);
        }
        $this->assertSame($before, $this->state());
        $this->assertSame(0, DB::connection()->transactionLevel());
    }

    public function test_schema_version_exact_c_compatibility_and_valid_bundle(): void
    {
        $this->activate();
        $this->assertTrue(SE::ready());
        SE::assertFrozen();
        $this->bundle();
        $this->assertSame('pending_review', DB::table('merchant_applications')->value('status'));
        $this->assertSame('consumed', DB::table('contact_verification_proofs')->value('state'));
        $this->assertSame(1, DB::table(SE::TABLE)->count());
        $this->assertSame(1, DB::table('audit_logs')->where('event_type', 'application.submitted')->count());
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
        $this->assertSame(['from' => 'draft', 'to' => 'pending_review', 'profile_version' => 1, 'reason_code' => 'minimum_evidence_bound'], json_decode(DB::table('audit_logs')->where('event_type', 'application.submitted')->value('metadata'), true));
    }

    public static function incomplete(): array
    {
        return [['evidence'], ['proof'], ['application'], ['audit']];
    }

    #[DataProvider('incomplete')]
    public function test_incomplete_bundles_cannot_commit(string $omit): void
    {
        $this->activate();
        if ($omit === 'audit') {
            DB::unprepared("CREATE TRIGGER fixture_audit_failure BEFORE INSERT ON audit_logs WHEN NEW.event_type='application.submitted' BEGIN SELECT RAISE(ABORT,'fixture outage'); END");
        }
        $this->denied(function () use ($omit): void {
            app(VerificationStore::class)->run(function () use ($omit): void {
                if ($omit !== 'evidence') {
                    DB::table(SE::TABLE)->insert($this->evidence);
                }
                if ($omit !== 'proof') {
                    $this->consume();
                }
                if ($omit !== 'application') {
                    $this->transition();
                }
            });
        });
    }

    public static function invalidFields(): array
    {
        return [['email_binding_version', 2], ['authority_version', 2], ['auth_version', 2], ['profile_version', 2], ['context_version', 2], ['identity_version', 2], ['contact_version', 2], ['attempt_number', 2], ['actor_user_id', 999999], ['merchant_id', '01AAAAAAAAAAAAAAAAAAAAAAAA'], ['proof_id', '01BBBBBBBBBBBBBBBBBBBBBBBB'], ['submitted_at', '2026-11-08 00:00:00.999'], ['purpose', 'settlement'], ['envelope_version', 2], ['binding_key_ref', 'BAD KEY'], ['submission_request_id', 'not-a-uuid']];
    }

    #[DataProvider('invalidFields')]
    public function test_invalid_or_stale_tuple_is_rejected(string $field, mixed $value): void
    {
        $this->activate();
        $this->evidence[$field] = $value;
        $this->denied(fn () => $this->bundle());
    }

    public static function mutations(): array
    {
        return [['update'], ['delete'], ['replace'], ['duplicate'], ['proof_reuse'], ['request_reuse'], ['upsert']];
    }

    #[DataProvider('mutations')]
    public function test_raw_sql_evidence_mutations_and_reuse_are_denied(string $kind): void
    {
        $this->activate();
        $this->bundle();
        $this->denied(function () use ($kind): void {
            if ($kind === 'update') {
                DB::statement('UPDATE '.SE::TABLE.' SET evidence_ciphertext=evidence_ciphertext');
            } elseif ($kind === 'delete') {
                DB::statement('DELETE FROM '.SE::TABLE);
            } elseif ($kind === 'replace' || $kind === 'upsert') {
                $columns = implode(',', array_keys($this->evidence));
                $sql = ($kind === 'replace' ? 'INSERT OR REPLACE' : 'INSERT').' INTO '.SE::TABLE." ($columns) VALUES (".implode(',', array_fill(0, count($this->evidence), '?')).')'.($kind === 'upsert' ? ' ON CONFLICT(application_id) DO UPDATE SET evidence_ciphertext=excluded.evidence_ciphertext' : '');
                DB::statement($sql, array_values($this->evidence));
            } else {
                $copy = $this->evidence;
                if ($kind !== 'duplicate') {
                    $copy['application_id'] = (string) Str::ulid();
                    $copy['audit_event_id'] = (string) Str::ulid();
                    if ($kind === 'proof_reuse') {
                        $copy['submission_request_id'] = (string) Str::uuid();
                    } else {
                        $copy['proof_id'] = (string) Str::ulid();
                    }
                }
                DB::table(SE::TABLE)->insert($copy);
            }
        });
    }

    public function test_each_evidence_column_is_immutable_even_when_unchanged(): void
    {
        $this->activate();
        $this->bundle();
        foreach (array_keys($this->evidence) as $column) {
            $this->denied(fn () => DB::statement('UPDATE '.SE::TABLE." SET $column=$column"));
        }
    }

    public function test_request_uuid_cannot_be_reused_for_an_otherwise_valid_new_attempt(): void
    {
        $this->activate();
        $this->bundle();
        $proof = $this->freshProof();
        DB::table('merchant_applications')->where('id', $this->evidence['application_id'])->update(['current_slot' => null]);
        $next = MerchantApplication::factory()->create(['merchant_id' => $this->evidence['merchant_id'], 'attempt_number' => 2, 'current_slot' => 1]);
        $this->evidence = [...$this->evidence, 'application_id' => $next->id, 'attempt_number' => 2, 'audit_event_id' => (string) Str::ulid(), 'proof_id' => $proof];
        $this->denied(fn () => $this->bundle());
        $this->evidence['submission_request_id'] = (string) Str::uuid();
        $this->bundle();
        $this->assertSame(2, DB::table(SE::TABLE)->count());
    }

    public function test_pending_r06_blocks_an_otherwise_valid_bundle_without_discharge(): void
    {
        $this->activate();
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
        $this->denied(fn () => $this->bundle());
        $this->assertSame($event, DB::table('verification_limit_state')->value('pending_audit_event_id'));
        $this->assertSame($stamp, DB::table('verification_limit_state')->value('failure_05_at'));
    }

    public function test_legacy_maximum_profile_version_cannot_overflow(): void
    {
        DB::table('merchants')->update(['profile_version' => PHP_INT_MAX]);
        $this->activate();
        $this->denied(fn () => DB::statement("UPDATE merchants SET business_name='changed',profile_version=profile_version+1"));
    }

    public function test_standalone_evidence_insert_fails_deferred_commit_and_creates_no_audit(): void
    {
        $this->activate();
        $this->denied(fn () => DB::table(SE::TABLE)->insert($this->evidence));
        $this->assertSame(0, DB::table('audit_logs')->where('event_type', 'application.submitted')->count());
    }

    public function test_audit_failure_after_insert_rolls_back_the_entire_bundle(): void
    {
        $this->activate();
        DB::unprepared("CREATE TRIGGER fixture_fail_after_audit AFTER INSERT ON audit_logs WHEN NEW.event_type='application.submitted' BEGIN SELECT RAISE(ABORT,'fixture after audit'); END");
        $this->denied(fn () => $this->bundle());
        $this->assertSame('available', DB::table('contact_verification_proofs')->value('state'));
    }

    public function test_committed_consumption_and_evidence_survive_later_revocation(): void
    {
        $this->activate();
        $this->bundle();
        $evidence = DB::table(SE::TABLE)->first();
        DB::table('contact_verification_proofs')->update(['state' => 'revoked', 'state_version' => 3, 'revoked_at' => $this->time + 4, 'reason_code' => 'operator_revoked']);
        $this->assertEquals($evidence, DB::table(SE::TABLE)->first());
        $this->assertSame($this->evidence['application_id'], DB::table('contact_verification_proofs')->value('consumed_application_id'));
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
    }

    public function test_changed_binding_between_evidence_insert_and_consumption_rolls_back(): void
    {
        $this->activate();
        $this->denied(function (): void {
            app(VerificationStore::class)->run(function (): void {
                DB::table(SE::TABLE)->insert($this->evidence);
                DB::table('merchant_memberships')->where('id', $this->evidence['membership_id'])->update(['role' => 'manager', 'authority_version' => 2]);
                $this->consume();
                $this->transition();
            });
        });
        $this->assertSame(1, DB::table('merchant_memberships')->value('authority_version'));
    }

    public function test_epoch_resets_replacements_and_overflow_are_denied(): void
    {
        $this->activate();
        foreach (['users', 'merchant_memberships'] as $table) {
            $this->denied(fn () => DB::statement("DELETE FROM $table"));
            $row = (array) DB::table($table)->first();
            $this->denied(fn () => DB::statement("INSERT OR REPLACE INTO $table (".implode(',', array_keys($row)).') VALUES ('.implode(',', array_fill(0, count($row), '?')).')', array_values($row)));
        }
        $this->denied(fn () => DB::table('users')->update(['email_binding_version' => 0]));
        $this->denied(fn () => DB::table('merchant_memberships')->update(['authority_version' => 9223372036854775807]));
        $this->denied(fn () => DB::table('users')->update(['email_binding_version' => 1.5]));
    }

    public function test_readiness_requires_enforcement_and_rejects_unknown_objects(): void
    {
        $this->activate();
        $this->assertTrue(SE::ready());
        DB::statement('PRAGMA recursive_triggers=OFF');
        $this->assertFalse(SE::ready());
        DB::statement('PRAGMA recursive_triggers=ON');
        DB::unprepared('CREATE TABLE fixture_unexpected (id INTEGER)');
        $this->assertFalse(SE::ready());
    }

    public function test_orphan_audit_and_invalid_transitions_are_denied(): void
    {
        $this->activate();
        $this->denied(fn () => $this->transition());
        $this->denied(fn () => DB::table('audit_logs')->insert(['id' => $this->evidence['audit_event_id'], 'event_type' => 'application.submitted', 'actor_user_id' => $this->evidence['actor_user_id'], 'merchant_id' => $this->evidence['merchant_id'], 'application_id' => $this->evidence['application_id'], 'request_id' => $this->evidence['submission_request_id'], 'metadata' => '{}', 'occurred_at' => $this->evidence['submitted_at']]));
        $this->bundle();
        $this->denied(fn () => DB::table('merchant_applications')->update(['status' => 'draft']));
        $this->denied(fn () => DB::table('merchant_applications')->update(['submitted_at' => '2000-01-01 00:00:00']));
        $this->denied(fn () => DB::table('merchant_applications')->delete());
    }

    public function test_email_and_membership_change_away_back_require_monotonic_cas(): void
    {
        $this->activate();
        $user = $this->evidence['actor_user_id'];
        $email = DB::table('users')->where('id', $user)->value('email');
        $verified = DB::table('users')->where('id', $user)->value('email_verified_at');
        $this->denied(fn () => DB::table('users')->where('id', $user)->update(['email_binding_version' => 2]));
        $this->denied(fn () => DB::table('users')->where('id', $user)->update(['email' => 'changed@fixture.test']));
        DB::table('users')->where('id', $user)->update(['email' => 'changed@fixture.test', 'email_verified_at' => null, 'email_binding_version' => 2]);
        DB::table('users')->where('id', $user)->update(['email' => $email, 'email_verified_at' => null, 'email_binding_version' => 3]);
        DB::table('users')->where('id', $user)->update(['email_verified_at' => $verified, 'email_binding_version' => 4]);
        $this->assertSame(4, DB::table('users')->where('id', $user)->value('email_binding_version'));
        $this->denied(fn () => $this->bundle());
        $this->evidence['email_binding_version'] = 4;
        $member = $this->evidence['membership_id'];
        $this->denied(fn () => DB::table('merchant_memberships')->where('id', $member)->update(['role' => 'manager']));
        DB::table('merchant_memberships')->where('id', $member)->update(['role' => 'manager', 'authority_version' => 2]);
        DB::table('merchant_memberships')->where('id', $member)->update(['role' => 'owner', 'authority_version' => 3]);
        $this->denied(fn () => $this->bundle());
        $this->evidence['authority_version'] = 3;
        $this->bundle();
        $this->assertSame(1, DB::table(SE::TABLE)->count());
    }

    public function test_unsupported_security_writers_and_unversioned_profiles_are_held(): void
    {
        $this->activate();
        $this->denied(fn () => DB::table('users')->update(['account_status' => 'suspended']));
        $this->denied(fn () => DB::table('users')->update(['auth_version' => 2]));
        $this->denied(fn () => DB::table('merchants')->update(['business_name' => 'changed']));
        DB::table('merchants')->update(['business_name' => 'changed', 'profile_version' => 2]);
        $this->denied(fn () => $this->bundle());
    }

    public function test_lineage_expiry_revocation_and_missing_identity_are_held(): void
    {
        $this->activate();
        $this->evidence['submitted_at_ms'] = $this->time + 1800002;
        $this->evidence['submitted_at'] = VerificationAuditReconciler::occurredAt($this->evidence['submitted_at_ms']);
        $this->denied(fn () => $this->bundle());
        $this->evidence['submitted_at_ms'] = $this->time + 3;
        $this->evidence['submitted_at'] = VerificationAuditReconciler::occurredAt($this->time + 3);
        DB::table('contact_verification_proofs')->update(['state' => 'revoked', 'state_version' => 2, 'revoked_at' => $this->time + 3, 'reason_code' => 'operator_revoked']);
        $this->denied(fn () => $this->bundle());
    }

    public function test_legacy_rows_and_original_schema_objects_are_preserved(): void
    {
        $legacy = MerchantApplication::factory()->create(['merchant_id' => $this->evidence['merchant_id'], 'status' => 'rejected', 'profile_snapshot' => ['fixture' => 'opaque historic content'], 'submitted_at' => now(), 'submitted_by_user_id' => $this->evidence['actor_user_id']]);
        $tables = ['merchant_applications', 'contact_verification_proofs', 'merchant_identity_records', 'merchant_contact_versions', 'audit_logs'];
        $before = array_map(fn (string $table): array => DB::select('SELECT * FROM '.$table.' ORDER BY rowid'), $tables);
        $this->activate();
        $this->assertSame(json_encode($before), json_encode(array_map(fn (string $table): array => DB::select('SELECT * FROM '.$table.' ORDER BY rowid'), $tables)));
        SE::assertFrozen();
        $this->assertSame(0, DB::table(SE::TABLE)->count());
        $this->denied(fn () => DB::table('merchant_applications')->where('id', $legacy->id)->update(['status' => 'draft']));
    }

    public function test_migration_fault_rolls_back_columns_objects_and_ledger(): void
    {
        config(['se1.selected' => realpath(SE::artifact()), 'se1.fixture_checkpoint' => function (string $name): void {
            if ($name === SE::TABLE) {
                throw new LogicException('fixture migration interruption');
            }
        }]);
        try {
            SE::install();
            $this->fail('Interruption must propagate.');
        } catch (LogicException $error) {
            $this->assertSame('fixture migration interruption', $error->getMessage());
        }
        $this->assertFalse(SE::ready());
        $this->assertNull(DB::selectOne('SELECT sql FROM sqlite_master WHERE name=?', [SE::TABLE]));
        $this->assertNotContains('email_binding_version', array_column(DB::select('PRAGMA table_info(users)'), 'name'));
        $this->assertSame(0, DB::table('migrations')->where('migration', SE::MIGRATION)->count());
        SE::assertFrozen();
    }

    public function test_ledger_gap_drift_wrong_selection_and_down_fail_closed(): void
    {
        $this->assertFalse(SE::ready());
        try {
            SE::install();
            $this->fail('Explicit selection is required.');
        } catch (LogicException $error) {
            $this->assertStringContainsString('disabled', $error->getMessage());
        }
        config(['se1.selected' => realpath(SE::artifact())]);
        SE::install();
        $this->assertFalse(SE::ready());
        try {
            SE::install();
            $this->fail('Partial activation must not be adopted.');
        } catch (LogicException $error) {
            $this->assertStringContainsString('partial', $error->getMessage());
        }
        DB::table('migrations')->insert(['migration' => SE::MIGRATION, 'batch' => 100]);
        $this->assertTrue(SE::ready());
        DB::unprepared('DROP TRIGGER se1_evidence_delete');
        $this->assertFalse(SE::ready());
        $artifact = require SE::artifact();
        try {
            $artifact->down();
            $this->fail('Down is unavailable.');
        } catch (LogicException $error) {
            $this->assertStringContainsString('disabled', $error->getMessage());
        }
    }

    public function test_two_generated_database_connections_contend_before_bundle_writes(): void
    {
        $this->activate();
        $path = tempnam(sys_get_temp_dir(), 'xlipa-se1-');
        $this->assertNotFalse($path);
        $this->assertSame(realpath(sys_get_temp_dir()), realpath(dirname($path)));
        unlink($path);
        $other = null;
        try {
            DB::statement('VACUUM INTO '.DB::connection()->getPdo()->quote($path));
            DB::purge('sqlite');
            config(['database.connections.sqlite.database' => $path, 'database.connections.sqlite.busy_timeout' => 0]);
            DB::statement('PRAGMA recursive_triggers=ON');
            $other = new \PDO('sqlite:'.$path, options: [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
            $other->exec('PRAGMA busy_timeout=0');
            $other->exec('BEGIN IMMEDIATE');
            $before = $this->state();
            try {
                $this->bundle();
                $this->fail('Competing writer must fail.');
            } catch (QueryException $error) {
                $this->assertContains($error->errorInfo[1], [5, 6]);
            }
            $this->assertSame($before, $this->state());
            $other->exec('ROLLBACK');
            $this->bundle();
            $this->assertSame(1, DB::table(SE::TABLE)->count());
            $this->denied(fn () => $this->bundle());
        } finally {
            $other = null;
            DB::purge('sqlite');
            config(['database.connections.sqlite.database' => ':memory:']);
            foreach ([$path, $path.'-journal', $path.'-wal', $path.'-shm'] as $temporary) {
                if (is_file($temporary)) {
                    unlink($temporary);
                }
            }
        }
    }
}
