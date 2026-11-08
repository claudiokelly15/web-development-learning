<?php

namespace Tests\Support;

use LogicException;

/** Explicit model only: does not control any real process, queue or scheduler. */
final class SobFence
{
    private bool $held = false;

    private int $generation = 0;

    private array $writers = ['web' => 0, 'queue' => 0, 'scheduler' => 0, 'cli' => 0, 'transaction' => 0];

    public function writer(string $kind, int $active): void
    {
        if (! array_key_exists($kind, $this->writers) || $active < 0) {
            $this->held = false;
            throw new LogicException('SOB unexpected writer.');
        }
        $this->writers[$kind] = $active;
        $this->generation++;
        if ($active > 0) {
            $this->held = false;
        }
    }

    public function acquire(): int
    {
        SobFixture::guard();
        if (array_sum($this->writers) !== 0) {
            throw new LogicException('SOB active writers refuse maintenance.');
        }
        $this->held = true;

        return ++$this->generation;
    }

    public function lose(): void
    {
        $this->held = false;
        $this->generation++;
    }

    public function assert(int $generation): void
    {
        if (! $this->held || $generation !== $this->generation || array_sum($this->writers) !== 0) {
            throw new LogicException('SOB writer fence lost or stale.');
        }
    }
}
