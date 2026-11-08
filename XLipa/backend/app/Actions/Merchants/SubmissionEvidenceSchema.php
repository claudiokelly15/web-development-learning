<?php

namespace App\Actions\Merchants;

use Illuminate\Support\Facades\DB;
use LogicException;
use Throwable;

/** SE-1.0 database profile only. This is not operational submission readiness. */
final class SubmissionEvidenceSchema
{
    public const MIGRATION = '2026_11_08_210000_add_se1_submission_evidence';

    public const TABLE = 'merchant_application_submission_evidence';

    public static function artifact(): string
    {
        return database_path('activation/se1/'.self::MIGRATION.'.php');
    }

    private static function integer(string $name, int $minimum = 1): string
    {
        return "$name INTEGER NOT NULL CHECK(typeof($name)='integer' AND $name >= $minimum)";
    }

    private static function text(string $name, int $max): string
    {
        return "$name TEXT NOT NULL CHECK(typeof($name)='text' AND length(CAST($name AS BLOB)) BETWEEN 1 AND $max AND instr($name,char(0))=0)";
    }

    private static function ulid(string $name, bool $parent = true): string
    {
        $alphabet = '0123456789ABCDEFGHJKMNPQRSTVWXYZ'.($parent ? 'abcdefghjkmnpqrstvwxyz' : '');

        return self::text($name, 26)." CHECK(length($name)=26 AND substr($name,1,1) BETWEEN '0' AND '7' AND $name NOT GLOB '*[^$alphabet]*')";
    }

    private static function fk(string $columns, string $table, string $parent, bool $deferred = false): string
    {
        return "FOREIGN KEY($columns) REFERENCES $table($parent) ON UPDATE ".($deferred ? 'NO ACTION' : 'RESTRICT').' ON DELETE RESTRICT'.($deferred ? ' DEFERRABLE INITIALLY DEFERRED' : '');
    }

    private static function trigger(string $name, string $event, string $table, string $when, string $body = ''): string
    {
        return "CREATE TRIGGER $name $event ON $table".($when !== '' ? " WHEN $when" : '').' BEGIN '.($body ?: "SELECT RAISE(ABORT,'SE1 invariant denied');").' END';
    }

    /** Current authority/context checks shared by insertion and final mutations. Alias e is required. */
    private static function current(): string
    {
        return "EXISTS(SELECT 1 FROM users u JOIN merchant_memberships m ON m.user_id=u.id JOIN merchants b ON b.id=m.merchant_id JOIN merchant_onboarding_contexts x ON x.merchant_id=b.id JOIN merchant_identity_records i ON i.id=x.current_identity_id JOIN merchant_contact_versions c ON c.id=x.current_contact_id WHERE u.id=e.actor_user_id AND u.account_status='active' AND u.email_verified_at IS NOT NULL AND u.auth_version=e.auth_version AND u.email_binding_version=e.email_binding_version AND m.id=e.membership_id AND m.merchant_id=e.merchant_id AND m.role='owner' AND m.status='active' AND m.revoked_at IS NULL AND m.authority_version=e.authority_version AND b.operational_status NOT IN ('suspended','closed') AND length(trim(b.business_name)) BETWEEN 1 AND 200 AND b.profile_version=e.profile_version AND x.context_version=e.context_version AND x.updated_at<=e.submitted_at_ms AND x.contact_actor_user_id=e.actor_user_id AND x.contact_purpose=e.purpose AND i.id=e.identity_id AND i.merchant_id=e.merchant_id AND i.version=e.identity_version AND i.declared_by_user_id=e.actor_user_id AND i.state='current' AND i.subject_claim IN ('individual_owner','authorized_representative') AND i.syntax_policy_version IS NOT NULL AND i.syntax_checked_at<=e.submitted_at_ms AND i.declared_at<=e.submitted_at_ms AND c.id=e.contact_id AND c.merchant_id=e.merchant_id AND c.actor_user_id=e.actor_user_id AND c.purpose=e.purpose AND c.version=e.contact_version AND c.state='current' AND c.created_at<=e.submitted_at_ms) AND NOT EXISTS(SELECT 1 FROM verification_limit_state l WHERE l.merchant_id=e.merchant_id AND (l.pending_audit_event_id IS NOT NULL OR l.last_observed_at>e.submitted_at_ms))";
    }

    private static function metadata(string $alias): string
    {
        return "json_object('from','draft','to','pending_review','profile_version',$alias.profile_version,'reason_code','minimum_evidence_bound')";
    }

