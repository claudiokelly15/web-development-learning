<?php

namespace Tests\Support;

use SQLite3;

/** Offline policy only: no database opener, filesystem access or runtime registration. */
final class OfflineInspectorAuthorizer
{
    public array $events = [];

    public string $category = 'unspecified';

    public function __invoke(int $action, ?string $first = null, ?string $second = null, ?string $database = null, ?string $source = null): int
    {
        $allowed = match ($action) {
            SQLite3::SELECT => true,
            SQLite3::READ => in_array($database, ['main', 'temp'], true)
                || ($database === null && $second === '' && is_string($first)),
            SQLite3::FUNCTION => in_array(strtolower($second ?? ''), ['sqlite_version', 'count', 'typeof', 'like'], true),
            SQLite3::TRANSACTION => in_array($first, ['BEGIN', 'ROLLBACK'], true),
            SQLite3::PRAGMA => $this->pragma($first, $second),
            default => false,
        };

        // Do not retain raw SQL, values, filenames or arbitrary callback arguments.
        $this->events[] = ['category' => $this->category, 'action' => $action, 'name' => $this->name($action), 'allowed' => $allowed];

        return $allowed ? SQLite3::OK : SQLite3::DENY;
    }

    private function pragma(?string $name, ?string $argument): bool
    {
        $name = strtolower($name ?? '');
        $reads = ['query_only', 'temp_store', 'foreign_keys', 'database_list', 'data_version', 'journal_mode', 'synchronous', 'page_size', 'encoding', 'schema_version', 'user_version', 'busy_timeout', 'compile_options', 'integrity_check', 'foreign_key_check'];
        if (in_array($name, $reads, true)) {
            return $argument === null || $argument === '';
        }

        // Metadata arguments are identifiers, never arbitrary SQL or configuration values.
        return in_array($name, ['table_info', 'index_list', 'index_info', 'foreign_key_list'], true)
            && is_string($argument) && preg_match('/\A[a-zA-Z_][a-zA-Z0-9_]{0,127}\z/D', $argument) === 1;
    }

    private function name(int $action): string
    {
        return match ($action) {
            SQLite3::SELECT => 'SELECT', SQLite3::READ => 'READ', SQLite3::FUNCTION => 'FUNCTION',
            SQLite3::TRANSACTION => 'TRANSACTION', SQLite3::PRAGMA => 'PRAGMA',
            SQLite3::INSERT => 'INSERT', SQLite3::UPDATE => 'UPDATE', SQLite3::DELETE => 'DELETE',
            SQLite3::CREATE_TABLE => 'CREATE_TABLE', SQLite3::DROP_TABLE => 'DROP_TABLE',
            SQLite3::ALTER_TABLE => 'ALTER_TABLE', SQLite3::ATTACH => 'ATTACH', SQLite3::DETACH => 'DETACH',
            default => 'OTHER_DENIED',
        };
    }
}
