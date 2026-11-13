<?php

namespace App\Actions\Merchants;

use Illuminate\Support\Facades\DB;
use LogicException;

/** Isolated BNC schema only. This supplies no authenticated completion command. */
final class BusinessNameCompletionSchema
{
    public const MIGRATION = '2026_11_13_210000_add_bnc1_business_name_completion';

    public const TABLE = 'merchant_business_name_completions';

    public const WITNESSES = 'merchant_business_name_completion_witnesses';

    private static bool $installing = false;

    public static function artifact(): string
    {
        return database_path('activation/bnc1/'.self::MIGRATION.'.php');
    }

    private static function integer(string $name, int $minimum = 1): string
    {
        return "$name INTEGER NOT NULL CHECK(typeof($name)='integer' AND $name >= $minimum)";
    }

    private static function text(string $name, int $maximum): string
    {
        return "$name TEXT NOT NULL CHECK(typeof($name)='text' AND length(CAST($name AS BLOB)) BETWEEN 1 AND $maximum AND instr($name,char(0))=0)";
    }

    private static function ulid(string $name, bool $parent = true): string
    {
        $alphabet = '0123456789ABCDEFGHJKMNPQRSTVWXYZ'.($parent ? 'abcdefghjkmnpqrstvwxyz' : '');

        return self::text($name, 26)." CHECK(length($name)=26 AND substr($name,1,1) BETWEEN '0' AND '7' AND $name NOT GLOB '*[^$alphabet]*')";
    }

    private static function trigger(string $name, string $event, string $table, string $condition = '', string $body = ''): string
    {
        return "CREATE TRIGGER $name $event ON $table".($condition === '' ? '' : " WHEN $condition").' BEGIN '.($body === '' ? "SELECT RAISE(ABORT,'BNC1 invariant denied');" : $body).' END';
    }

    private static function authority(): string
    {
        return "EXISTS(SELECT 1 FROM users u JOIN merchant_memberships m ON m.user_id=u.id WHERE u.id=e.actor_user_id AND u.account_status='active' AND u.email_verified_at IS NOT NULL AND u.auth_version=e.auth_version AND u.email_binding_version=e.email_binding_version AND m.id=e.membership_id AND m.merchant_id=e.merchant_id AND m.role='owner' AND m.status='active' AND m.revoked_at IS NULL AND m.authority_version=e.authority_version)";
    }

    private static function draft(string $version): string
    {
        return "a.id=e.application_id AND a.merchant_id=e.merchant_id AND a.attempt_number=e.attempt_number AND a.current_slot='1' AND a.status='draft' AND a.profile_version=e.$version AND a.profile_snapshot IS NULL AND a.submitted_at IS NULL AND a.submitted_by_user_id IS NULL AND a.reviewed_at IS NULL AND a.reviewed_by_user_id IS NULL AND a.public_feedback IS NULL AND a.internal_review_notes IS NULL AND NOT EXISTS(SELECT 1 FROM merchant_application_submission_evidence s WHERE s.application_id=a.id) AND NOT EXISTS(SELECT 1 FROM audit_logs z WHERE z.application_id=a.id AND z.event_type='application.submitted')";
    }

    private static function validName(string $value): string
    {
        $controls = [];
        for ($code = 0; $code <= 31; $code++) {
            $controls[] = "instr($value,char($code))=0";
        }
        $controls[] = "instr($value,char(127))=0";

        return "typeof($value)='text' AND length($value) BETWEEN 1 AND 200 AND $value=trim($value,char(32,9,10,11,12,13)) AND ".implode(' AND ', $controls);
    }

