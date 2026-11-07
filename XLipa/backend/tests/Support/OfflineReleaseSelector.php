<?php

namespace Tests\Support;

use FilesystemIterator;
use LogicException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class OfflineReleaseSelector
{
    public static function select(OfflinePermit $permit, string $root, string $manifestBytes, array $requested): array
    {
        $p = $permit->data();
        $prefix = rtrim(str_replace('\\', '/', sys_get_temp_dir()), '/').'/';
        $path = str_replace('\\', '/', $root);
        if (getenv('XLIPA_SYNTHETIC_ONLY') !== '1' || ! str_starts_with($path, $prefix) || ! preg_match('/^xlipa-offline-release-[a-f0-9]+$/D', substr($path, strlen($prefix)))
            || is_link($root) || realpath(dirname($root)) !== realpath(sys_get_temp_dir()) || ! is_dir($root)
            || strlen($manifestBytes) > 65536 || ! hash_equals($p['hashes']['release'], hash('sha256', $manifestBytes))) {
            throw new LogicException('Offline release rejected.');
        }
        $m = json_decode($manifestBytes, true, 8, JSON_THROW_ON_ERROR);
        if (! is_array($m) || array_keys($m) !== ['version', 'mode', 'artifact', 'contract', 'files'] || $m['version'] !== 1 || $m['mode'] !== 'offline-synthetic'
            || json_encode($m, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) !== $manifestBytes
            || $m['artifact'] !== 'activation/2026_11_07_200000_activate_b32_security_contract.php' || $m['contract'] !== 'activation/security-contract.json'
            || $requested !== [$m['artifact']] || array_keys($m['files']) !== [$m['artifact'], $m['contract']]
            || $m['files'][$m['artifact']] !== $p['hashes']['artifact'] || $m['files'][$m['contract']] !== $p['hashes']['contract']) {
            throw new LogicException('Offline release selection rejected.');
        }
        $found = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST) as $item) {
            if ($item->isLink()) {
                throw new LogicException('Offline release alias rejected.');
            }
            $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($root) + 1));
            if ($item->isDir()) {
                if ($relative !== 'activation') {
                    throw new LogicException('Offline release directory rejected.');
                }

                continue;
            }
            if (! isset($m['files'][$relative]) || ! is_string($m['files'][$relative]) || ! preg_match('/^[a-f0-9]{64}$/D', $m['files'][$relative])
                || ! hash_equals($m['files'][$relative], hash_file('sha256', $item->getPathname()))) {
                throw new LogicException('Offline release inventory rejected.');
            }
            $found[] = $relative;
        }
        $expected = array_keys($m['files']);
        sort($found);
        sort($expected);
        if ($found !== $expected) {
            throw new LogicException('Offline release inventory rejected.');
        }

        // Return data only. Never execute or include the selected migration.
        return ['manifest' => $m, 'artifact_hash' => $p['hashes']['artifact'], 'contract_hash' => $p['hashes']['contract']];
    }
}
