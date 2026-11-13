<?php

namespace Tests\Feature;

use App\Actions\Merchants\BusinessNameCompletionSchema as BNC;
use App\Actions\Merchants\ComposedSubmissionSchema as Composed;
use App\Actions\Merchants\SubmissionEvidenceSchema as SE;
use App\Actions\Merchants\SubmissionOutcomeUnknown;
use App\Actions\Merchants\SubmissionTransaction;
use App\Actions\Merchants\VerificationAuditReconciler;
use App\Models\Merchant;
use App\Models\User;
use Closure;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use LogicException;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\B32CRehearsal;
use Tests\TestCase;

/** Generated fictional schema fixtures, with no HTTP completion endpoint or operational adapters. */
class BusinessNameCompletionSchemaTest extends TestCase
{
    private array $record;

    private array $temporaryPaths = [];

    protected function setUp(): void
    {
        parent::setUp();
        B32CRehearsal::foundation();
        B32CRehearsal::activate();
        Notification::fake();
        DB::statement('PRAGMA recursive_triggers=ON');
        $owner = User::factory()->create();
        $merchant = Merchant::factory()->create(['created_by_user_id' => $owner->id, 'business_name' => '']);
        config(['se1.selected' => realpath(SE::artifact())]);
        $this->assertSame(0, Artisan::call('migrate', ['--database' => 'sqlite', '--path' => [SE::artifact()], '--realpath' => true, '--no-interaction' => true]));
        $this->assertTrue(SE::ready());
        $this->record = [
            'id' => (string) Str::ulid(), 'contract_version' => 'business-name-completion-v1',
            'merchant_id' => $merchant->id, 'application_id' => $merchant->applications()->value('id'), 'attempt_number' => 1,
            'actor_user_id' => $owner->id, 'membership_id' => $merchant->memberships()->value('id'),
            'auth_version' => 1, 'email_binding_version' => 1, 'authority_version' => 1,
            'expected_profile_version' => 1, 'resulting_profile_version' => 2,
            'completion_request_id' => (string) Str::uuid(), 'occurred_at_ms' => 1794528000000,
            'occurred_at' => VerificationAuditReconciler::occurredAt(1794528000000), 'record_policy_version' => 'fixture.redacted',
        ];
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite');
        foreach ($this->temporaryPaths as $path) {
            foreach (['', '-journal', '-wal', '-shm'] as $suffix) {
                if (is_file($path.$suffix)) {
                    unlink($path.$suffix);
                }
            }
        }
        parent::tearDown();
    }

    private function install(): void
    {
        config(['bnc1.selected' => realpath(BNC::artifact())]);
        BNC::install();
        $this->assertTrue(Composed::ready());
    }

    private function mutate(string $conflict = ''): void
    {
        $this->assertSame(1, DB::update("UPDATE $conflict merchants SET business_name=?,profile_version=?,updated_at=? WHERE id=? AND profile_version=? AND business_name=''", ['Fictional Kijiji Shop', $this->record['resulting_profile_version'], $this->record['occurred_at'], $this->record['merchant_id'], $this->record['expected_profile_version']]));
    }

    private function complete(): void
    {
        app(SubmissionTransaction::class)->run(function (): void {
            DB::table(BNC::TABLE)->insert($this->record);
            $this->mutate();
        });
    }

    private function fingerprint(): string
    {
        $state = DB::select('SELECT type,name,tbl_name,sql FROM sqlite_master ORDER BY name');
        $rows = [];
        foreach (DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name") as $table) {
            $rows[$table->name] = DB::select('SELECT * FROM '.$table->name.' ORDER BY rowid');
        }

        return hash('sha256', json_encode([$state, $rows], JSON_THROW_ON_ERROR));
    }

