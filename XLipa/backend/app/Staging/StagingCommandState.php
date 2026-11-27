<?php

namespace App\Staging;

/** Independent durable dispatch quarantine; no distributed transaction with workflow SQLite. */
final class StagingCommandState
{
    private static function path(string $command, string $suffix): string
    {
        StagingIdentity::check((bool) preg_match('/^[a-f0-9]{32}$/D', $command));

        return StagingIdentity::path(app('staging.context')['root']).'/workflow-command-'.$command.'.'.$suffix;
    }

    public static function begin(array $command): void
    {
        $handle = fopen(self::path($command['command'], 'claimed'), 'x');
        StagingIdentity::check($handle !== false);
        try {
            $body = StagingIdentity::encode($command);
            StagingIdentity::check(fwrite($handle, $body) === strlen($body) && fflush($handle) && fsync($handle));
        } finally {
            fclose($handle);
        }
    }

    public static function finish(array $command, string $state): void
    {
        StagingIdentity::check(in_array($state, ['completed', 'rejected', 'unresolved'], true));
        $handle = fopen(self::path($command['command'], 'outcome'), 'x');
        StagingIdentity::check($handle !== false);
        try {
            StagingIdentity::check(fwrite($handle, $state) === strlen($state) && fflush($handle) && fsync($handle));
        } finally {
            fclose($handle);
        }
    }

    public static function assertNotUnresolved(array $command): void
    {
        $claim = self::path($command['command'], 'claimed');
        if (file_exists($claim)) {
            $outcome = self::path($command['command'], 'outcome');
            StagingIdentity::check(is_file($outcome) && in_array(file_get_contents(StagingIdentity::path($outcome)), ['completed', 'rejected'], true));
        }
    }
}