    /** Deterministic amendment-owned definitions. Frozen C definitions are never edited. */
    public static function objects(): array
    {
        $table = self::TABLE;
        $objects = [];
        foreach ([
            'applications' => ['merchant_applications', 'id,merchant_id,submitted_by_user_id,submitted_at,profile_version,attempt_number'],
            'memberships' => ['merchant_memberships', 'id,merchant_id,user_id'],
            'identities' => ['merchant_identity_records', 'id,merchant_id,declared_by_user_id,version'],
            'contacts' => ['merchant_contact_versions', 'id,merchant_id,actor_user_id,purpose,version'],
            'proofs' => ['contact_verification_proofs', 'id,merchant_id,actor_user_id,contact_id,purpose,consumed_application_id,consumed_at'],
            'audits' => ['audit_logs', 'id,event_type,actor_user_id,merchant_id,application_id,request_id,occurred_at'],
        ] as $name => [$parent, $columns]) {
            $objects['se1_'.$name.'_tuple'] = "CREATE UNIQUE INDEX se1_{$name}_tuple ON $parent ($columns)";
        }
        $columns = [self::ulid('application_id').' PRIMARY KEY', self::ulid('merchant_id'), self::text('contract_version', 50)." CHECK(contract_version='submission-evidence-v1')"];
        foreach (['actor_user_id', 'membership_id', 'auth_version', 'authority_version', 'email_binding_version', 'attempt_number', 'profile_version', 'context_version'] as $name) {
            $columns[] = self::integer($name);
        }
        foreach (['identity', 'contact'] as $name) {
            $columns[] = self::ulid($name.'_id', false);
            $columns[] = self::integer($name.'_version');
        }
        $columns = [...$columns, self::text('purpose', 50)." CHECK(purpose='application_phone')", self::ulid('proof_id', false).' UNIQUE',
            self::text('submission_request_id', 36)." CHECK(length(submission_request_id)=36 AND substr(submission_request_id,9,1)='-' AND substr(submission_request_id,14,1)='-' AND substr(submission_request_id,19,1)='-' AND substr(submission_request_id,24,1)='-' AND length(replace(submission_request_id,'-',''))=32 AND replace(submission_request_id,'-','') NOT GLOB '*[^0-9a-f]*')",
            self::integer('submitted_at_ms', 0).' CHECK(submitted_at_ms<=253402300799999)', self::text('submitted_at', 23)." CHECK(length(submitted_at)=23 AND strftime('%Y-%m-%d %H:%M:%S',submitted_at_ms/1000,'unixepoch') IS NOT NULL AND submitted_at=strftime('%Y-%m-%d %H:%M:%S',submitted_at_ms/1000,'unixepoch')||printf('.%03d',submitted_at_ms%1000))",
            self::ulid('audit_event_id', false).' UNIQUE', self::text('audit_event_type', 50)." CHECK(audit_event_type='application.submitted')", self::text('evidence_ciphertext', 16384)];
        foreach (['evidence_key_ref' => 64, 'binding_key_ref' => 64, 'retention_policy_version' => 50] as $name => $length) {
            $columns[] = self::text($name, $length)." CHECK($name NOT GLOB '*[^a-z0-9._-]*')";
        }
        $columns[] = self::integer('envelope_version').' CHECK(envelope_version=1)';
        $columns = [...$columns, 'UNIQUE(merchant_id,actor_user_id,submission_request_id)',
            self::fk('merchant_id', 'merchants', 'id'), self::fk('actor_user_id', 'users', 'id'),
            self::fk('application_id,merchant_id,actor_user_id,submitted_at,profile_version,attempt_number', 'merchant_applications', 'id,merchant_id,submitted_by_user_id,submitted_at,profile_version,attempt_number', true),
            self::fk('membership_id,merchant_id,actor_user_id', 'merchant_memberships', 'id,merchant_id,user_id'),
            self::fk('identity_id,merchant_id,actor_user_id,identity_version', 'merchant_identity_records', 'id,merchant_id,declared_by_user_id,version'),
            self::fk('contact_id,merchant_id,actor_user_id,purpose,contact_version', 'merchant_contact_versions', 'id,merchant_id,actor_user_id,purpose,version'),
            self::fk('proof_id,merchant_id,actor_user_id,contact_id,purpose,application_id,submitted_at_ms', 'contact_verification_proofs', 'id,merchant_id,actor_user_id,contact_id,purpose,consumed_application_id,consumed_at', true),
            self::fk('audit_event_id,audit_event_type,actor_user_id,merchant_id,application_id,submission_request_id,submitted_at', 'audit_logs', 'id,event_type,actor_user_id,merchant_id,application_id,request_id,occurred_at', true)];
        $objects[$table] = "CREATE TABLE $table (".implode(',', $columns).')';
        $current = self::current();
        $proof = "EXISTS(SELECT 1 FROM contact_verification_proofs p JOIN phone_otp_challenges h ON h.id=p.challenge_id JOIN otp_delivery_intents d ON d.challenge_id=h.id WHERE p.id=e.proof_id AND p.merchant_id=e.merchant_id AND p.actor_user_id=e.actor_user_id AND p.contact_id=e.contact_id AND p.purpose=e.purpose AND p.state='available' AND p.consumed_at IS NULL AND p.revoked_at IS NULL AND p.provenance='live' AND p.channel='sms' AND p.policy_version='application-phone-proof-v1' AND p.verified_at<=e.submitted_at_ms AND p.expires_at>e.submitted_at_ms AND h.state='consumed' AND h.terminal_at=p.verified_at AND h.transport_mode='live' AND d.transport_mode='live' AND d.state='accepted' AND d.completed_at<d.lease_expires_at AND d.completed_at<=p.verified_at)";
        // Explicitly project NEW fields so the same current-context predicate can be used at each write boundary.
        $fields = ['actor_user_id', 'merchant_id', 'auth_version', 'email_binding_version', 'membership_id', 'authority_version', 'profile_version', 'context_version', 'submitted_at_ms', 'purpose', 'identity_id', 'identity_version', 'contact_id', 'contact_version', 'proof_id'];
        $projection = implode(',', array_map(fn (string $field): string => "NEW.$field AS $field", $fields));
        $insert = "NOT EXISTS(SELECT 1 FROM (SELECT $projection) e WHERE $current AND $proof) OR NOT EXISTS(SELECT 1 FROM merchant_applications a WHERE a.id=NEW.application_id AND a.merchant_id=NEW.merchant_id AND a.current_slot=1 AND a.status='draft' AND a.attempt_number=NEW.attempt_number AND a.profile_version=NEW.profile_version AND a.profile_snapshot IS NULL AND a.submitted_at IS NULL AND a.submitted_by_user_id IS NULL) OR EXISTS(SELECT 1 FROM $table e WHERE e.application_id=NEW.application_id OR e.proof_id=NEW.proof_id OR (e.merchant_id=NEW.merchant_id AND e.actor_user_id=NEW.actor_user_id AND e.submission_request_id=NEW.submission_request_id)) OR EXISTS(SELECT 1 FROM audit_logs WHERE id=NEW.audit_event_id OR (event_type='application.submitted' AND merchant_id=NEW.merchant_id AND actor_user_id=NEW.actor_user_id AND request_id=NEW.submission_request_id))";
        $objects['se1_evidence_insert'] = self::trigger('se1_evidence_insert', 'BEFORE INSERT', $table, $insert);
        foreach (['update' => 'BEFORE UPDATE', 'delete' => 'BEFORE DELETE'] as $name => $event) {
            $objects['se1_evidence_'.$name] = self::trigger('se1_evidence_'.$name, $event, $table, '');
        }
        $objects['se1_evidence_audit'] = self::trigger('se1_evidence_audit', 'AFTER INSERT', $table, '', "INSERT INTO audit_logs(id,event_type,actor_user_id,target_user_id,merchant_id,application_id,request_id,metadata,occurred_at) VALUES(NEW.audit_event_id,'application.submitted',NEW.actor_user_id,NULL,NEW.merchant_id,NEW.application_id,NEW.submission_request_id,".self::metadata('NEW').',NEW.submitted_at);');
        $objects['se1_audit_insert'] = self::trigger('se1_audit_insert', 'BEFORE INSERT', 'audit_logs', "NEW.event_type='application.submitted' AND NOT EXISTS(SELECT 1 FROM $table e JOIN merchant_applications a ON a.id=e.application_id WHERE e.audit_event_id=NEW.id AND e.actor_user_id=NEW.actor_user_id AND NEW.target_user_id IS NULL AND e.merchant_id=NEW.merchant_id AND e.application_id=NEW.application_id AND e.submission_request_id=NEW.request_id AND e.submitted_at=NEW.occurred_at AND NEW.metadata=".self::metadata('e')." AND a.status='draft' AND a.current_slot=1)");
        $objects['se1_proof_consume'] = self::trigger('se1_proof_consume', 'BEFORE UPDATE', 'contact_verification_proofs', "OLD.state='available' AND NEW.state='consumed' AND NOT EXISTS(SELECT 1 FROM $table e WHERE e.proof_id=OLD.id AND e.application_id=NEW.consumed_application_id AND e.submitted_at_ms=NEW.consumed_at AND $current)");
        $objects['se1_application_insert'] = self::trigger('se1_application_insert', 'BEFORE INSERT', 'merchant_applications', "NEW.status!='draft' OR NEW.submitted_at IS NOT NULL OR NEW.submitted_by_user_id IS NOT NULL OR EXISTS(SELECT 1 FROM merchant_applications WHERE id=NEW.id)");
        $objects['se1_application_delete'] = self::trigger('se1_application_delete', 'BEFORE DELETE', 'merchant_applications', '');
        $frozen = "NEW.id IS NOT OLD.id OR NEW.merchant_id IS NOT OLD.merchant_id OR NEW.attempt_number IS NOT OLD.attempt_number OR (OLD.status!='draft' AND (NEW.profile_version IS NOT OLD.profile_version OR NEW.profile_snapshot IS NOT OLD.profile_snapshot OR NEW.submitted_at IS NOT OLD.submitted_at OR NEW.submitted_by_user_id IS NOT OLD.submitted_by_user_id))";
        $bundle = "EXISTS(SELECT 1 FROM $table e JOIN contact_verification_proofs p ON p.id=e.proof_id JOIN audit_logs z ON z.id=e.audit_event_id WHERE e.application_id=NEW.id AND e.profile_version=NEW.profile_version AND e.attempt_number=NEW.attempt_number AND e.actor_user_id=NEW.submitted_by_user_id AND e.submitted_at=NEW.submitted_at AND p.state='consumed' AND p.consumed_application_id=NEW.id AND p.consumed_at=e.submitted_at_ms AND z.metadata=".self::metadata('e')." AND $current)";
        $objects['se1_application_update'] = self::trigger('se1_application_update', 'BEFORE UPDATE', 'merchant_applications', "$frozen OR (OLD.status='draft' AND ((NEW.status='draft' AND (NEW.submitted_at IS NOT NULL OR NEW.submitted_by_user_id IS NOT NULL)) OR (NEW.status!='draft' AND (NEW.status!='pending_review' OR (NEW.current_slot IS NULL OR NEW.current_slot!='1') OR NEW.profile_snapshot IS NOT NULL OR NOT $bundle)))) OR (OLD.status!='draft' AND NEW.status IS NOT OLD.status AND NOT(OLD.status='pending_review' AND NEW.status IN ('approved','rejected','information_requested')))");
        foreach (['users', 'merchant_memberships', 'merchants'] as $parent) {
            $objects['se1_'.$parent.'_delete'] = self::trigger('se1_'.$parent.'_delete', 'BEFORE DELETE', $parent, '');
            $identity = $parent === 'users' ? 'id' : ($parent === 'merchants' ? 'id,created_by_user_id' : 'id,merchant_id,user_id');
            $condition = implode(' OR ', array_map(fn (string $field): string => "NEW.$field IS NOT OLD.$field", explode(',', $identity)));
            $objects['se1_'.$parent.'_identity'] = self::trigger('se1_'.$parent.'_identity', 'BEFORE UPDATE', $parent, $condition);
            $objects['se1_'.$parent.'_replace'] = self::trigger('se1_'.$parent.'_replace', 'BEFORE INSERT', $parent, "EXISTS(SELECT 1 FROM $parent WHERE id=NEW.id)".($parent === 'users' ? ' OR EXISTS(SELECT 1 FROM users WHERE email=NEW.email)' : ($parent === 'merchant_memberships' ? ' OR EXISTS(SELECT 1 FROM merchant_memberships WHERE merchant_id=NEW.merchant_id AND user_id=NEW.user_id)' : '')));
        }
        foreach ([
            'email' => ['users', 'email_binding_version', ['email', 'email_verified_at']],
            'authority' => ['merchant_memberships', 'authority_version', ['role', 'status', 'revoked_at']],
            'profile' => ['merchants', 'profile_version', ['business_name', 'business_type', 'registration_country', 'registration_number', 'business_email', 'business_phone', 'address_line1', 'address_line2', 'city', 'region', 'country_code', 'postal_code']],
        ] as $name => [$parent,$version,$fields]) {
            $changed = '('.implode(' OR ', array_map(fn (string $field): string => "NEW.$field IS NOT OLD.$field", $fields)).')';
            $objects['se1_'.$name.'_version'] = self::trigger('se1_'.$name.'_version', 'BEFORE UPDATE', $parent, "typeof(NEW.$version)!='integer' OR NEW.$version<1 OR ($changed AND (OLD.$version=9223372036854775807 OR NEW.$version!=OLD.$version+1)) OR (NOT $changed AND NEW.$version IS NOT OLD.$version)".($name === 'email' ? ' OR (NEW.email IS NOT OLD.email AND NEW.email_verified_at IS NOT NULL)' : ''));
            if ($name !== 'profile') {
                $objects['se1_'.$name.'_initial'] = self::trigger('se1_'.$name.'_initial', 'BEFORE INSERT', $parent, "NEW.$version IS NOT 1");
            }
        }
        $objects['se1_account_lifecycle_hold'] = self::trigger('se1_account_lifecycle_hold', 'BEFORE UPDATE', 'users', 'NEW.account_status IS NOT OLD.account_status OR NEW.password IS NOT OLD.password OR NEW.auth_version IS NOT OLD.auth_version');

        return $objects;
    }

