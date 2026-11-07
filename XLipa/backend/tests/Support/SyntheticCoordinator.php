<?php

namespace Tests\Support;

use Closure;
use LogicException;
use PDO;
use PDOException;
use Throwable;

final class SyntheticCoordinator
{
    public function __construct(private readonly PDO $pdo)
    {
        if (getenv('XLIPA_SYNTHETIC_ONLY') !== '1' || $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') {
            throw new LogicException('Isolated SQLite required.');
        }
        $path = $pdo->query('PRAGMA database_list')->fetch(PDO::FETCH_ASSOC)['file'];
        if ($path !== '' && (realpath(dirname($path)) !== realpath(sys_get_temp_dir()) || ! preg_match('/^xlipa-b32-synthetic-[a-f0-9]+\.sqlite$/D', basename($path)))) {
            throw new LogicException('Working databases are forbidden.');
        }
        $pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec('PRAGMA busy_timeout=250');
        if ((int) $pdo->query('PRAGMA foreign_keys')->fetchColumn() !== 1) {
            throw new LogicException('Foreign keys required.');
        }
    }

    public function run(Closure $operation): mixed
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                $this->pdo->exec('BEGIN IMMEDIATE');
                break;
            } catch (Throwable $error) {
                if ($error instanceof PDOException && in_array(($error->errorInfo[1] ?? null), [5, 6], true) && $attempt < 2) {
                    continue;
                }
                throw $error;
            }
        }
        try {
            $result = $operation();
            $this->pdo->exec('COMMIT');

            return $result;
        } catch (Throwable $error) {
            try {
                $this->pdo->exec('ROLLBACK');
            } catch (Throwable) {
                // Preserve the original unknown-outcome error; never rerun the operation.
            }
            throw $error;
        }
    }
}
