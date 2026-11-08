<?php

namespace App\Actions\Merchants;

use Closure;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use LogicException;
use Throwable;

/** One writer, one commit attempt; no reconnect or replay after an uncertain acknowledgement. */
class SubmissionTransaction
{
    public function run(Closure $operation): mixed
    {
        $connection = DB::connection();
        if ($connection->getDriverName() !== 'sqlite' || $connection->transactionLevel() !== 0 || $connection->getPdo()->inTransaction()) {
            throw new LogicException('Submission transaction unavailable.');
        }
        $connection->beginTransaction();
        $committing = false;
        try {
            $connection->update('UPDATE merchant_onboarding_contexts SET context_version=context_version WHERE 0');
            $result = $operation();
            $committing = true;
            $this->commit($connection);

            return $result;
        } catch (Throwable $error) {
            try {
                if ($connection->transactionLevel() > 0) {
                    $connection->rollBack();
                }
            } catch (Throwable) {
                $committing = true;
            }
            if ($committing) {
                DB::purge($connection->getName());
                throw new SubmissionOutcomeUnknown;
            }
            throw $error;
        }
    }

    protected function commit(Connection $connection): void
    {
        $connection->commit();
    }
}
