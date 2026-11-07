<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const FIELDS = ['pending_audit_event_id', 'pending_audit_challenge_id', 'pending_audit_reason_code', 'pending_audit_request_id', 'pending_audit_occurred_at'];

    private function guard(): void
    {
        $settings = config('database.connections.'.config('database.default'), []);
        if (! app()->environment('testing') || getenv('XLIPA_SYNTHETIC_ONLY') !== '1' || ($settings['driver'] ?? null) !== 'sqlite' || ($settings['database'] ?? null) !== ':memory:' || ! empty($settings['url'])) {
            throw new LogicException('R06 migration requires disposable memory SQLite.');
        }
        if (DB::getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:' || (int) DB::selectOne('PRAGMA foreign_keys')->foreign_keys !== 1) {
            throw new LogicException('R06 migration requires enforced isolated SQLite.');
        }
    }

    /** Split generated SQLite definitions without splitting CHECK or FK expressions. */
    private function definitions(string $sql): array
    {
        $start = strpos($sql, '(');
        $end = strrpos($sql, ')');
        if ($start === false || $end === false) {
            throw new LogicException('Unexpected scope schema.');
        }
        $body = substr($sql, $start + 1, $end - $start - 1);
        $parts = [];
        $depth = 0;
        $quote = null;
        $offset = 0;
        for ($i = 0; $i < strlen($body); $i++) {
            $char = $body[$i];
            if ($quote !== null) {
                if ($char === $quote) {
                    if (($body[$i + 1] ?? null) === $quote) {
                        $i++;
                    } else {
                        $quote = null;
                    }
                }

                continue;
            }
            if ($char === "'" || $char === '"') {
                $quote = $char;
            } elseif ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
            } elseif ($char === ',' && $depth === 0) {
                $parts[] = trim(substr($body, $offset, $i - $offset));
                $offset = $i + 1;
            }
        }
        if ($depth !== 0 || $quote !== null) {
            throw new LogicException('Unbalanced scope schema.');
        }
        $parts[] = trim(substr($body, $offset));

        return $parts;
    }

    private function additions(): array
    {
        $columns = [];
        foreach (['pending_audit_event_id', 'pending_audit_challenge_id'] as $field) {
            $columns[] = "$field TEXT NULL CHECK($field IS NULL OR (typeof($field)='text' AND length($field)=26 AND substr($field,1,1) BETWEEN '0' AND '7' AND $field NOT GLOB '*[^0123456789ABCDEFGHJKMNPQRSTVWXYZ]*'))";
        }
        $columns[] = "pending_audit_reason_code TEXT NULL CHECK(pending_audit_reason_code IS NULL OR (typeof(pending_audit_reason_code)='text' AND pending_audit_reason_code IN ('exhausted','aggregate_exhausted')))";
        $columns[] = "pending_audit_request_id TEXT NULL CHECK(pending_audit_request_id IS NULL OR (typeof(pending_audit_request_id)='text' AND length(pending_audit_request_id)=36 AND substr(pending_audit_request_id,9,1)='-' AND substr(pending_audit_request_id,14,1)='-' AND substr(pending_audit_request_id,19,1)='-' AND substr(pending_audit_request_id,24,1)='-' AND length(replace(pending_audit_request_id,'-',''))=32 AND replace(pending_audit_request_id,'-','') NOT GLOB '*[^0-9a-f]*'))";
        $columns[] = "pending_audit_occurred_at TEXT NULL CHECK(pending_audit_occurred_at IS NULL OR (typeof(pending_audit_occurred_at)='text' AND length(pending_audit_occurred_at)=23 AND pending_audit_occurred_at GLOB '[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9] [0-9][0-9]:[0-9][0-9]:[0-9][0-9].[0-9][0-9][0-9]'))";

        return $columns;
    }

    private function rebuild(bool $extend): void
    {
        foreach (DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'") as $table) {
            foreach (DB::select('PRAGMA foreign_key_list("'.str_replace('"', '""', $table->name).'")') as $fk) {
                if ($fk->table === 'verification_limit_state') {
                    throw new LogicException('Unexpected inbound scope dependency; review required.');
                }
            }
        }
        $definitions = $this->definitions(DB::selectOne("SELECT sql FROM sqlite_master WHERE type='table' AND name='verification_limit_state'")->sql);
        $oldColumns = array_map(fn (object $column): string => $column->name, DB::select('PRAGMA table_info(verification_limit_state)'));
        $objects = DB::select("SELECT sql FROM sqlite_master WHERE tbl_name='verification_limit_state' AND type IN ('index','trigger') AND sql IS NOT NULL AND name NOT LIKE 'r06_%'");
        if ($extend) {
            $position = array_find_key($definitions, fn (string $definition): bool => (bool) preg_match('/^(PRIMARY KEY|UNIQUE|FOREIGN KEY|CHECK|CONSTRAINT)\b/i', $definition));
            if ($position === null) {
                throw new LogicException('Missing scope constraints.');
            }
            array_splice($definitions, $position, 0, $this->additions());
            $empty = implode(' AND ', array_map(fn (string $field): string => "$field IS NULL", self::FIELDS));
            $full = implode(' AND ', array_map(fn (string $field): string => "$field IS NOT NULL", self::FIELDS));
            $definitions[] = "CONSTRAINT r06_pending_tuple CHECK(($empty) OR ($full))";
            $definitions[] = 'CONSTRAINT r06_pending_lineage FOREIGN KEY(pending_audit_challenge_id,merchant_id,actor_user_id) REFERENCES phone_otp_challenges(id,merchant_id,actor_user_id) ON UPDATE RESTRICT ON DELETE RESTRICT';
        } else {
            $definitions = array_values(array_filter($definitions, fn (string $definition): bool => ! preg_match('/^(pending_audit_|CONSTRAINT r06_)/', $definition)));
            $oldColumns = array_values(array_diff($oldColumns, self::FIELDS));
        }
        $columns = implode(',', $oldColumns);
        $before = DB::select('SELECT '.$columns.' FROM verification_limit_state ORDER BY scope_key,scope_key_version,policy_rule');
        DB::statement('CREATE TABLE verification_limit_state_r06_stage ('.implode(',', $definitions).')');
        DB::statement("INSERT INTO verification_limit_state_r06_stage ($columns) SELECT $columns FROM verification_limit_state");
        $after = DB::select('SELECT '.$columns.' FROM verification_limit_state_r06_stage ORDER BY scope_key,scope_key_version,policy_rule');
        if ($before !== $after && serialize($before) !== serialize($after)) {
            throw new LogicException('Scope data preservation failed.');
        }
        DB::statement('DROP TABLE verification_limit_state');
        DB::statement('ALTER TABLE verification_limit_state_r06_stage RENAME TO verification_limit_state');
        foreach ($objects as $object) {
            DB::statement($object->sql);
        }
        if (DB::select('PRAGMA foreign_key_check') !== []) {
            throw new LogicException('Scope foreign key preservation failed.');
        }
    }

    public function up(): void
    {
        $this->guard();
        DB::transaction(function (): void {
            DB::statement('CREATE UNIQUE INDEX r06_challenge_tenant_actor ON phone_otp_challenges(id,merchant_id,actor_user_id)');
            $this->rebuild(true);
            DB::statement('CREATE UNIQUE INDEX r06_pending_event ON verification_limit_state(pending_audit_event_id)');
            DB::statement('CREATE INDEX r06_pending_scan ON verification_limit_state(pending_audit_occurred_at,pending_audit_event_id) WHERE pending_audit_event_id IS NOT NULL');
            $this->triggers();
        });
    }

    private function triggers(): void
    {
        $pendingChanged = implode(' OR ', array_map(fn (string $field): string => "NEW.$field IS NOT OLD.$field", self::FIELDS));
        $accountingChanged = implode(' OR ', array_map(fn (string $field): string => "NEW.$field IS NOT OLD.$field", ['last_send_at', ...array_map(fn (int $i): string => sprintf('failure_%02d_at', $i), range(1, 10))]));
        $count = implode('+', array_map(fn (int $i): string => sprintf('(NEW.failure_%02d_at IS NOT NULL)', $i), range(1, 10)));
        $last = 'coalesce('.implode(',', array_map(fn (int $i): string => sprintf('NEW.failure_%02d_at', $i), range(10, 1))).')';
        $stamp = "strftime('%Y-%m-%d %H:%M:%S',CAST(NEW.last_observed_at/1000 AS INTEGER),'unixepoch') || printf('.%03d',NEW.last_observed_at % 1000)";
        DB::unprepared("CREATE TRIGGER r06_initial BEFORE INSERT ON verification_limit_state WHEN NEW.pending_audit_event_id IS NOT NULL BEGIN SELECT RAISE(ABORT,'R06 obligation requires counted update'); END");
        DB::unprepared("CREATE TRIGGER r06_pending_create BEFORE UPDATE ON verification_limit_state WHEN OLD.pending_audit_event_id IS NULL AND NEW.pending_audit_event_id IS NOT NULL BEGIN
            SELECT CASE WHEN ($stamp) IS NULL OR NEW.pending_audit_occurred_at IS NOT ($stamp) OR $last IS NOT NEW.last_observed_at THEN RAISE(ABORT,'R06 occurrence mismatch') END;
            SELECT CASE WHEN EXISTS(SELECT 1 FROM audit_logs WHERE id=NEW.pending_audit_event_id) THEN RAISE(ABORT,'R06 event already materialized') END;
            SELECT CASE WHEN NOT EXISTS(SELECT 1 FROM phone_otp_challenges c JOIN otp_delivery_intents d ON d.challenge_id=c.id WHERE c.id=NEW.pending_audit_challenge_id AND c.merchant_id=NEW.merchant_id AND c.actor_user_id=NEW.actor_user_id AND c.purpose='application_phone' AND d.request_id=NEW.pending_audit_request_id AND d.merchant_id=NEW.merchant_id AND d.actor_user_id=NEW.actor_user_id AND ((NEW.pending_audit_reason_code='aggregate_exhausted' AND ($count)=10 AND c.failed_attempts BETWEEN 1 AND 5 AND c.state IN ('active','exhausted')) OR (NEW.pending_audit_reason_code='exhausted' AND ($count)<10 AND c.failed_attempts=5 AND c.state='exhausted' AND c.terminal_at=NEW.last_observed_at))) THEN RAISE(ABORT,'R06 pending lineage or threshold mismatch') END;
        END");
        DB::unprepared("CREATE TRIGGER r06_pending_immutable BEFORE UPDATE ON verification_limit_state WHEN OLD.pending_audit_event_id IS NOT NULL AND NEW.pending_audit_event_id IS NOT NULL AND (($pendingChanged) OR ($accountingChanged)) BEGIN SELECT RAISE(ABORT,'R06 pending obligation immutable'); END");
        DB::unprepared("CREATE TRIGGER r06_pending_discharge BEFORE UPDATE ON verification_limit_state WHEN OLD.pending_audit_event_id IS NOT NULL AND NEW.pending_audit_event_id IS NULL BEGIN
            SELECT CASE WHEN ($accountingChanged) OR NEW.last_observed_at IS NOT OLD.last_observed_at OR NEW.updated_at IS NOT OLD.updated_at THEN RAISE(ABORT,'R06 discharge cannot alter accounting') END;
            SELECT CASE WHEN NOT EXISTS(SELECT 1 FROM audit_logs a WHERE a.id=OLD.pending_audit_event_id AND a.event_type='otp.changed' AND a.actor_user_id=OLD.actor_user_id AND a.merchant_id=OLD.merchant_id AND a.target_user_id IS NULL AND a.application_id IS NULL AND a.request_id=OLD.pending_audit_request_id AND a.occurred_at=OLD.pending_audit_occurred_at AND a.metadata=('{\"reason_code\":\"' || OLD.pending_audit_reason_code || '\"}')) THEN RAISE(ABORT,'R06 exact audit required') END;
        END");
        foreach (['UPDATE', 'DELETE'] as $operation) {
            DB::unprepared('CREATE TRIGGER r06_audit_no_'.strtolower($operation)." BEFORE $operation ON audit_logs BEGIN SELECT RAISE(ABORT,'Audit history is append-only'); END");
        }
    }

    public function down(): void
    {
        $this->guard();
        DB::transaction(function (): void {
            if (DB::table('verification_limit_state')->whereNotNull('pending_audit_event_id')->exists()) {
                throw new LogicException('R06 pending obligations prevent downgrade.');
            }
            foreach (['r06_initial', 'r06_pending_create', 'r06_pending_immutable', 'r06_pending_discharge', 'r06_audit_no_update', 'r06_audit_no_delete'] as $name) {
                DB::statement('DROP TRIGGER '.$name);
            }
            $this->rebuild(false);
            DB::statement('DROP INDEX r06_challenge_tenant_actor');
        });
    }
};
