<?php

namespace Tests\Support;

use Closure;
use LogicException;

/** Two fixture custody roots. Independence is simulated, never an ACL claim. */
final class SobWitnessRegister
{
    public function __construct(private readonly string $root, private readonly string $witness)
    {
        if (SobFixture::root($root) === SobFixture::root($witness)) {
            throw new LogicException('SOB separate witness required.');
        }
    }

    public static function initialize(string $root, string $witness): void
    {
        foreach ([$root, $witness] as $directory) {
            SobFixture::root($directory);
            if (scandir($directory) !== ['.', '..']) {
                throw new LogicException('SOB initialization refused.');
            }
        }
        SobFixture::create($root, 'register.log', '');
        SobFixture::create($witness, 'witness.log', '');
    }

    private function locked(Closure $operation): mixed
    {
        SobFixture::read($this->root, 'register.log');
        SobFixture::read($this->witness, 'witness.log');
        $primary = fopen($this->root.'/register.log', 'r+b');
        $witness = fopen($this->witness.'/witness.log', 'r+b');
        if ($primary === false || $witness === false) {
            throw new LogicException('SOB register unavailable.');
        }
        try {
            if (! flock($primary, LOCK_EX) || ! flock($witness, LOCK_EX)) {
                throw new LogicException('SOB register lock failed.');
            }
            $records = $this->records(stream_get_contents($primary, 1048577));
            $observed = $this->records(stream_get_contents($witness, 1048577));
            if ($records !== $observed) {
                throw new LogicException('SOB witness conflict or rollback.');
            }

            return $operation($records, $primary, $witness);
        } finally {
            flock($witness, LOCK_UN);
            flock($primary, LOCK_UN);
            fclose($witness);
            fclose($primary);
        }
    }

    private function records(string $bytes): array
    {
        if (strlen($bytes) > 1048576 || ($bytes !== '' && ! str_ends_with($bytes, "\n"))) {
            throw new LogicException('SOB register corrupt.');
        }
        $records = [];
        $seen = [];
        $runs = [];
        $head = str_repeat('0', 64);
        foreach (array_filter(explode("\n", $bytes), static fn (string $line): bool => $line !== '') as $line) {
            $row = SobFixture::decode($line);
            SobFixture::keys($row, ['seq', 'previous', 'id', 'run', 'permit', 'state', 'result', 'head']);
            $hash = $row['head'];
            unset($row['head']);
            if ($row['seq'] !== count($records) + 1 || $row['previous'] !== $head || hash('sha256', SobFixture::canonical($row)) !== $hash
                || ! in_array($row['state'], ['claimed', 'completed'], true) || ! preg_match('/^[a-f0-9]{32}$/D', $row['id']) || ! preg_match('/^[a-f0-9]{32}$/D', $row['run'])
                || ! preg_match('/^[a-f0-9]{64}$/D', $row['permit']) || ! preg_match('/^[a-f0-9]{64}$/D', $row['result'])) {
                throw new LogicException('SOB register corrupt.');
            }
            if ($row['state'] === 'claimed') {
                if (isset($seen[$row['id']]) || isset($runs[$row['run']]) || $row['result'] !== str_repeat('0', 64)) {
                    throw new LogicException('SOB register replay corruption.');
                }
                $runs[$row['run']] = true;
            } elseif (! isset($seen[$row['id']]) || $seen[$row['id']]['state'] !== 'claimed' || $seen[$row['id']]['run'] !== $row['run'] || $seen[$row['id']]['permit'] !== $row['permit']) {
                throw new LogicException('SOB register transition corruption.');
            }
            $row['head'] = $hash;
            $head = $hash;
            $seen[$row['id']] = $row;
            $records[] = $row;
        }

        return $records;
    }

    public function checkpoint(): int
    {
        return $this->locked(static fn (array $rows): int => count($rows));
    }

    private function append(SobPermit $permit, string $state, string $result, ?Closure $hook): void
    {
        $this->locked(function (array $rows, $primary, $witness) use ($permit, $state, $result, $hook): void {
            $p = $permit->data;
            $matching = array_values(array_filter($rows, static fn (array $row): bool => $row['id'] === $p['id'] || $row['run'] === $p['run']));
            if ($state === 'claimed') {
                if ($matching !== []) {
                    throw new LogicException('SOB permit consumed.');
                }
                if ($p['checkpoint'] !== count($rows)) {
                    throw new LogicException('SOB stale checkpoint.');
                }
            } elseif (count($matching) !== 1 || $matching[0]['id'] !== $p['id'] || $matching[0]['run'] !== $p['run'] || $matching[0]['permit'] !== $permit->digest || $matching[0]['state'] !== 'claimed') {
                throw new LogicException('SOB completion rejected.');
            }
            $row = ['seq' => count($rows) + 1, 'previous' => $rows === [] ? str_repeat('0', 64) : end($rows)['head'], 'id' => $p['id'], 'run' => $p['run'], 'permit' => $permit->digest, 'state' => $state, 'result' => $result];
            $row['head'] = hash('sha256', SobFixture::canonical($row));
            $bytes = SobFixture::canonical($row)."\n";
            fseek($primary, 0, SEEK_END);
            SobFixture::write($primary, $bytes);
            SobFixture::phase($hook, $state.'_primary');
            fseek($witness, 0, SEEK_END);
            SobFixture::write($witness, $bytes);
            SobFixture::phase($hook, $state.'_witnessed');
        });
    }

    public function claim(SobPermit $permit, ?Closure $hook = null): void
    {
        $this->append($permit, 'claimed', str_repeat('0', 64), $hook);
    }

    public function complete(SobPermit $permit, string $result, ?Closure $hook = null): void
    {
        if (! preg_match('/^[a-f0-9]{64}$/D', $result)) {
            throw new LogicException('SOB result digest invalid.');
        }
        $this->append($permit, 'completed', $result, $hook);
    }

    public function completed(string $id, string $run, string $digest): bool
    {
        return $this->locked(static function (array $rows) use ($id, $run, $digest): bool {
            foreach ($rows as $row) {
                if ($row['id'] === $id && $row['run'] === $run && $row['state'] === 'completed' && $row['result'] === $digest) {
                    return true;
                }
            }

            return false;
        });
    }
}
