<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ORIGINAL = ['merchant.created', 'membership.revoked', 'application.submitted', 'application.reviewed', 'platform_role.granted', 'platform_role.revoked', 'account.status_changed', 'merchant.status_changed'];

    private const ADDITIONS = ['identity.recorded', 'identity.changed', 'contact.created', 'contact.changed', 'otp.requested', 'otp.changed', 'otp.verified', 'contact.proof_changed'];

    private function guard(): void
    {
        $settings = config('database.connections.'.config('database.default'), []);
        if (! app()->environment('testing') || getenv('XLIPA_SYNTHETIC_ONLY') !== '1'
            || ($settings['driver'] ?? null) !== 'sqlite' || ($settings['database'] ?? null) !== ':memory:' || ! empty($settings['url'])) {
            throw new LogicException('Audit reconstruction is approved only in disposable memory SQLite.');
        }
    }

    private function rebuild(array $events): void
    {
        $this->guard();
        if (DB::getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') {
            throw new LogicException('Audit reconstruction is approved only in disposable memory SQLite.');
        }
        foreach (DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'") as $table) {
            $name = str_replace('"', '""', $table->name);
            foreach (DB::select('PRAGMA foreign_key_list("'.$name.'")') as $foreign) {
                if ($foreign->table === 'audit_logs') {
                    throw new LogicException('Unexpected inbound audit dependency; separate review required.');
                }
            }
        }
        DB::transaction(function () use ($events): void {
            $source = DB::selectOne("SELECT sql FROM sqlite_master WHERE type='table' AND name='audit_logs'")->sql;
            $objects = DB::select("SELECT sql FROM sqlite_master WHERE tbl_name='audit_logs' AND type IN ('index','trigger') AND sql IS NOT NULL");
            $values = implode(',', array_map(fn (string $value): string => "'$value'", $events));
            $sql = preg_replace('/check\s*\(\s*["`\[]?event_type["`\]]?\s+in\s*\([^)]*\)\s*\)/i', 'CHECK(event_type IN ('.$values.'))', $source, -1, $count);
            if ($count !== 1) {
                throw new LogicException('Unexpected audit CHECK; refusing reconstruction.');
            }
            $sql = preg_replace('/^create table\s+["`\[]?audit_logs["`\]]?/i', 'CREATE TABLE audit_logs_b32_rebuild', $sql, 1, $count);
            if ($count !== 1) {
                throw new LogicException('Unexpected audit table definition.');
            }
            DB::statement($sql);
            DB::statement('INSERT INTO audit_logs_b32_rebuild SELECT * FROM audit_logs');
            if (DB::table('audit_logs')->count() !== DB::table('audit_logs_b32_rebuild')->count()) {
                throw new LogicException('Audit preservation failed.');
            }
            DB::statement('DROP TABLE audit_logs');
            DB::statement('ALTER TABLE audit_logs_b32_rebuild RENAME TO audit_logs');
            foreach ($objects as $object) {
                DB::statement($object->sql);
            }
            if (DB::select('PRAGMA foreign_key_check') !== []) {
                throw new LogicException('Audit foreign key preservation failed.');
            }
        });
    }

    public function up(): void
    {
        $this->rebuild([...self::ORIGINAL, ...self::ADDITIONS]);
    }

    public function down(): void
    {
        $this->guard();
        // Never delete or rename an event to make a downgrade succeed.
        if (DB::table('audit_logs')->whereNotIn('event_type', self::ORIGINAL)->exists()) {
            throw new LogicException('New audit history prevents downgrade.');
        }
        $this->rebuild(self::ORIGINAL);
    }
};
