<?php

namespace App\Actions\Payments;

use Closure;
use LogicException;
use PDO;
use Throwable;

/** Explicitly supplied payment store. No default connection or operational binding. */
class PaymentStore
{
    private bool $quarantined = false;

    private bool $active = false;

    public function __construct(private readonly PDO $pdo)
    {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') {
            throw new LogicException('Payment store requires SQLite.');
        }
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec('PRAGMA recursive_triggers=ON');
    }

    public function rows(string $sql, array $parameters = []): array
    {
        if ($this->quarantined) {
            throw new PaymentOutcomeUnknown;
        }
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);

        return $statement->fetchAll();
    }

    public function write(string $sql, array $parameters = []): int
    {
        if ($this->quarantined) {
            throw new PaymentOutcomeUnknown;
        }
        if (! $this->active) {
            throw new LogicException('Payment writes require a reserved transaction.');
        }
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);

        return $statement->rowCount();
    }

    public function transaction(Closure $operation): mixed
    {
        if ($this->quarantined) {
            throw new PaymentOutcomeUnknown;
        }
        if ($this->active || $this->pdo->inTransaction()) {
            throw new LogicException('Nested payment transaction denied.');
        }
        if ((int) $this->pdo->query('PRAGMA foreign_keys')->fetchColumn() !== 1 || (int) $this->pdo->query('PRAGMA recursive_triggers')->fetchColumn() !== 1
            || (int) $this->pdo->query('PRAGMA ignore_check_constraints')->fetchColumn() !== 0 || (int) $this->pdo->query('PRAGMA writable_schema')->fetchColumn() !== 0) {
            throw new LogicException('Payment store constraint enforcement is unavailable.');
        }
        $this->pdo->exec('BEGIN IMMEDIATE');
        $this->active = true;
        $committing = false;
        try {
            $result = $operation();
            $committing = true;
            $this->commit();

            return $result;
        } catch (Throwable $error) {
            try {
                $this->pdo->exec('ROLLBACK');
            } catch (Throwable) {
                $committing = true;
            }
            if ($committing) {
                $this->quarantined = true;
                throw new PaymentOutcomeUnknown;
            }
            throw $error;
        } finally {
            $this->active = false;
        }
    }

    protected function commit(): void
    {
        $this->pdo->exec('COMMIT');
    }
}