    /** Exact additive definitions, preserving every frozen trigger. */
    public static function objects(): array
    {
        $table = self::TABLE;
        $witnesses = self::WITNESSES;
        $columns = [self::ulid('id', false).' PRIMARY KEY', self::text('contract_version', 50)." CHECK(contract_version='business-name-completion-v1')", self::ulid('merchant_id'), self::ulid('application_id')];
        foreach (['attempt_number', 'actor_user_id', 'membership_id', 'auth_version', 'email_binding_version', 'authority_version', 'expected_profile_version', 'resulting_profile_version'] as $column) {
            $columns[] = self::integer($column);
        }
        $columns = [...$columns,
            self::text('completion_request_id', 36)." CHECK(length(completion_request_id)=36 AND substr(completion_request_id,9,1)='-' AND substr(completion_request_id,14,1)='-' AND substr(completion_request_id,19,1)='-' AND substr(completion_request_id,24,1)='-' AND length(replace(completion_request_id,'-',''))=32 AND replace(completion_request_id,'-','') NOT GLOB '*[^0-9a-f]*')",
            self::integer('occurred_at_ms', 0).' CHECK(occurred_at_ms<=253402300799999)',
            self::text('occurred_at', 23)." CHECK(length(occurred_at)=23 AND strftime('%Y-%m-%d %H:%M:%S',occurred_at_ms/1000,'unixepoch') IS NOT NULL AND occurred_at=strftime('%Y-%m-%d %H:%M:%S',occurred_at_ms/1000,'unixepoch')||printf('.%03d',occurred_at_ms%1000))",
            self::text('record_policy_version', 50)." CHECK(record_policy_version NOT GLOB '*[^a-z0-9._-]*')",
            'CHECK(expected_profile_version<=9223372036854775806 AND resulting_profile_version=expected_profile_version+1)',
            'FOREIGN KEY(merchant_id) REFERENCES merchants(id) ON UPDATE RESTRICT ON DELETE RESTRICT',
            'FOREIGN KEY(actor_user_id) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT',
            'FOREIGN KEY(membership_id,merchant_id,actor_user_id) REFERENCES merchant_memberships(id,merchant_id,user_id) ON UPDATE RESTRICT ON DELETE RESTRICT',
            'FOREIGN KEY(application_id,merchant_id,attempt_number) REFERENCES merchant_applications(id,merchant_id,attempt_number) ON UPDATE RESTRICT ON DELETE RESTRICT',
            "FOREIGN KEY(id) REFERENCES $witnesses(completion_id) ON UPDATE NO ACTION ON DELETE RESTRICT DEFERRABLE INITIALLY DEFERRED",
        ];
        $objects = [
            'bnc1_application_identity_tuple' => 'CREATE UNIQUE INDEX bnc1_application_identity_tuple ON merchant_applications (id,merchant_id,attempt_number)',
            $table => "CREATE TABLE $table (".implode(',', $columns).')',
            $witnesses => "CREATE TABLE $witnesses (".self::ulid('completion_id', false)." PRIMARY KEY,FOREIGN KEY(completion_id) REFERENCES $table(id) ON UPDATE RESTRICT ON DELETE RESTRICT)",
            'bnc1_request_tuple' => "CREATE UNIQUE INDEX bnc1_request_tuple ON $table (merchant_id,actor_user_id,completion_request_id)",
            'bnc1_transition_tuple' => "CREATE UNIQUE INDEX bnc1_transition_tuple ON $table (merchant_id,expected_profile_version)",
        ];
        $authority = self::authority();
        $preDraft = self::draft('expected_profile_version');
        $postDraft = self::draft('resulting_profile_version');
        $fields = ['merchant_id', 'application_id', 'attempt_number', 'actor_user_id', 'membership_id', 'auth_version', 'email_binding_version', 'authority_version', 'expected_profile_version', 'resulting_profile_version'];
        $projection = implode(',', array_map(fn (string $field): string => "NEW.$field AS $field", $fields));
        $prestate = "EXISTS(SELECT 1 FROM (SELECT $projection) e JOIN merchants b ON b.id=e.merchant_id JOIN merchant_applications a ON a.merchant_id=b.id WHERE b.business_name='' AND b.profile_version=e.expected_profile_version AND b.operational_status NOT IN ('suspended','closed') AND $preDraft AND $authority)";
        $duplicate = "EXISTS(SELECT 1 FROM $table e WHERE e.id=NEW.id OR (e.merchant_id=NEW.merchant_id AND ((e.actor_user_id=NEW.actor_user_id AND e.completion_request_id=NEW.completion_request_id) OR e.expected_profile_version=NEW.expected_profile_version OR NOT EXISTS(SELECT 1 FROM $witnesses w WHERE w.completion_id=e.id))))";
        $objects['bnc1_completion_insert'] = self::trigger('bnc1_completion_insert', 'BEFORE INSERT', $table, "NOT $prestate OR $duplicate");
        foreach (['completion' => $table, 'witness' => $witnesses] as $prefix => $parent) {
            foreach (['update' => 'BEFORE UPDATE', 'delete' => 'BEFORE DELETE'] as $suffix => $event) {
                $name = 'bnc1_'.$prefix.'_'.$suffix;
                $objects[$name] = self::trigger($name, $event, $parent);
            }
        }
        $validName = self::validName('b.business_name');
        $final = "EXISTS(SELECT 1 FROM $table e JOIN merchants b ON b.id=e.merchant_id JOIN merchant_applications a ON a.id=e.application_id WHERE e.id=NEW.completion_id AND b.profile_version=e.resulting_profile_version AND b.operational_status NOT IN ('suspended','closed') AND b.updated_at=e.occurred_at AND a.updated_at=e.occurred_at AND $validName AND $postDraft AND $authority)";
        $objects['bnc1_witness_insert'] = self::trigger('bnc1_witness_insert', 'BEFORE INSERT', $witnesses, "EXISTS(SELECT 1 FROM $witnesses WHERE completion_id=NEW.completion_id) OR NOT $final");
        $transition = "OLD.business_name='' AND NEW.business_name IS NOT OLD.business_name";
        $untouched = ['id', 'business_type', 'registration_country', 'registration_number', 'business_email', 'business_phone', 'address_line1', 'address_line2', 'city', 'region', 'country_code', 'postal_code', 'operational_status', 'verification_status', 'created_by_user_id', 'created_at'];
        $changed = implode(' OR ', array_map(fn (string $field): string => "NEW.$field IS NOT OLD.$field", $untouched));
        $pending = "SELECT e.* FROM $table e WHERE e.merchant_id=OLD.id AND e.expected_profile_version=OLD.profile_version AND e.resulting_profile_version=NEW.profile_version AND NOT EXISTS(SELECT 1 FROM $witnesses w WHERE w.completion_id=e.id)";
        $authorized = "EXISTS(SELECT 1 FROM ($pending) e JOIN merchant_applications a ON a.id=e.application_id WHERE $preDraft AND $authority AND NEW.updated_at=e.occurred_at)";
        $objects['bnc1_merchant_completion_guard'] = self::trigger('bnc1_merchant_completion_guard', 'BEFORE UPDATE', 'merchants', "$transition AND (NOT (".self::validName('NEW.business_name').") OR $changed OR NOT $authorized)");
        $sync = "UPDATE merchant_applications SET profile_version=NEW.profile_version,updated_at=NEW.updated_at WHERE id=(SELECT application_id FROM ($pending)) AND merchant_id=OLD.id AND profile_version=OLD.profile_version AND status='draft' AND current_slot='1'; SELECT CASE WHEN changes()!=1 THEN RAISE(ABORT,'BNC1 draft synchronization denied') END; INSERT INTO $witnesses(completion_id) SELECT id FROM ($pending); SELECT CASE WHEN changes()!=1 THEN RAISE(ABORT,'BNC1 witness required') END;";
        $objects['bnc1_merchant_completion_apply'] = self::trigger('bnc1_merchant_completion_apply', 'AFTER UPDATE', 'merchants', $transition, $sync);
        $applicationFields = ['id', 'merchant_id', 'attempt_number', 'current_slot', 'status', 'profile_snapshot', 'submitted_at', 'submitted_by_user_id', 'reviewed_at', 'reviewed_by_user_id', 'public_feedback', 'internal_review_notes', 'created_at'];
        $applicationChanged = implode(' OR ', array_map(fn (string $field): string => "NEW.$field IS NOT OLD.$field", $applicationFields));
        $waiting = "EXISTS(SELECT 1 FROM $table e WHERE e.application_id=OLD.id AND NOT EXISTS(SELECT 1 FROM $witnesses w WHERE w.completion_id=e.id))";
        $syncTuple = "EXISTS(SELECT 1 FROM $table e WHERE e.application_id=OLD.id AND e.expected_profile_version=OLD.profile_version AND e.resulting_profile_version=NEW.profile_version AND e.occurred_at=NEW.updated_at AND $authority AND NOT EXISTS(SELECT 1 FROM $witnesses w WHERE w.completion_id=e.id))";
        $profileInvalid = "OLD.status!='draft' OR NEW.status!='draft' OR $applicationChanged OR NEW.profile_version<=OLD.profile_version OR NOT EXISTS(SELECT 1 FROM merchants b WHERE b.id=NEW.merchant_id AND b.profile_version=NEW.profile_version)";
        $objects['bnc1_draft_profile_guard'] = self::trigger('bnc1_draft_profile_guard', 'BEFORE UPDATE', 'merchant_applications', "(NEW.profile_version IS NOT OLD.profile_version AND ($profileInvalid)) OR ($waiting AND ($applicationChanged OR NEW.profile_version IS OLD.profile_version OR NOT $syncTuple))");

        return $objects;
    }