    private function denied(Closure $operation): void
    {
        $before = $this->fingerprint();
        try {
            $operation();
            $this->fail('Expected SQL constraint denial.');
        } catch (PDOException $error) {
            $this->assertSame('23000', $error->getCode());
        } finally {
            if (DB::connection()->transactionLevel() > 0) {
                DB::rollBack();
            }
        }
        $this->assertSame($before, $this->fingerprint());
        $this->assertSame(0, DB::connection()->transactionLevel());
    }

    public function test_complete_bundle_and_strict_composition(): void
    {
        $this->install();
        $this->assertFalse(SE::ready());
        $this->complete();
        $this->assertSame(2, DB::table('merchants')->value('profile_version'));
        $this->assertSame(2, DB::table('merchant_applications')->value('profile_version'));
        $this->assertSame('Fictional Kijiji Shop', DB::table('merchants')->value('business_name'));
        $this->assertSame('draft', DB::table('merchant_applications')->value('status'));
        $this->assertSame($this->record, (array) DB::table(BNC::TABLE)->first());
        $this->assertSame(1, DB::table(BNC::WITNESSES)->count());
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
        $this->assertSame(0, DB::table('audit_logs')->where('event_type', 'application.submitted')->count());
        $before = $this->fingerprint();
        DB::table('merchants')->update(['business_name' => 'Fictional Kijiji Shop']);
        $this->assertSame($before, $this->fingerprint());
        $this->denied(fn () => DB::table('merchants')->update(['profile_version' => 3]));
        $this->denied(fn () => DB::table(BNC::TABLE)->insert([...$this->record, 'id' => (string) Str::ulid(), 'expected_profile_version' => 2, 'resulting_profile_version' => 3]));
        $this->assertTrue(Composed::ready());
    }

    public function test_record_only_cannot_commit_or_autocommit(): void
    {
        $this->install();
        $this->denied(fn () => DB::table(BNC::TABLE)->insert($this->record));
        $this->denied(function (): void {
            DB::beginTransaction();
            DB::table(BNC::TABLE)->insert($this->record);
            $this->assertCount(1, DB::select('PRAGMA foreign_key_check'));
            DB::commit();
        });
    }

    public function test_merchant_only_draft_first_and_fake_witness_are_denied(): void
    {
        $this->install();
        $this->denied(fn () => $this->mutate());
        $this->denied(fn () => DB::table(BNC::WITNESSES)->insert(['completion_id' => $this->record['id']]));
        $this->denied(function (): void {
            DB::beginTransaction();
            DB::table(BNC::TABLE)->insert($this->record);
            DB::table(BNC::WITNESSES)->insert(['completion_id' => $this->record['id']]);
        });
        $this->denied(fn () => DB::table('merchant_applications')->update(['profile_version' => 2]));
    }

    public static function malformed(): array
    {
        return [
            ['expected_profile_version', 0], ['expected_profile_version', PHP_INT_MAX], ['resulting_profile_version', 3],
            ['actor_user_id', 999999], ['membership_id', 999999], ['authority_version', 2], ['auth_version', 2], ['email_binding_version', 2],
            ['attempt_number', 2], ['completion_request_id', 'ABCDEF00-0000-0000-0000-000000000000'],
            ['occurred_at_ms', -1], ['occurred_at', '2026-11-13 00:00:00.001'], ['record_policy_version', 'private name'],
            ['id', 'not-a-ulid'], ['merchant_id', '00000000000000000000000000'], ['application_id', '00000000000000000000000000'],
        ];
    }

    #[DataProvider('malformed')]
    public function test_typed_record_and_authority_denials(string $field, mixed $value): void
    {
        $this->install();
        $this->record[$field] = $value;
        $this->denied(fn () => DB::table(BNC::TABLE)->insert($this->record));
    }

    public static function invalidNames(): array
    {
        return [[''], ['   '], ["Name\n"], ["Name\0"], [str_repeat('A', 201)]];
    }

