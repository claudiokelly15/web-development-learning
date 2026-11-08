<?php

namespace Tests\Support;

use Closure;
use LogicException;

/** SOB-1 fixtures only. No operational target, identity or key authority. */
final class SobFixture
{
    public static function guard(): void
    {
        if (PHP_SAPI !== 'cli' || getenv('APP_ENV') !== 'testing' || getenv('XLIPA_SYNTHETIC_ONLY') !== '1') {
            throw new LogicException('SOB synthetic bootstrap required.');
        }
    }

    public static function root(string $root): string
    {
        self::guard();
        $temp = str_replace('\\', '/', sys_get_temp_dir());
        if (! preg_match('~^'.preg_quote(rtrim($temp, '/'), '~').'/xlipa-sob-[a-f0-9]{20}$~D', $root)) {
            throw new LogicException('SOB fixture path rejected before access.');
        }
        if (is_link($root) || ! is_dir($root) || str_replace('\\', '/', realpath($root)) !== $root) {
            throw new LogicException('SOB fixture root alias rejected.');
        }

        return $root;
    }

    public static function canonical(mixed $value): string
    {
        if (is_array($value)) {
            if (! array_is_list($value)) {
                ksort($value, SORT_STRING);
            }
            foreach ($value as &$item) {
                $item = json_decode(self::canonical($item), true, 32, JSON_THROW_ON_ERROR);
            }
        }

        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    public static function decode(string $bytes): array
    {
        if (strlen($bytes) > 1048576) {
            throw new LogicException('SOB input bound exceeded.');
        }
        $value = json_decode($bytes, true, 32, JSON_THROW_ON_ERROR);
        if (! is_array($value) || self::canonical($value) !== $bytes) {
            throw new LogicException('SOB canonical encoding rejected.');
        }

        return $value;
    }

    public static function keys(array $value, array $keys): void
    {
        $actual = array_keys($value);
        sort($actual);
        sort($keys);
        if ($actual !== $keys) {
            throw new LogicException('SOB fields rejected.');
        }
    }

    public static function read(string $root, string $name): string
    {
        self::root($root);
        $path = $root.'/'.$name;
        if (! preg_match('/^[a-z][a-z0-9.-]*$/D', $name) || is_link($path) || ! is_file($path) || filesize($path) > 1048576) {
            throw new LogicException('SOB evidence missing or invalid.');
        }
        $bytes = file_get_contents($path);
        if ($bytes === false) {
            throw new LogicException('SOB evidence read failed.');
        }

        return $bytes;
    }

    public static function write($file, string $bytes): void
    {
        for ($offset = 0; $offset < strlen($bytes);) {
            $count = fwrite($file, substr($bytes, $offset));
            if ($count === false || $count === 0) {
                throw new LogicException('SOB I/O write failed.');
            }
            $offset += $count;
        }
        if (! fflush($file) || ! fsync($file)) {
            throw new LogicException('SOB durable write failed.');
        }
    }

    public static function create(string $root, string $name, string $bytes): void
    {
        self::root($root);
        if (! preg_match('/^[a-z][a-z0-9.-]*$/D', $name)) {
            throw new LogicException('SOB output name rejected.');
        }
        $file = @fopen($root.'/'.$name, 'x+b');
        if ($file === false) {
            throw new LogicException('SOB never overwrite output.');
        }
        try {
            self::write($file, $bytes);
        } finally {
            fclose($file);
        }
    }

    public static function phase(?Closure $hook, string $phase): void
    {
        if ($hook !== null) {
            $hook($phase);
        }
    }
}