    /** Called only by the selected artifact inside the atomic installer. */
    public static function createObjects(): void
    {
        if (! self::$installing || DB::connection()->transactionLevel() !== 1) {
            throw new LogicException('BNC1 requires its isolated atomic installer.');
        }
        foreach (self::objects() as $name => $sql) {
            DB::unprepared($sql);
            $checkpoint = config('bnc1.fixture_checkpoint');
            if ($checkpoint instanceof \Closure) {
                $checkpoint($name);
            }
        }
    }

    /** Owns schema and ledger atomically. Generic migrate is deliberately unsupported. */
    public static function install(): void
    {
        $settings = config('database.connections.sqlite');
        if (! app()->environment('testing') || getenv('XLIPA_SYNTHETIC_ONLY') !== '1' || config('database.default') !== 'sqlite' || $settings['database'] !== ':memory:' || ! empty($settings['url']) || DB::connection()->getDatabaseName() !== ':memory:' || config('bnc1.selected') !== realpath(self::artifact()) || self::$installing) {
            throw new LogicException('BNC1 working activation disabled.');
        }
        ComposedSubmissionSchema::assertSources();
        app(SubmissionTransaction::class)->run(function (): void {
            if (! SubmissionEvidenceSchema::ready()) {
                throw new LogicException('BNC1 requires the exact complete base SE profile.');
            }
            ComposedSubmissionSchema::assertBaseLedger();
            $batch = DB::table('migrations')->max('batch');
            if (! is_int($batch) || $batch >= PHP_INT_MAX) {
                throw new LogicException('BNC1 migration batch unavailable.');
            }
            self::$installing = true;
            try {
                (require self::artifact())->up();
                DB::table('migrations')->insert(['migration' => self::MIGRATION, 'batch' => $batch + 1]);
                $checkpoint = config('bnc1.fixture_checkpoint');
                if ($checkpoint instanceof \Closure) {
                    $checkpoint('ledger');
                }
                ComposedSubmissionSchema::assertReady();
            } finally {
                self::$installing = false;
            }
        });
    }
}