    #[DataProvider('invalidNames')]
    public function test_invalid_name_cannot_satisfy_witness(string $name): void
    {
        $this->install();
        $this->denied(function () use ($name): void {
            DB::beginTransaction();
            DB::table(BNC::TABLE)->insert($this->record);
            DB::table('merchants')->update(['business_name' => $name, 'profile_version' => 2, 'updated_at' => $this->record['occurred_at']]);
            DB::commit();
        });
    }

    public static function conflictPolicies(): array
    {
        return [[''], ['OR IGNORE'], ['OR REPLACE'], ['OR FAIL'], ['OR ABORT'], ['OR ROLLBACK']];
    }

    #[DataProvider('conflictPolicies')]
    public function test_duplicate_replacement_and_immutable_history(string $policy): void
    {
        $this->install();
        $this->complete();
        $columns = implode(',', array_keys($this->record));
        $parameters = implode(',', array_fill(0, count($this->record), '?'));
        $this->denied(fn () => DB::insert('INSERT '.$policy.' INTO '.BNC::TABLE." ($columns) VALUES ($parameters)", array_values($this->record)));
        $this->denied(fn () => DB::insert('INSERT '.$policy.' INTO '.BNC::WITNESSES.'(completion_id) VALUES (?)', [$this->record['id']]));
        foreach ([BNC::TABLE, BNC::WITNESSES] as $table) {
            $field = $table === BNC::TABLE ? 'id' : 'completion_id';
            $this->denied(fn () => DB::update("UPDATE $policy $table SET $field=$field"));
            $this->denied(fn () => DB::delete("DELETE FROM $table"));
        }
    }

    public function test_duplicate_pending_request_and_non_name_side_effects_denied(): void
    {
        $this->install();
        $this->denied(function (): void {
            DB::beginTransaction();
            DB::table(BNC::TABLE)->insert($this->record);
            DB::table(BNC::TABLE)->insert([...$this->record, 'id' => (string) Str::ulid()]);
        });
        $this->denied(function (): void {
            DB::beginTransaction();
            DB::table(BNC::TABLE)->insert($this->record);
            DB::table('merchants')->update(['business_name' => 'Fictional', 'profile_version' => 2, 'business_type' => 'changed', 'updated_at' => $this->record['occurred_at']]);
        });
    }

    public function test_future_profile_change_and_authority_epoch_do_not_pin_history(): void
    {
        $this->install();
        $this->complete();
        $history = DB::table(BNC::TABLE)->get()->toJson();
        app(SubmissionTransaction::class)->run(function (): void {
            DB::table('merchants')->update(['city' => 'Fictional Town', 'profile_version' => 3]);
            DB::table('merchant_applications')->update(['profile_version' => 3]);
            DB::table('merchant_memberships')->update(['status' => 'revoked', 'revoked_at' => '2026-11-13 01:00:00', 'authority_version' => 2]);
        });
        $this->assertSame($history, DB::table(BNC::TABLE)->get()->toJson());
        $this->assertTrue(Composed::ready());
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
    }

    public function test_later_valid_empty_state_allows_a_distinct_completion_and_rejects_old_uuid(): void
    {
        $this->install();
        $this->complete();
        app(SubmissionTransaction::class)->run(function (): void {
            DB::table('merchants')->update(['business_name' => '', 'profile_version' => 3]);
            DB::table('merchant_applications')->update(['profile_version' => 3]);
        });
        $this->record = [...$this->record, 'id' => (string) Str::ulid(), 'expected_profile_version' => 3, 'resulting_profile_version' => 4];
        $this->denied(fn () => DB::table(BNC::TABLE)->insert($this->record));
        $this->record['completion_request_id'] = (string) Str::uuid();
        $this->complete();
        $this->assertSame(2, DB::table(BNC::TABLE)->count());
        $this->assertSame(2, DB::table(BNC::WITNESSES)->count());
        $this->assertSame(4, DB::table('merchant_applications')->value('profile_version'));
        $this->assertTrue(Composed::ready());
    }

