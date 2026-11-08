<?php

namespace App\Actions\Merchants;

use Closure;
use Illuminate\Support\Facades\DB;
use LogicException;
use Throwable;

/** One SQLite writer acquisition. Never retry an operation after it begins. */
final class VerificationStore
{
    private bool $running = false;

    public function run(Closure $operation): mixed
    {
        $connection = DB::connection();
        if ($connection->getDriverName() !== 'sqlite' || $connection->transactionLevel() !== 0 || $this->running || $connection->getPdo()->inTransaction()) {
            throw new LogicException('Verification unavailable.');
        }
        $connection->beginTransaction();
        $this->running = true;
        try {
            // A zero-row write acquires SQLite's writer before any workflow state read, while Laravel tracks the transaction and suppresses query reconnect/retry.
            $connection->update('UPDATE merchant_onboarding_contexts SET context_version=context_version WHERE 0');
            $result = $operation();
            $connection->commit();

            return $result;
        } catch (Throwable $error) {
            try {
                $connection->rollBack();
            } catch (Throwable) {
            } throw $error;
        } finally {
            $this->running = false;
        }
    }

    public function change(string $table, array $where, array $values): void
    {
        if (DB::table($table)->where($where)->update($values) !== 1) {
            throw new LogicException('Verification state changed.');
        }
    }
}
