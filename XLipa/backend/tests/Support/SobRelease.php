<?php

namespace Tests\Support;

use LogicException;

/** Fixed synthetic text assets only; selection returns no executable PHP. */
final class SobRelease
{
    public const FILES = ['artifact.txt', 'contract.json', 'executor.txt', 'policy.json'];

    public static function executorBytes(): string
    {
        $files = [];
        foreach (['SobFixture.php', 'SobPermit.php', 'SobWitnessRegister.php', 'SobWindowsLease.php', 'SobRelease.php', 'SobFence.php', 'SobCustody.php', 'SobBoundary.php', 'sob-windows-lease.ps1'] as $name) {
            $files[$name] = hash_file('sha256', __DIR__.'/'.$name);
        }

        return SobFixture::canonical($files);
    }

    public static function policyBytes(): string
    {
        return SobFixture::canonical(['v' => 1, 'environment' => 'sob-synthetic-only', 'working_execution' => 'DISABLED', 'max_validity_ms' => 300000, 'max_duration_ms' => 60000, 'witness' => 'required', 'journal' => 'delete-only']);
    }

    public static function verifyHeld(string $root, array $reviewed, SobWindowsLease $lease): void
    {
        SobFixture::root($root);
        $lease->alive();
        $actual = array_values(array_diff(scandir($root), ['.', '..']));
        sort($actual);
        $expected = self::FILES;
        sort($expected);
        if ($actual !== $expected) {
            throw new LogicException('SOB release inventory rejected.');
        }
        foreach ($reviewed as $file => $hash) {
            if (! is_string($hash) || ! preg_match('/^[a-f0-9]{64}$/D', $hash) || hash('sha256', SobFixture::read($root, $file)) !== $hash) {
                throw new LogicException('SOB release hash rejected.');
            }
        }
        if ($reviewed['executor.txt'] !== hash('sha256', self::executorBytes()) || $reviewed['policy.json'] !== hash('sha256', self::policyBytes())) {
            throw new LogicException('SOB executor/policy source pin rejected.');
        }
    }

    public static function select(string $root, array $reviewed, SobPermit $permit, array $requested): SobWindowsLease
    {
        SobFixture::root($root);
        if ($requested !== ['artifact.txt'] || array_keys($reviewed) !== self::FILES || $permit->data['release'] !== hash('sha256', SobFixture::canonical($reviewed))
            || $permit->data['executor'] !== $reviewed['executor.txt'] || $permit->data['policy'] !== $reviewed['policy.json']) {
            throw new LogicException('SOB release selection rejected before access.');
        }
        if ($reviewed['executor.txt'] !== hash('sha256', self::executorBytes()) || $reviewed['policy.json'] !== hash('sha256', self::policyBytes())) {
            throw new LogicException('SOB executor/policy source pin rejected.');
        }
        $actual = array_values(array_diff(scandir($root), ['.', '..']));
        sort($actual);
        $expected = self::FILES;
        sort($expected);
        if ($actual !== $expected) {
            throw new LogicException('SOB release inventory rejected.');
        }
        $lease = new SobWindowsLease($root, self::FILES);
        try {
            self::verifyHeld($root, $reviewed, $lease);

            return $lease;
        } catch (\Throwable $error) {
            $lease->close();
            throw $error;
        }
    }
}