    public function test_savepoint_and_multirow_insert_cannot_leave_an_orphan(): void
    {
        $this->install();
        $this->denied(function (): void {
            DB::beginTransaction();
            DB::statement('SAVEPOINT fictional');
            DB::table(BNC::TABLE)->insert($this->record);
            DB::statement('RELEASE fictional');
            DB::commit();
        });
        $this->denied(function (): void {
            DB::beginTransaction();
            DB::table(BNC::TABLE)->insert([$this->record, [...$this->record, 'id' => (string) Str::ulid()]]);
        });
    }

    public function test_completed_draft_can_submit_and_submitted_evidence_remains_frozen(): void
    {
        $this->install();
        $this->complete();
        $time = $this->record['occurred_at_ms'] + 1000;
        $merchant = $this->record['merchant_id'];
        $actor = $this->record['actor_user_id'];
        $identity = (string) Str::ulid();
        $contact = (string) Str::ulid();
        $challenge = (string) Str::ulid();
        $proof = (string) Str::ulid();
        DB::table('merchant_identity_records')->insert(['id' => $identity, 'merchant_id' => $merchant, 'version' => 1, 'subject_claim' => 'individual_owner', 'nin_ciphertext' => 'FICTIONAL-OPAQUE-IDENTITY', 'encryption_key_ref' => 'fixture.nin', 'envelope_version' => 1, 'syntax_policy_version' => 'fixture.syntax', 'syntax_checked_at' => $time, 'declaration_version' => 'fixture.declaration', 'notice_version' => 'fixture.notice', 'declared_by_user_id' => $actor, 'declared_at' => $time]);
        DB::table('merchant_contact_versions')->insert(['id' => $contact, 'merchant_id' => $merchant, 'actor_user_id' => $actor, 'purpose' => 'application_phone', 'version' => 1, 'phone_ciphertext' => 'FICTIONAL-OPAQUE-CONTACT', 'phone_key_ref' => 'fixture.phone', 'envelope_version' => 1, 'target_fingerprint' => str_repeat('a', 64), 'fingerprint_key_ref' => 'fixture.fp', 'created_at' => $time]);
        DB::table('merchant_onboarding_contexts')->insert(['merchant_id' => $merchant, 'current_identity_id' => $identity, 'current_contact_id' => $contact, 'contact_actor_user_id' => $actor, 'contact_purpose' => 'application_phone', 'updated_at' => $time]);
        $base = ['merchant_id' => $merchant, 'actor_user_id' => $actor, 'contact_id' => $contact, 'purpose' => 'application_phone'];
        DB::table('phone_otp_challenges')->insert(['id' => $challenge, ...$base, 'active_slot' => 1, 'created_at' => $time, 'expires_at' => $time + 300000, 'transport_mode' => 'live']);
        DB::table('phone_otp_challenges')->where('id', $challenge)->update(['state' => 'dispatching', 'state_version' => 2, 'code_digest' => str_repeat('b', 64), 'digest_key_ref' => 'fixture.otp', 'digest_created_at' => $time]);
        DB::table('otp_delivery_intents')->insert(['id' => (string) Str::ulid(), ...$base, 'challenge_id' => $challenge, 'transport_mode' => 'live', 'request_id' => (string) Str::uuid(), 'requested_at' => $time]);
        DB::table('otp_delivery_intents')->where('challenge_id', $challenge)->update(['state' => 'claimed', 'claim_version' => 2, 'claimed_at' => $time, 'lease_expires_at' => $time + 30000]);
        DB::table('otp_delivery_intents')->where('challenge_id', $challenge)->update(['state' => 'accepted', 'claim_version' => 3, 'completed_at' => $time + 1]);
        DB::table('phone_otp_challenges')->where('id', $challenge)->update(['state' => 'active', 'state_version' => 3]);
        DB::table('phone_otp_challenges')->where('id', $challenge)->update(['state' => 'consumed', 'state_version' => 4, 'active_slot' => null, 'terminal_at' => $time + 2]);
        DB::table('contact_verification_proofs')->insert(['id' => $proof, ...$base, 'challenge_id' => $challenge, 'channel' => 'sms', 'provenance' => 'live', 'policy_version' => 'application-phone-proof-v1', 'verified_at' => $time + 2, 'expires_at' => $time + 1800002]);
        $evidence = ['application_id' => $this->record['application_id'], 'merchant_id' => $merchant, 'contract_version' => 'submission-evidence-v1', 'actor_user_id' => $actor, 'membership_id' => $this->record['membership_id'], 'auth_version' => 1, 'authority_version' => 1, 'email_binding_version' => 1, 'attempt_number' => 1, 'profile_version' => 2, 'context_version' => 1, 'identity_id' => $identity, 'identity_version' => 1, 'contact_id' => $contact, 'contact_version' => 1, 'purpose' => 'application_phone', 'proof_id' => $proof, 'submission_request_id' => (string) Str::uuid(), 'submitted_at_ms' => $time + 3, 'submitted_at' => VerificationAuditReconciler::occurredAt($time + 3), 'audit_event_id' => (string) Str::ulid(), 'audit_event_type' => 'application.submitted', 'evidence_ciphertext' => 'FICTIONAL-OPAQUE-EVIDENCE', 'evidence_key_ref' => 'fixture.evidence', 'binding_key_ref' => 'fixture.binding', 'retention_policy_version' => 'fixture.retention', 'envelope_version' => 1];
        app(SubmissionTransaction::class)->run(function () use ($evidence, $proof, $time, $actor): void {
            DB::table(SE::TABLE)->insert($evidence);
            DB::table('contact_verification_proofs')->where('id', $proof)->update(['state' => 'consumed', 'state_version' => 2, 'consumed_application_id' => $this->record['application_id'], 'consumed_at' => $time + 3]);
            DB::table('merchant_applications')->update(['status' => 'pending_review', 'submitted_at' => $evidence['submitted_at'], 'submitted_by_user_id' => $actor]);
        });
        $history = DB::table(SE::TABLE)->get()->toJson();
        $completion = DB::table(BNC::TABLE)->get()->toJson();
        DB::table('merchants')->update(['city' => 'Later fictional town', 'profile_version' => 3]);
        $this->denied(fn () => DB::table('merchant_applications')->update(['profile_version' => 3]));
        $this->denied(fn () => DB::table(SE::TABLE)->update(['evidence_ciphertext' => 'changed']));
        DB::table('merchant_applications')->update(['status' => 'information_requested']);
        $this->assertSame(2, DB::table('merchant_applications')->value('profile_version'));
        $this->assertSame($history, DB::table(SE::TABLE)->get()->toJson());
        $this->assertSame($completion, DB::table(BNC::TABLE)->get()->toJson());
        $this->assertTrue(Composed::ready());
    }