    public static function assertFrozen(): void
    {
        $contract = json_decode(file_get_contents(database_path('activation/b32c/security-contract.json')), true, 64, JSON_THROW_ON_ERROR);
        foreach ($contract['create_tables'] + $contract['indexes'] + $contract['triggers'] + ['audit_logs' => $contract['audit']['sql']] as $name => $sql) {
            if (DB::selectOne('SELECT sql FROM sqlite_master WHERE name=?', [$name])?->sql !== $sql) {
                throw new LogicException('SE1 requires exact frozen C objects.');
            }
        }
        if (DB::table('migrations')->where('migration', '2026_11_07_200000_activate_b32_security_contract')->count() !== 1) {
            throw new LogicException('SE1 requires genuine C ledger.');
        }
    }

    public static function assertSources(): void
    {
        $manifest = json_decode(file_get_contents(database_path('activation/se1/manifest.json')), true, 16, JSON_THROW_ON_ERROR);
        if ($manifest['version'] !== 'submission-evidence-v1' || $manifest['migration'] !== self::MIGRATION || $manifest['mode'] !== 'isolated-only' || ! hash_equals($manifest['schema_sha256'], hash_file('sha256', __FILE__)) || ! hash_equals($manifest['artifact_sha256'], hash_file('sha256', self::artifact())) || ! hash_equals($manifest['c_contract_sha256'], hash_file('sha256', database_path('activation/b32c/security-contract.json'))) || ! hash_equals($manifest['c_manifest_sha256'], hash_file('sha256', database_path('activation/b32c/selection-manifest.json')))) {
            throw new LogicException('SE1 source manifest mismatch.');
        }
    }

