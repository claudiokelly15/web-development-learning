<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const TABLES = ['merchant_identity_records', 'merchant_contact_versions', 'merchant_onboarding_contexts', 'phone_otp_challenges', 'otp_delivery_intents', 'contact_verification_proofs', 'verification_limit_state'];

    private function guard(): void
    {
        $connection = config('database.default');
        $settings = config('database.connections.'.$connection, []);
        $name = $settings['database'] ?? '';
        $temporary = realpath(dirname($name)) === realpath(sys_get_temp_dir()) && preg_match('/^xlipa-b32-synthetic-[a-f0-9]+\.sqlite$/D', basename($name));
        if (! app()->environment('testing') || getenv('XLIPA_SYNTHETIC_ONLY') !== '1' || ($settings['driver'] ?? null) !== 'sqlite'
            || ! empty($settings['url']) || ($name !== ':memory:' && ! $temporary)) {
            throw new LogicException('B3.2-B requires isolated synthetic SQLite.');
        }
        if (DB::getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== $name || (int) DB::selectOne('PRAGMA foreign_keys')->foreign_keys !== 1) {
            throw new LogicException('B3.2-B requires isolated synthetic SQLite.');
        }
    }

    private function text(string $name, int $max, bool $nullable = false): string
    {
        $null = $nullable ? '' : ' NOT NULL';

        return "$name TEXT$null CHECK ($name IS NULL OR (typeof($name)='text' AND length($name) BETWEEN 1 AND $max AND instr($name,char(0))=0))";
    }

    private function integer(string $name, int $min = 0, bool $nullable = false, ?int $default = null): string
    {
        return "$name INTEGER".($nullable ? '' : ' NOT NULL').($default === null ? '' : " DEFAULT $default")." CHECK ($name IS NULL OR (typeof($name)='integer' AND $name >= $min))";
    }

    private function ulid(string $name, bool $nullable = false): string
    {
        $alphabet = in_array($name, ['merchant_id', 'consumed_application_id'], true)
            ? '0123456789ABCDEFGHJKMNPQRSTVWXYZabcdefghjkmnpqrstvwxyz'
            : '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

        return $this->text($name, 26, $nullable)." CHECK ($name IS NULL OR (length($name)=26 AND substr($name,1,1) BETWEEN '0' AND '7' AND $name NOT GLOB '*[^$alphabet]*'))";
    }

    private function identifier(string $name, bool $nullable = false): string
    {
        return $this->text($name, 64, $nullable)." CHECK ($name IS NULL OR $name NOT GLOB '*[^a-z0-9._-]*')";
    }

    private function hex(string $name, bool $nullable = false): string
    {
        return $this->text($name, 64, $nullable)." CHECK ($name IS NULL OR (length($name)=64 AND $name NOT GLOB '*[^0-9a-f]*'))";
    }

    private function state(string $name, array $values, ?string $default = null): string
    {
        $list = implode(',', array_map(fn (string $v): string => "'$v'", $values));

        return $this->text($name, 50).($default === null ? '' : " DEFAULT '$default'")." CHECK ($name IN ($list))";
    }

    private function foreign(array $columns, string $table, array $references): string
    {
        return 'FOREIGN KEY ('.implode(',', $columns).') REFERENCES '.$table.' ('.implode(',', $references).') ON UPDATE RESTRICT ON DELETE RESTRICT';
    }

    private function create(string $table, array $columns, array $constraints): void
    {
        DB::statement('CREATE TABLE '.$table.' ('.implode(',', [...$columns, ...$constraints]).')');
        DB::unprepared("CREATE TRIGGER {$table}_no_delete BEFORE DELETE ON $table BEGIN SELECT RAISE(ABORT,'Evidence deletion prohibited'); END");
    }

    private function index(string $table, string $suffix, array $columns, bool $unique = false, string $where = ''): void
    {
        DB::statement('CREATE '.($unique ? 'UNIQUE ' : '')."INDEX {$table}_$suffix ON $table (".implode(',', $columns).')'.($where ? " WHERE $where" : ''));
    }

    private function immutable(string $table, array $fields, string $version, string $extra = ''): void
    {
        $condition = implode(' OR ', array_map(fn (string $f): string => "NEW.$f IS NOT OLD.$f", $fields));
        $condition .= " OR NEW.$version != OLD.$version+1".($extra ? " OR ($extra)" : '');
        DB::unprepared("CREATE TRIGGER {$table}_immutable BEFORE UPDATE ON $table WHEN $condition BEGIN SELECT RAISE(ABORT,'Immutable data or invalid transition'); END");
    }

    private function insertState(string $table, string $state): void
    {
        DB::unprepared("CREATE TRIGGER {$table}_initial BEFORE INSERT ON $table WHEN NEW.state != '$state' BEGIN SELECT RAISE(ABORT,'Invalid initial state'); END");
    }

    private function tenant(): array
    {
        return [$this->ulid('merchant_id'), $this->integer('actor_user_id', 1)];
    }

    private function tenantFks(): array
    {
        return [$this->foreign(['merchant_id'], 'merchants', ['id']), $this->foreign(['actor_user_id'], 'users', ['id'])];
    }

    public function up(): void
    {
        $this->guard();
        $this->create('merchant_identity_records', [
            $this->ulid('id').' PRIMARY KEY', $this->ulid('merchant_id'), $this->integer('version', 1),
            $this->state('subject_claim', ['unknown', 'individual_owner', 'authorized_representative']),
            $this->text('nin_ciphertext', 4096), $this->identifier('encryption_key_ref'),
            $this->integer('envelope_version', 1).' CHECK(envelope_version=1)', $this->integer('envelope_revision', 1, false, 1),
            $this->text('syntax_policy_version', 50, true), $this->integer('syntax_checked_at', 0, true),
            $this->text('declaration_version', 50), $this->text('notice_version', 50),
            $this->integer('declared_by_user_id', 1), $this->integer('declared_at'),
            $this->state('state', ['current', 'superseded'], 'current'), $this->integer('row_version', 1, false, 1),
            $this->integer('superseded_at', 0, true), $this->integer('retention_due_at', 0, true),
        ], [
            'UNIQUE(id,merchant_id)', 'UNIQUE(merchant_id,version)',
            $this->foreign(['merchant_id'], 'merchants', ['id']), $this->foreign(['declared_by_user_id'], 'users', ['id']),
            'CHECK((syntax_policy_version IS NULL)=(syntax_checked_at IS NULL))',
            "CHECK((state='current' AND superseded_at IS NULL) OR (state='superseded' AND superseded_at IS NOT NULL AND superseded_at>=declared_at))",
            'CHECK(retention_due_at IS NULL OR retention_due_at>=declared_at)', 'CHECK(syntax_checked_at IS NULL OR syntax_checked_at>=declared_at)',
        ]);
        $this->index('merchant_identity_records', 'current', ['merchant_id'], true, "state='current'");
        $this->index('merchant_identity_records', 'retention', ['retention_due_at'], false, 'retention_due_at IS NOT NULL');
        $this->index('merchant_identity_records', 'actor', ['declared_by_user_id']);
        $this->insertState('merchant_identity_records', 'current');
        $this->immutable('merchant_identity_records', ['id', 'merchant_id', 'version', 'subject_claim', 'declaration_version', 'notice_version', 'declared_by_user_id', 'declared_at', 'envelope_version'], 'row_version',
            "(OLD.state='superseded' AND NEW.state!='superseded') OR (OLD.superseded_at IS NOT NULL AND NEW.superseded_at IS NOT OLD.superseded_at) OR (OLD.syntax_policy_version IS NOT NULL AND (NEW.syntax_policy_version IS NOT OLD.syntax_policy_version OR NEW.syntax_checked_at IS NOT OLD.syntax_checked_at)) OR ((NEW.nin_ciphertext IS NOT OLD.nin_ciphertext OR NEW.encryption_key_ref IS NOT OLD.encryption_key_ref OR NEW.envelope_revision IS NOT OLD.envelope_revision) AND NEW.envelope_revision != OLD.envelope_revision+1)");

        $this->create('merchant_contact_versions', [
            $this->ulid('id').' PRIMARY KEY', ...$this->tenant(), $this->state('purpose', ['application_phone']), $this->integer('version', 1),
            $this->text('phone_ciphertext', 4096), $this->identifier('phone_key_ref'),
            $this->integer('envelope_version', 1).' CHECK(envelope_version=1)', $this->integer('envelope_revision', 1, false, 1),
            $this->hex('target_fingerprint'), $this->identifier('fingerprint_key_ref'),
            $this->state('state', ['current', 'retired'], 'current'), $this->integer('row_version', 1, false, 1),
            $this->integer('created_at'), $this->integer('retired_at', 0, true),
        ], [...$this->tenantFks(), 'UNIQUE(id,merchant_id,actor_user_id,purpose)', 'UNIQUE(merchant_id,actor_user_id,purpose,version)',
            "CHECK((state='current' AND retired_at IS NULL) OR (state='retired' AND retired_at IS NOT NULL AND retired_at>=created_at))"]);
        $this->index('merchant_contact_versions', 'current', ['merchant_id', 'actor_user_id', 'purpose'], true, "state='current'");
        $this->index('merchant_contact_versions', 'fingerprint', ['fingerprint_key_ref', 'target_fingerprint']);
        $this->index('merchant_contact_versions', 'actor', ['actor_user_id']);
        $this->insertState('merchant_contact_versions', 'current');
        $this->immutable('merchant_contact_versions', ['id', 'merchant_id', 'actor_user_id', 'purpose', 'version', 'target_fingerprint', 'fingerprint_key_ref', 'created_at', 'envelope_version'], 'row_version',
            "(OLD.state='retired' AND NEW.state!='retired') OR (OLD.retired_at IS NOT NULL AND NEW.retired_at IS NOT OLD.retired_at) OR ((NEW.phone_ciphertext IS NOT OLD.phone_ciphertext OR NEW.phone_key_ref IS NOT OLD.phone_key_ref OR NEW.envelope_revision IS NOT OLD.envelope_revision) AND NEW.envelope_revision != OLD.envelope_revision+1)");

        $this->create('merchant_onboarding_contexts', [
            $this->ulid('merchant_id').' PRIMARY KEY', $this->integer('context_version', 1, false, 1),
            $this->ulid('current_identity_id', true), $this->ulid('current_contact_id', true),
            $this->integer('contact_actor_user_id', 1, true), $this->text('contact_purpose', 50, true)." CHECK(contact_purpose IS NULL OR contact_purpose='application_phone')", $this->integer('updated_at'),
        ], [$this->foreign(['merchant_id'], 'merchants', ['id']),
            $this->foreign(['current_identity_id', 'merchant_id'], 'merchant_identity_records', ['id', 'merchant_id']),
            $this->foreign(['current_contact_id', 'merchant_id', 'contact_actor_user_id', 'contact_purpose'], 'merchant_contact_versions', ['id', 'merchant_id', 'actor_user_id', 'purpose']),
            'CHECK((current_contact_id IS NULL AND contact_actor_user_id IS NULL AND contact_purpose IS NULL) OR (current_contact_id IS NOT NULL AND contact_actor_user_id IS NOT NULL AND contact_purpose IS NOT NULL))']);
        $this->index('merchant_onboarding_contexts', 'identity', ['current_identity_id', 'merchant_id']);
        $this->index('merchant_onboarding_contexts', 'contact', ['current_contact_id', 'merchant_id', 'contact_actor_user_id', 'contact_purpose']);
        $this->immutable('merchant_onboarding_contexts', ['merchant_id'], 'context_version', 'NEW.updated_at<OLD.updated_at');
        foreach (['INSERT', 'UPDATE'] as $operation) {
            DB::unprepared("CREATE TRIGGER contexts_current_$operation BEFORE $operation ON merchant_onboarding_contexts WHEN (NEW.current_identity_id IS NOT NULL AND NOT EXISTS(SELECT 1 FROM merchant_identity_records WHERE id=NEW.current_identity_id AND state='current')) OR (NEW.current_contact_id IS NOT NULL AND NOT EXISTS(SELECT 1 FROM merchant_contact_versions WHERE id=NEW.current_contact_id AND state='current')) BEGIN SELECT RAISE(ABORT,'Current pointer required'); END");
        }

        $binding = fn (): array => [$this->ulid('id').' PRIMARY KEY', ...$this->tenant(), $this->ulid('contact_id'), $this->state('purpose', ['application_phone'])];
        $contactFk = $this->foreign(['contact_id', 'merchant_id', 'actor_user_id', 'purpose'], 'merchant_contact_versions', ['id', 'merchant_id', 'actor_user_id', 'purpose']);
        $this->create('phone_otp_challenges', [...$binding(),
            $this->integer('active_slot', 1, true).' CHECK(active_slot IS NULL OR active_slot=1)',
            $this->state('state', ['queued', 'dispatching', 'active', 'delivery_unknown', 'consumed', 'exhausted', 'expired', 'revoked', 'delivery_failed'], 'queued'),
            $this->integer('state_version', 1, false, 1), $this->hex('code_digest', true), $this->identifier('digest_key_ref', true),
            $this->integer('failed_attempts', 0, false, 0).' CHECK(failed_attempts<=5)',
            $this->integer('created_at'), $this->integer('expires_at'), $this->integer('digest_created_at', 0, true), $this->integer('terminal_at', 0, true),
            $this->state('transport_mode', ['simulated', 'live']),
        ], [...$this->tenantFks(), $contactFk,
            'UNIQUE(id,merchant_id,contact_id,actor_user_id,purpose,transport_mode)', 'UNIQUE(merchant_id,contact_id,actor_user_id,purpose,active_slot)',
            'CHECK(expires_at=created_at+300000)', 'CHECK((code_digest IS NULL)=(digest_key_ref IS NULL))',
            'CHECK(digest_created_at IS NULL OR (digest_created_at>=created_at AND digest_created_at<expires_at))',
            "CONSTRAINT otp_terminal_chronology CHECK(terminal_at IS NULL OR ((digest_created_at IS NULL OR terminal_at>=digest_created_at) AND (state NOT IN ('consumed','exhausted') OR terminal_at<expires_at) AND (state!='expired' OR terminal_at>=expires_at)))",
            "CHECK((state='queued' AND active_slot IS 1 AND code_digest IS NULL AND digest_created_at IS NULL AND failed_attempts=0 AND terminal_at IS NULL) OR (state IN ('dispatching','active','delivery_unknown') AND active_slot IS 1 AND code_digest IS NOT NULL AND digest_created_at IS NOT NULL AND terminal_at IS NULL AND ((state='active' AND failed_attempts<=4) OR (state!='active' AND failed_attempts=0))) OR (state IN ('consumed','exhausted','expired','revoked','delivery_failed') AND active_slot IS NULL AND terminal_at IS NOT NULL AND terminal_at>=created_at AND (state!='exhausted' OR (failed_attempts=5 AND digest_created_at IS NOT NULL)) AND (state!='consumed' OR (failed_attempts<=4 AND digest_created_at IS NOT NULL))))",
        ]);
        $this->index('phone_otp_challenges', 'expiry', ['state', 'expires_at']);
        $this->index('phone_otp_challenges', 'contact', ['contact_id', 'merchant_id', 'actor_user_id', 'purpose']);
        $this->index('phone_otp_challenges', 'actor', ['actor_user_id']);
        $this->insertState('phone_otp_challenges', 'queued');
        $edges = "(NEW.state=OLD.state OR (OLD.state='queued' AND NEW.state IN ('dispatching','expired','revoked','delivery_failed')) OR (OLD.state='dispatching' AND NEW.state IN ('active','delivery_unknown','delivery_failed','expired','revoked')) OR (OLD.state='active' AND NEW.state IN ('consumed','exhausted','expired','revoked')) OR (OLD.state='delivery_unknown' AND NEW.state IN ('expired','revoked')))";
        $this->immutable('phone_otp_challenges', ['id', 'merchant_id', 'contact_id', 'actor_user_id', 'purpose', 'created_at', 'expires_at', 'transport_mode'], 'state_version',
            "NOT $edges OR NEW.failed_attempts<OLD.failed_attempts OR (NEW.failed_attempts!=OLD.failed_attempts AND NOT(OLD.state='active' AND NEW.state IN ('active','exhausted') AND NEW.failed_attempts=OLD.failed_attempts+1)) OR (OLD.digest_created_at IS NOT NULL AND NEW.digest_created_at IS NOT OLD.digest_created_at) OR (OLD.terminal_at IS NOT NULL AND NEW.terminal_at IS NOT OLD.terminal_at) OR ((NEW.code_digest IS NOT OLD.code_digest OR NEW.digest_key_ref IS NOT OLD.digest_key_ref) AND NOT((OLD.state='queued' AND NEW.state='dispatching') OR (OLD.active_slot IS NULL AND NEW.code_digest IS NULL AND NEW.digest_key_ref IS NULL)))");

        $challengeFk = $this->foreign(['challenge_id', 'merchant_id', 'contact_id', 'actor_user_id', 'purpose', 'transport_mode'], 'phone_otp_challenges', ['id', 'merchant_id', 'contact_id', 'actor_user_id', 'purpose', 'transport_mode']);
        $this->create('otp_delivery_intents', [...$binding(), $this->ulid('challenge_id'), $this->state('transport_mode', ['simulated', 'live']),
            $this->text('request_id', 36)." CHECK(length(request_id)=36 AND substr(request_id,9,1)='-' AND substr(request_id,14,1)='-' AND substr(request_id,19,1)='-' AND substr(request_id,24,1)='-' AND length(replace(request_id,'-',''))=32 AND replace(request_id,'-','') NOT GLOB '*[^0-9a-f]*')",
            $this->state('state', ['queued', 'claimed', 'accepted', 'rejected', 'unknown', 'cancelled'], 'queued'), $this->integer('claim_version', 1, false, 1),
            $this->integer('requested_at'), $this->integer('claimed_at', 0, true), $this->integer('lease_expires_at', 0, true), $this->integer('completed_at', 0, true),
            $this->text('failure_code', 50, true)." CHECK(failure_code IS NULL OR failure_code IN ('provider_rejected','provider_unavailable','ambiguous_timeout','dispatch_cancelled','expired_before_send'))",
        ], [...$this->tenantFks(), $contactFk, $challengeFk, 'UNIQUE(challenge_id)', 'UNIQUE(merchant_id,actor_user_id,request_id)',
            'CHECK((claimed_at IS NULL)=(lease_expires_at IS NULL))', 'CHECK(claimed_at IS NULL OR (claimed_at>=requested_at AND lease_expires_at>claimed_at))',
            "CHECK((state='queued' AND claimed_at IS NULL AND completed_at IS NULL AND failure_code IS NULL) OR (state='claimed' AND claimed_at IS NOT NULL AND completed_at IS NULL AND failure_code IS NULL) OR (state IN ('accepted','rejected','unknown') AND claimed_at IS NOT NULL AND completed_at IS NOT NULL AND completed_at>=claimed_at AND ((state='accepted' AND failure_code IS NULL) OR (state='rejected' AND failure_code IS NOT NULL AND failure_code IN ('provider_rejected','provider_unavailable')) OR (state='unknown' AND failure_code IS NOT NULL AND failure_code IN ('ambiguous_timeout','provider_unavailable')))) OR (state='cancelled' AND completed_at IS NOT NULL AND completed_at>=requested_at AND failure_code IS NOT NULL AND failure_code IN ('dispatch_cancelled','expired_before_send')))"]);
        $this->index('otp_delivery_intents', 'lease', ['state', 'lease_expires_at']);
        $this->index('otp_delivery_intents', 'lineage', ['challenge_id', 'merchant_id', 'contact_id', 'actor_user_id', 'purpose', 'transport_mode']);
        $this->index('otp_delivery_intents', 'contact', ['contact_id', 'merchant_id', 'actor_user_id', 'purpose']);
        $this->index('otp_delivery_intents', 'actor', ['actor_user_id']);
        $this->insertState('otp_delivery_intents', 'queued');
        $this->immutable('otp_delivery_intents', ['id', 'merchant_id', 'contact_id', 'actor_user_id', 'purpose', 'challenge_id', 'transport_mode', 'request_id', 'requested_at'], 'claim_version',
            "NOT((OLD.state='queued' AND NEW.state IN ('claimed','cancelled')) OR (OLD.state='claimed' AND NEW.state IN ('accepted','rejected','unknown','cancelled'))) OR (OLD.claimed_at IS NOT NULL AND (NEW.claimed_at IS NOT OLD.claimed_at OR NEW.lease_expires_at IS NOT OLD.lease_expires_at))");
        DB::unprepared("CREATE TRIGGER intents_lease BEFORE UPDATE ON otp_delivery_intents WHEN NEW.lease_expires_at IS NOT NULL AND NEW.lease_expires_at != min(NEW.claimed_at+30000,(SELECT expires_at FROM phone_otp_challenges WHERE id=NEW.challenge_id)) BEGIN SELECT RAISE(ABORT,'Invalid dispatch lease'); END");

        $this->create('contact_verification_proofs', [...$binding(), $this->ulid('challenge_id'), $this->state('channel', ['sms']), $this->state('provenance', ['simulated', 'live']),
            $this->state('policy_version', ['application-phone-proof-v1']), $this->integer('verified_at'), $this->integer('expires_at'),
            $this->state('state', ['available', 'consumed', 'revoked'], 'available'), $this->integer('state_version', 1, false, 1),
            $this->integer('consumed_at', 0, true), $this->ulid('consumed_application_id', true), $this->integer('revoked_at', 0, true),
            $this->text('reason_code', 50, true)." CHECK(reason_code IS NULL OR reason_code IN ('contact_changed','owner_changed','authority_lost','security_incident','policy_retired','operator_revoked'))",
        ], [...$this->tenantFks(), $contactFk,
            $this->foreign(['challenge_id', 'merchant_id', 'contact_id', 'actor_user_id', 'purpose', 'provenance'], 'phone_otp_challenges', ['id', 'merchant_id', 'contact_id', 'actor_user_id', 'purpose', 'transport_mode']),
            $this->foreign(['consumed_application_id', 'merchant_id'], 'merchant_applications', ['id', 'merchant_id']),
            'UNIQUE(challenge_id)', 'UNIQUE(id,merchant_id,actor_user_id)', 'UNIQUE(consumed_application_id)',
            'CHECK(expires_at=verified_at+1800000)', 'CHECK((consumed_at IS NULL)=(consumed_application_id IS NULL))',
            'CHECK(consumed_at IS NULL OR (consumed_at>=verified_at AND consumed_at<expires_at))', 'CHECK((revoked_at IS NULL)=(reason_code IS NULL))',
            'CONSTRAINT proof_revocation_chronology CHECK(revoked_at IS NULL OR consumed_at IS NULL OR revoked_at>=consumed_at)',
            "CHECK((state='available' AND consumed_at IS NULL AND revoked_at IS NULL) OR (state='consumed' AND consumed_at IS NOT NULL AND revoked_at IS NULL) OR (state='revoked' AND revoked_at IS NOT NULL AND revoked_at>=verified_at))",
        ]);
        $this->index('contact_verification_proofs', 'expiry', ['merchant_id', 'actor_user_id', 'purpose', 'expires_at']);
        $this->index('contact_verification_proofs', 'contact', ['contact_id', 'merchant_id', 'actor_user_id', 'purpose']);
        $this->index('contact_verification_proofs', 'lineage', ['challenge_id', 'merchant_id', 'contact_id', 'actor_user_id', 'purpose', 'provenance']);
        $this->index('contact_verification_proofs', 'application', ['consumed_application_id', 'merchant_id']);
        $this->index('contact_verification_proofs', 'actor', ['actor_user_id']);
        $this->insertState('contact_verification_proofs', 'available');
        $this->immutable('contact_verification_proofs', ['id', 'merchant_id', 'contact_id', 'actor_user_id', 'purpose', 'challenge_id', 'channel', 'provenance', 'policy_version', 'verified_at', 'expires_at'], 'state_version',
            "NOT((OLD.state='available' AND NEW.state IN ('consumed','revoked')) OR (OLD.state='consumed' AND NEW.state='revoked')) OR ((NEW.state='revoked' OR OLD.consumed_at IS NOT NULL) AND (NEW.consumed_at IS NOT OLD.consumed_at OR NEW.consumed_application_id IS NOT OLD.consumed_application_id))");
        DB::unprepared("CREATE TRIGGER proofs_consumed_challenge BEFORE INSERT ON contact_verification_proofs WHEN NOT EXISTS(SELECT 1 FROM phone_otp_challenges WHERE id=NEW.challenge_id AND state='consumed' AND terminal_at=NEW.verified_at AND digest_created_at IS NOT NULL) BEGIN SELECT RAISE(ABORT,'Consumed challenge required'); END");

        $slots = [];
        $checks = [];
        for ($i = 1; $i <= 10; $i++) {
            $name = sprintf('failure_%02d_at', $i);
            $slots[] = $this->integer($name, 0, true);
            $checks[] = "CHECK($name IS NULL OR $name<=last_observed_at)";
            if ($i > 1) {
                $prior = sprintf('failure_%02d_at', $i - 1);
                $checks[] = "CHECK($name IS NULL OR ($prior IS NOT NULL AND $name>=$prior))";
            }
        }
        $this->create('verification_limit_state', [$this->hex('scope_key'), $this->identifier('scope_key_version'), $this->state('policy_rule', ['otp.fail.rolling30.v1']),
            ...$this->tenant(), $this->integer('state_version', 1, false, 1), $this->integer('last_observed_at'), $this->integer('updated_at'), $this->integer('last_send_at', 0, true), ...$slots,
        ], [...$this->tenantFks(), 'PRIMARY KEY(scope_key,scope_key_version,policy_rule)', 'UNIQUE(merchant_id,actor_user_id,scope_key_version,policy_rule,scope_key)',
            'CHECK(updated_at=last_observed_at)', 'CHECK(last_send_at IS NULL OR last_send_at<=last_observed_at)', ...$checks]);
        $this->index('verification_limit_state', 'cleanup', ['policy_rule', 'updated_at']);
        $this->index('verification_limit_state', 'actor', ['actor_user_id']);
        $this->immutable('verification_limit_state', ['scope_key', 'scope_key_version', 'policy_rule', 'merchant_id', 'actor_user_id'], 'state_version', 'NEW.last_observed_at<OLD.last_observed_at OR (OLD.last_send_at IS NOT NULL AND (NEW.last_send_at IS NULL OR NEW.last_send_at<OLD.last_send_at))');
    }

    public function down(): void
    {
        $this->guard();
        foreach (array_reverse(self::TABLES) as $table) {
            DB::statement('DROP TABLE IF EXISTS '.$table);
        }
    }
};