    public function test_manifest_tampering_and_duplicate_json_keys_are_denied(): void
    {
        $this->install();
        $path = Composed::manifestPath();
        $original = file_get_contents($path);
        try {
            file_put_contents($path, str_replace('"BNC-1.0"', '"unknown"', $original));
            $this->assertFalse(Composed::ready());
            file_put_contents($path, str_replace('"version": "BNC-1.0",', '"version": "BNC-1.0", "version": "BNC-1.0",', $original));
            $this->assertFalse(Composed::ready());
        } finally {
            file_put_contents($path, $original);
        }
        $this->assertTrue(Composed::ready());
    }

    public static function failurePoints(): array
    {
        return array_map(fn (string $name): array => [$name], [...array_keys(BNC::objects()), 'ledger']);
    }

    #[DataProvider('failurePoints')]
    public function test_migration_fault_rolls_back_schema_and_ledger(string $point): void
    {
        $before = $this->fingerprint();
        config(['bnc1.selected' => realpath(BNC::artifact()), 'bnc1.fixture_checkpoint' => function (string $name) use ($point): void {
            if ($name === $point) {
                throw new RuntimeException('Fictional migration fault');
            }
        }]);
        try {
            BNC::install();
            $this->fail('Expected migration fault.');
        } catch (RuntimeException $error) {
            $this->assertSame('Fictional migration fault', $error->getMessage());
        }
        $this->assertSame($before, $this->fingerprint());
        $this->assertTrue(SE::ready());
        $this->assertFalse(Composed::ready());
    }

