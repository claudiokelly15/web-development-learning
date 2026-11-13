<?php

namespace Tests\Support;

use App\Actions\Merchants\TrustedCompletionTimePolicy;
use App\Actions\Merchants\TrustedReleaseApproval;
use App\Actions\Merchants\TrustedStoreLineage;
use Illuminate\Database\Connection;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;
use SplObjectStorage;

/** Fictional independent authorities; owns only explicitly constructed memory connections. */
final class FakeCompletionTrustAuthority implements TrustedCompletionTimePolicy, TrustedReleaseApproval, TrustedStoreLineage
{
    public bool $approvalAvailable = true;

    public bool $clockAvailable = true;

    public bool $lineageAvailable = true;

    public bool $cutover = false;

    public bool $reconciliation = true;

    public bool $recoveryHold = false;

    public int $time = 10000;

    public string $policy = 'fixture.retention.v1';

    public array $revoked = [];

    public array $runtime;

    public array $observed;

    public array $policies = ['fixture.retention.v1' => 5000];

    private ?int $lastTime = null;

    private array $approvals = [];

    private array $releases = [];

    private array $spent = [];

    private SplObjectStorage $connections;

    public function __construct(array $runtime, array $observed)
    {
        $this->isolated();
        $this->runtime = $runtime;
        $this->observed = $observed;
        $this->connections = new SplObjectStorage;
    }

    /** Fixture setup only: independent approval store, not descriptor self-approval. */
    public function approve(array $descriptor): void
    {
        $release = array_intersect_key($descriptor, array_flip(['release_id', 'manifests', 'sources', 'approved_profile']));
        $id = $descriptor['release_id'];
        $this->check(! isset($this->releases[$id]) || $this->releases[$id] === $release);
        $this->releases[$id] = $release;
        $this->approvals[$descriptor['approval_id']] = $descriptor;
    }

    public function approves(#[\SensitiveParameter] array $descriptor): bool
    {
        $this->isolated();
        $this->check($this->approvalAvailable);

        return ! in_array($descriptor['approval_id'], $this->revoked, true)
            && ($this->approvals[$descriptor['approval_id']] ?? null) === $descriptor;
    }

    public function milliseconds(): int
    {
        $this->isolated();
        $this->check($this->clockAvailable && $this->time >= 0 && $this->time <= 253402300799999);
        $this->check($this->lastTime === null || ($this->time >= $this->lastTime && $this->time - $this->lastTime <= 1000));
        $this->lastTime = $this->time;

        return $this->time;
    }

    public function recordPolicy(): string
    {
        $this->isolated();
        $this->check(isset($this->policies[$this->policy]));

        return $this->policy;
    }

    public function retentionAllowed(string $policy, int $occurredAt, bool $unresolved): bool
    {
        $now = $this->milliseconds();

        return isset($this->policies[$policy]) && is_int($this->policies[$policy]) && $this->policies[$policy] > 0
            && $occurredAt >= 0 && $occurredAt <= $now && $now - $occurredAt < $this->policies[$policy];
    }

    public function contextAccess(int $preparedAt, int $expiresAt, int $retainUntil, bool $unresolved, bool $resolving): bool
    {
        $now = $this->milliseconds();

        return $preparedAt >= 0 && $preparedAt <= $now && $preparedAt < $expiresAt && $expiresAt <= $retainUntil && $retainUntil <= 253402300799999
            && $now < $retainUntil && ($resolving ? $unresolved : (! $unresolved && ! $this->recoveryHold && $now < $expiresAt));
    }

    /** New object/PDO, explicitly enrolled in the current fictional lineage; no DB facade. */
    public function memoryConnection(): SQLiteConnection
    {
        $this->isolated();
        $pdo = new PDO('sqlite::memory:');
        $connection = new SQLiteConnection($pdo, ':memory:');
        $this->connections[$connection] = ['pdo' => $pdo, 'runtime' => $this->runtime];

        return $connection;
    }

    public function authoritative(Connection $connection, #[\SensitiveParameter] array $descriptor): bool
    {
        $this->isolated();
        if (! $this->lineageAvailable || $this->cutover || ! $this->connections->contains($connection)) {
            return false;
        }
        $enrolled = $this->connections[$connection];
        if ($connection->getDatabaseName() !== ':memory:' || $connection->getRawPdo() !== $enrolled['pdo'] || $enrolled['runtime'] !== $this->runtime) {
            return false;
        }
        foreach ($this->runtime as $key => $value) {
            if (($descriptor[$key] ?? null) !== $value) {
                return false;
            }
        }

        return true;
    }

    public function requestAllowed(int $actor, string $merchant, #[\SensitiveParameter] string $requestId, bool $resolving): bool
    {
        $this->isolated();

        return $this->lineageAvailable && ! $this->cutover && ($resolving || (! $this->recoveryHold && ! isset($this->spent[$actor.'|'.$merchant.'|'.$requestId])));
    }

    /** Pre-query guard for the existing admission interface; never opens a lazy connection. */
    public function managedMemoryReady(): bool
    {
        $this->isolated();
        $managed = DB::connection();
        if ($managed->getDatabaseName() !== ':memory:') {
            return false;
        }
        foreach ($this->connections as $connection) {
            $enrolled = $this->connections[$connection];
            if ($managed->getRawPdo() === $enrolled['pdo'] && $enrolled['runtime'] === $this->runtime) {
                return true;
            }
        }

        return false;
    }

    /** Fixture observation of a dispatched command; never cleared by an absent resolution. */
    public function dispatched(int $actor, string $merchant, string $requestId): void
    {
        $this->spent[$actor.'|'.$merchant.'|'.$requestId] = true;
    }

    private function isolated(): void
    {
        $this->check(getenv('XLIPA_SYNTHETIC_ONLY') === '1' && app()->environment('testing') && config('database.default') === 'sqlite' && config('database.connections.sqlite.database') === ':memory:' && ! config('database.connections.sqlite.url'));
    }

    private function check(bool $condition): void
    {
        if (! $condition) {
            throw new RuntimeException('Fictional trust authority is unavailable.');
        }
    }

    public function __debugInfo(): array
    {
        return ['authority' => '[FICTIONAL REDACTED]'];
    }

    public function __serialize(): array
    {
        throw new RuntimeException('Fictional authority serialization is prohibited.');
    }
}
