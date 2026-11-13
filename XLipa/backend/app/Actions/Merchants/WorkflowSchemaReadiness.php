<?php

namespace App\Actions\Merchants;

use Closure;
use Illuminate\Support\Facades\DB;
use LogicException;
use Throwable;

/** Selected complete schema capability. No operational activation or schema inference. */
final class WorkflowSchemaReadiness
{
    public const FOUNDATION = 'foundation';

    public const C = 'b32c';

    public const SE = 'se1';

    public const BNC = 'b32c+se1+bnc1';

    private const BNC_MANIFEST_HASH = '2a151dd33e7ce5735f38a9b3363d79375684b81bd728366285f05702499049a3';

    private const SOURCES = [
        'app/Actions/Merchants/WorkflowSchemaReadiness.php',
        'app/Actions/Merchants/ApplicationSubmissionService.php',
        'app/Actions/Merchants/LifecycleMutation.php',
        'app/Actions/Merchants/VerificationReadiness.php',
    ];

    public static function manifestPath(): string
    {
        return database_path('activation/bnc1/integration-manifest.json');
    }

    /** Build only during reviewed release preparation, never to repair admission failures. */
    public static function releaseDefinition(): array
    {
        $sources = [];
        foreach (self::SOURCES as $path) {
            $sources[$path] = hash_file('sha256', base_path($path));
        }

        return ['version' => 'BNC-INT-01', 'mode' => 'isolated-only',
            'profiles' => [self::FOUNDATION, self::C, self::SE, self::BNC],
            'bnc_manifest_sha256' => self::BNC_MANIFEST_HASH, 'source_pins' => $sources];
    }

    private function assertRelease(): void
    {
        $raw = file_get_contents(self::manifestPath());
        $manifest = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        if ($raw !== json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n" || $manifest !== self::releaseDefinition() || ! hash_equals(self::BNC_MANIFEST_HASH, hash_file('sha256', ComposedSubmissionSchema::manifestPath()))) {
            throw new LogicException('Workflow release mismatch.');
        }
        ComposedSubmissionSchema::assertSources();
    }

    private function expected(): string
    {
        $profile = config('workflow_schema.expected_profile');
        if ($profile instanceof Closure) {
            $profile = $profile();
        }
        if (! is_string($profile) || ! in_array($profile, [self::FOUNDATION, self::C, self::SE, self::BNC], true)) {
            throw new LogicException('Trusted workflow profile selection required.');
        }

        return $profile;
    }

    private function assertConnection(string $profile): void
    {
        if (DB::getDriverName() !== 'sqlite' || (int) DB::selectOne('PRAGMA foreign_keys')->foreign_keys !== 1 || (int) DB::selectOne('PRAGMA ignore_check_constraints')->ignore_check_constraints !== 0 || (int) DB::selectOne('PRAGMA writable_schema')->writable_schema !== 0 || ($profile !== self::FOUNDATION && (int) DB::selectOne('PRAGMA recursive_triggers')->recursive_triggers !== 1)) {
            throw new LogicException('Workflow constraint enforcement unavailable.');
        }
        $databases = array_column(DB::select('PRAGMA database_list'), 'name');
        if (! in_array($databases, [['main'], ['main', 'temp']], true) || DB::select('SELECT 1 FROM sqlite_temp_master') !== []) {
            throw new LogicException('Workflow attached or temporary schema denied.');
        }
    }

    private function assertLedger(string $profile): void
    {
        $ledger = ComposedSubmissionSchema::ledger(false);
        $length = match ($profile) {
            self::FOUNDATION => 9,
            self::C => 10,
            self::SE => 11,
        };
        $actual = array_map(fn (object $row): array => (array) $row, DB::select('SELECT id,migration,batch FROM migrations ORDER BY id'));
        if ($actual !== array_slice($ledger, 0, $length)) {
            throw new LogicException('Workflow exact isolated ledger mismatch.');
        }
    }

    private function assertCatalogue(string $profile): void
    {
        $foundation = json_decode(file_get_contents(database_path('activation/b32c/selection-manifest.json')), true, 64, JSON_THROW_ON_ERROR);
        $contract = json_decode(file_get_contents(database_path('activation/b32c/security-contract.json')), true, 64, JSON_THROW_ON_ERROR);
        $expected = [];
        foreach ($foundation['foundation_objects'] as $key => $hash) {
            [$type, $name] = explode(':', $key, 2);
            $expected[] = $name;
            if ($profile !== self::FOUNDATION && $name === 'audit_logs') {
                continue;
            }
            $row = DB::selectOne('SELECT type,sql FROM sqlite_master WHERE name=?', [$name]);
            if ($row?->type !== $type || ! hash_equals($hash, hash('sha256', $row?->sql ?? ''))) {
                throw new LogicException('Workflow foundation definition mismatch.');
            }
        }
        if ($profile === self::C) {
            $objects = $contract['create_tables'] + $contract['indexes'] + $contract['triggers'] + ['audit_logs' => $contract['audit']['sql']];
            foreach ($objects as $name => $sql) {
                if (DB::selectOne('SELECT sql FROM sqlite_master WHERE name=?', [$name])?->sql !== $sql) {
                    throw new LogicException('Workflow C definition mismatch.');
                }
                $expected[] = $name;
            }
        }
        $expected = array_values(array_unique($expected));
        sort($expected);
        $this->assertObjectNames($expected);
    }

    private function assertObjectNames(array $expected): void
    {
        if (array_column(DB::select("SELECT name FROM sqlite_master WHERE sql IS NOT NULL AND name!='sqlite_sequence' ORDER BY name"), 'name') !== $expected || DB::selectOne("SELECT sql FROM sqlite_master WHERE name='sqlite_sequence'")?->sql !== 'CREATE TABLE sqlite_sequence(name,seq)') {
            throw new LogicException('Workflow unknown or partial schema.');
        }
    }

    /** Revalidate on every call, including under the caller's reserved writer. */
    public function profile(): string
    {
        try {
            $profile = $this->expected();
            $this->assertRelease();
            $this->assertConnection($profile);
            if ($profile === self::BNC) {
                ComposedSubmissionSchema::assertReady();
            } elseif ($profile === self::SE) {
                if (! SubmissionEvidenceSchema::ready()) {
                    throw new LogicException('Workflow SE profile denied.');
                }
                $foundation = json_decode(file_get_contents(database_path('activation/b32c/selection-manifest.json')), true, 64, JSON_THROW_ON_ERROR);
                $contract = json_decode(file_get_contents(database_path('activation/b32c/security-contract.json')), true, 64, JSON_THROW_ON_ERROR);
                $names = [...array_map(fn (string $key): string => explode(':', $key, 2)[1], array_keys($foundation['foundation_objects'])), ...array_keys($contract['create_tables'] + $contract['indexes'] + $contract['triggers']), ...array_keys(SubmissionEvidenceSchema::objects())];
                $names = array_values(array_unique($names));
                sort($names);
                $this->assertObjectNames($names);
                $this->assertLedger($profile);
            } else {
                $this->assertCatalogue($profile);
                $this->assertLedger($profile);
            }
            if (DB::select('PRAGMA foreign_key_check') !== []) {
                throw new LogicException('Workflow foreign-key consistency unavailable.');
            }

            return $profile;
        } catch (Throwable) {
            abort(503, 'Workflow schema is unavailable.');
        }
    }

    public function submissionProfile(): string
    {
        $profile = $this->profile();
        abort_unless(in_array($profile, [self::SE, self::BNC], true), 503, 'Submission schema is unavailable.');

        return $profile;
    }
}