    public static function faults(): array
    {
        return [['record'], ['draft'], ['witness'], ['final']];
    }

    #[DataProvider('faults')]
    public function test_completion_faults_roll_back_whole_bundle(string $point): void
    {
        $this->install();
        if ($point === 'draft') {
            DB::unprepared("CREATE TRIGGER fixture_fault BEFORE UPDATE ON merchant_applications BEGIN SELECT RAISE(ABORT,'fictional draft fault'); END");
        }
        if ($point === 'witness') {
            DB::unprepared('CREATE TRIGGER fixture_fault BEFORE INSERT ON '.BNC::WITNESSES." BEGIN SELECT RAISE(ABORT,'fictional witness fault'); END");
        }
        $before = $this->fingerprint();
        try {
            app(SubmissionTransaction::class)->run(function () use ($point): void {
                DB::table(BNC::TABLE)->insert($this->record);
                if ($point === 'record') {
                    throw new RuntimeException('fictional record fault');
                }
                $this->mutate();
                throw new RuntimeException('fictional final fault');
            });
            $this->fail('Expected fault');
        } catch (PDOException|RuntimeException $error) {
            $this->assertStringContainsString('fictional', $error->getMessage());
        }
        $this->assertSame($before, $this->fingerprint());
    }

    public static function tampering(): array
    {
        return [
            ['CREATE TABLE unexpected(value TEXT)'], ['CREATE TEMP TABLE unexpected(value TEXT)'],
            ["ATTACH DATABASE ':memory:' AS extra"], ['DROP TRIGGER bnc1_witness_insert'],
            ['DROP INDEX bnc1_transition_tuple'], ["DELETE FROM migrations WHERE migration='".BNC::MIGRATION."'"],
            ["UPDATE migrations SET batch=6 WHERE migration='".BNC::MIGRATION."'"],
            ["INSERT INTO migrations(migration,batch) VALUES ('unknown',6)"],
            ['PRAGMA foreign_keys=OFF'], ['PRAGMA recursive_triggers=OFF'], ['PRAGMA ignore_check_constraints=ON'], ['ANALYZE'],
        ];
    }

    #[DataProvider('tampering')]
    public function test_readiness_denies_schema_ledger_and_enforcement_tampering(string $sql): void
    {
        $this->install();
        DB::unprepared($sql);
        $this->assertFalse(Composed::ready());
    }

    public function test_trigger_creation_order_is_irrelevant(): void
    {
        $this->install();
        $triggers = DB::select("SELECT name,sql FROM sqlite_master WHERE type='trigger' ORDER BY name");
        foreach ($triggers as $trigger) {
            DB::unprepared('DROP TRIGGER '.$trigger->name);
        }
        foreach (array_reverse($triggers) as $trigger) {
            DB::unprepared($trigger->sql);
        }
        $this->assertTrue(Composed::ready());
        $this->complete();
        $this->assertSame(1, DB::table(BNC::WITNESSES)->count());
    }

    public function test_artifact_direct_up_and_down_are_denied(): void
    {
        $before = $this->fingerprint();
        foreach (['up', 'down'] as $method) {
            try {
                (require BNC::artifact())->$method();
                $this->fail('Expected isolated runner/downgrade denial.');
            } catch (LogicException) {
                $this->assertSame($before, $this->fingerprint());
            }
        }
        $this->assertFalse(Composed::ready());
    }