    private static function assertProfile(bool $amended): void
    {
        $foundation = json_decode(file_get_contents(database_path('activation/b32c/selection-manifest.json')), true, 64, JSON_THROW_ON_ERROR);
        foreach ($foundation['foundation_objects'] as $key => $hash) {
            [$type, $name] = explode(':', $key, 2);
            if ($name === 'audit_logs') {
                continue;
            }
            $sql = DB::selectOne('SELECT sql FROM sqlite_master WHERE type=? AND name=?', [$type, $name])?->sql ?? '';
            if ($amended && in_array($name, ['users', 'merchant_memberships'], true)) {
                $column = $name === 'users' ? 'email_binding_version' : 'authority_version';
                $addition = ", $column INTEGER NOT NULL DEFAULT 1 CHECK(typeof($column)='integer' AND $column>=1)";
                if (substr_count($sql, $addition) !== 1) {
                    throw new LogicException('SE1 foundation column mismatch.');
                }
                $sql = str_replace($addition, '', $sql);
            }
            if (! hash_equals($hash, hash('sha256', $sql))) {
                throw new LogicException('SE1 foundation definition drift.');
            }
        }
        $contract = json_decode(file_get_contents(database_path('activation/b32c/security-contract.json')), true, 64, JSON_THROW_ON_ERROR);
        $names = array_map(fn (string $key): string => explode(':', $key, 2)[1], array_keys($foundation['foundation_objects']));
        $names = array_unique([...$names, ...array_keys($contract['create_tables'] + $contract['indexes'] + $contract['triggers']), ...($amended ? array_keys(self::objects()) : [])]);
        sort($names);
        $actual = array_column(DB::select("SELECT name FROM sqlite_master WHERE sql IS NOT NULL AND name NOT LIKE 'sqlite_%' ORDER BY name"), 'name');
        if ($names !== $actual) {
            throw new LogicException('SE1 unexpected schema object.');
        }
        $ledger = [...array_column($foundation['foundation_ledger'], 'migration'), '2026_11_07_200000_activate_b32_security_contract'];
        if ($amended && DB::table('migrations')->where('migration', self::MIGRATION)->exists()) {
            $ledger[] = self::MIGRATION;
        }
        sort($ledger);
        if ($ledger !== DB::table('migrations')->orderBy('migration')->pluck('migration')->all()) {
            throw new LogicException('SE1 unexpected migration ledger.');
        }
    }

