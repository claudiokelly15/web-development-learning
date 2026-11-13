<?php

namespace Tests\Support;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use LogicException;

final class B32CRehearsal
{
    public static function artifact(): string
    {
        return database_path('activation/b32c/2026_11_07_200000_activate_b32_security_contract.php');
    }

    public static function foundation(): void
    {
        if (! app()->environment('testing') || getenv('XLIPA_SYNTHETIC_ONLY') !== '1' || config('database.connections.sqlite.database') !== ':memory:' || config('database.connections.sqlite.url')) {
            throw new LogicException('B32C foundation fixtures require isolated memory SQLite.');
        }
        DB::purge('sqlite');
        foreach (['0001_*.php', '2026_11_06_00000*.php'] as $glob) {
            $paths = array_map(fn (string $path): string => 'database/migrations/'.basename($path), glob(database_path('migrations/'.$glob)));
            if (Artisan::call('migrate', ['--database' => 'sqlite', '--path' => $paths, '--no-interaction' => true]) !== 0) {
                throw new LogicException('B32C foundation fixture migration failed.');
            }
        }
        DB::statement('PRAGMA recursive_triggers=ON');
    }

    public static function activate(): void
    {
        config(['b32c.selected' => realpath(self::artifact())]);
        $settings = config('database.connections.sqlite');
        $path = $settings['database'];
        $temporary = realpath(dirname($path)) === realpath(sys_get_temp_dir()) && preg_match('/^xlipa-b32-synthetic-[a-f0-9]+\.sqlite$/D', basename($path));
        if (! app()->environment('testing') || getenv('XLIPA_SYNTHETIC_ONLY') !== '1' || $settings['driver'] !== 'sqlite' || ! empty($settings['url']) || ($path !== ':memory:' && ! $temporary)) {
            throw new LogicException('B32C selector refuses non-fixture connections.');
        }
        $contract = json_decode(file_get_contents(database_path('activation/b32c/security-contract.json')), true, 64, JSON_THROW_ON_ERROR);
        if (DB::table('migrations')->where('migration', '2026_11_07_200000_activate_b32_security_contract')->exists()) {
            foreach ($contract['create_tables'] + $contract['indexes'] + $contract['triggers'] as $name => $sql) {
                $row = DB::selectOne('SELECT sql FROM sqlite_master WHERE name=?', [$name]);
                if ($row === null || $row->sql !== $sql) {
                    throw new LogicException('B32C quarantine: recorded activation schema mismatch.');
                }
            }
            $audit = DB::selectOne("SELECT sql FROM sqlite_master WHERE name='audit_logs' AND type='table'");
            if ($audit === null || $audit->sql !== $contract['audit']['sql']) {
                throw new LogicException('B32C quarantine: recorded audit schema mismatch.');
            }
        }
        if (Artisan::call('migrate', ['--database' => 'sqlite', '--path' => [self::artifact()], '--realpath' => true, '--no-interaction' => true]) !== 0) {
            throw new LogicException('B32C explicit migration failed.');
        }
    }

    public static function schema(): array
    {
        return DB::select("SELECT type,name,tbl_name,sql FROM sqlite_master WHERE name NOT LIKE 'sqlite_%' ORDER BY type,name");
    }
}