    private function ownedFile(): string
    {
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'xlipa-bnc-fictional-'.bin2hex(random_bytes(12)).'.sqlite';
        $this->temporaryPaths[] = $path;
        DB::unprepared('VACUUM INTO '.DB::connection()->getPdo()->quote($path));

        return $path;
    }

    public function test_two_writers_have_one_winner_without_retries(): void
    {
        $this->install();
        $path = $this->ownedFile();
        $first = new PDO('sqlite:'.$path);
        $second = new PDO('sqlite:'.$path);
        foreach ([$first, $second] as $pdo) {
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->exec('PRAGMA foreign_keys=ON; PRAGMA recursive_triggers=ON; PRAGMA busy_timeout=0');
        }
        $first->beginTransaction();
        $first->exec('UPDATE merchant_onboarding_contexts SET context_version=context_version WHERE 0');
        $second->beginTransaction();
        try {
            $second->exec('UPDATE merchant_onboarding_contexts SET context_version=context_version WHERE 0');
            $this->fail('Expected writer contention.');
        } catch (PDOException $error) {
            $this->assertSame(5, $error->errorInfo[1]);
            $second->rollBack();
        }
        $columns = implode(',', array_keys($this->record));
        $parameters = implode(',', array_fill(0, count($this->record), '?'));
        $insert = 'INSERT INTO '.BNC::TABLE." ($columns) VALUES ($parameters)";
        $first->prepare($insert)->execute(array_values($this->record));
        $first->prepare('UPDATE merchants SET business_name=?,profile_version=2,updated_at=? WHERE id=?')->execute(['Fictional winner', $this->record['occurred_at'], $this->record['merchant_id']]);
        $first->commit();
        $second->beginTransaction();
        try {
            $second->prepare($insert)->execute(array_values([...$this->record, 'id' => (string) Str::ulid(), 'completion_request_id' => (string) Str::uuid()]));
            $this->fail('Stale second writer must fail.');
        } catch (PDOException $error) {
            $this->assertSame('23000', $error->getCode());
            $second->rollBack();
        }
        $this->assertSame(1, (int) $second->query('SELECT count(*) FROM '.BNC::TABLE)->fetchColumn());
        $this->assertSame(2, (int) $second->query('SELECT profile_version FROM merchants')->fetchColumn());
        $first = $second = null;
    }

    public static function acknowledgements(): array
    {
        return [[false], [true]];
    }

    #[DataProvider('acknowledgements')]
    public function test_unknown_commit_discards_connection_without_replay(bool $after): void
    {
        $this->install();
        $path = $this->ownedFile();
        DB::purge('sqlite');
        config(['database.connections.sqlite.database' => $path]);
        DB::statement('PRAGMA recursive_triggers=ON');
        $original = DB::connection();
        $transaction = new class($after) extends SubmissionTransaction
        {
            public int $attempts = 0;

            public function __construct(private bool $after) {}

            protected function commit(Connection $connection): void
            {
                $this->attempts++;
                if ($this->after) {
                    parent::commit($connection);
                }
                throw new RuntimeException('Fictional missing COMMIT acknowledgement');
            }
        };
        $calls = 0;
        try {
            $transaction->run(function () use (&$calls): void {
                $calls++;
                DB::table(BNC::TABLE)->insert($this->record);
                $this->mutate();
            });
            $this->fail('Expected explicit unknown.');
        } catch (SubmissionOutcomeUnknown) {
            $this->assertSame(1, $calls);
            $this->assertSame(1, $transaction->attempts);
            $this->assertNotSame($original, DB::connection());
        }
        DB::statement('PRAGMA recursive_triggers=ON');
        $this->assertTrue(Composed::ready());
        $this->assertSame($after ? 1 : 0, DB::table(BNC::TABLE)->count());
        $this->assertSame($after ? 2 : 1, DB::table('merchants')->value('profile_version'));
    }
}