    public static function install(): void
    {
        $settings = config('database.connections.sqlite');
        if (! app()->environment('testing') || getenv('XLIPA_SYNTHETIC_ONLY') !== '1' || config('database.default') !== 'sqlite' || $settings['database'] !== ':memory:' || ! empty($settings['url']) || config('se1.selected') !== realpath(self::artifact()) || DB::connection()->getDatabaseName() !== ':memory:') {
            throw new LogicException('SE1 working activation is disabled.');
        }
        if ((int) DB::selectOne('PRAGMA foreign_keys')->foreign_keys !== 1 || (int) DB::selectOne('PRAGMA recursive_triggers')->recursive_triggers !== 1) {
            throw new LogicException('SE1 requires foreign keys and recursive triggers.');
        }
        self::assertSources();
        app(VerificationStore::class)->run(function (): void {
            self::assertFrozen();
            if (DB::selectOne("SELECT 1 FROM sqlite_master WHERE name LIKE 'se1_%' OR name=?", [self::TABLE]) || DB::table('migrations')->where('migration', self::MIGRATION)->exists()) {
                throw new LogicException('SE1 partial or duplicate activation refused.');
            }
            self::assertProfile(false);
            DB::statement('ALTER TABLE users ADD COLUMN email_binding_version INTEGER NOT NULL DEFAULT 1 CHECK(typeof(email_binding_version)=\'integer\' AND email_binding_version>=1)');
            DB::statement('ALTER TABLE merchant_memberships ADD COLUMN authority_version INTEGER NOT NULL DEFAULT 1 CHECK(typeof(authority_version)=\'integer\' AND authority_version>=1)');
            foreach (self::objects() as $name => $sql) {
                DB::unprepared($sql);
                $hook = config('se1.fixture_checkpoint');
                if ($hook instanceof \Closure) {
                    $hook($name);
                }
            }
            self::assertFrozen();
            self::assertProfile(true);
            if (DB::select('PRAGMA foreign_key_check') !== []) {
                throw new LogicException('SE1 foreign-key postflight failed.');
            }
        });
    }

    /** Schema capability only; never grants keys, provenance, processing or submission authority. */
    public static function ready(): bool
    {
        try {
            if (DB::getDriverName() !== 'sqlite' || (int) DB::selectOne('PRAGMA foreign_keys')->foreign_keys !== 1 || (int) DB::selectOne('PRAGMA recursive_triggers')->recursive_triggers !== 1) {
                return false;
            }
            self::assertSources();
            self::assertFrozen();
            self::assertProfile(true);
            foreach (self::objects() as $name => $sql) {
                if (DB::selectOne('SELECT sql FROM sqlite_master WHERE name=?', [$name])?->sql !== $sql) {
                    return false;
                }
            }
            foreach (['users' => 'email_binding_version', 'merchant_memberships' => 'authority_version'] as $table => $column) {
                $definition = DB::selectOne('SELECT sql FROM sqlite_master WHERE name=?', [$table])?->sql;
                if (! str_contains($definition ?? '', "$column INTEGER NOT NULL DEFAULT 1 CHECK(typeof($column)='integer' AND $column>=1)")) {
                    return false;
                }
            }

            return DB::table('migrations')->where('migration', self::MIGRATION)->count() === 1;
        } catch (Throwable) {
            return false;
        }
    }
}
