<?php

namespace App\Actions\Payments;

/** QR Model 2, version 6-L, byte mode, fixed mask 0. No remote rendering service. */
final class CheckoutQr
{
    public static function svg(string $text): string
    {
        abort_unless(strlen($text) <= 134 && preg_match('/\A[\x20-\x7e]+\z/D', $text) === 1, 422);
        $bits = '0100'.str_pad(decbin(strlen($text)), 8, '0', STR_PAD_LEFT);
        foreach (str_split($text) as $character) {
            $bits .= str_pad(decbin(ord($character)), 8, '0', STR_PAD_LEFT);
        }
        $bits .= str_repeat('0', min(4, 1088 - strlen($bits)));
        $bits .= str_repeat('0', (8 - strlen($bits) % 8) % 8);
        $data = array_map(bindec(...), str_split($bits, 8));
        for ($i = 0; count($data) < 136; $i++) {
            $data[] = $i % 2 === 0 ? 236 : 17;
        }
        $blocks = array_chunk($data, 68);
        $ecc = array_map(self::remainder(...), $blocks);
        $codewords = [];
        for ($i = 0; $i < 68; $i++) {
            foreach ($blocks as $block) {
                $codewords[] = $block[$i];
            }
        }
        for ($i = 0; $i < 18; $i++) {
            foreach ($ecc as $block) {
                $codewords[] = $block[$i];
            }
        }
        $stream = '';
        foreach ($codewords as $byte) {
            $stream .= str_pad(decbin($byte), 8, '0', STR_PAD_LEFT);
        }
        $modules = array_fill(0, 41, array_fill(0, 41, false));
        $reserved = $modules;
        $set = function (int $x, int $y, bool $dark) use (&$modules, &$reserved): void {
            if ($x >= 0 && $x < 41 && $y >= 0 && $y < 41) {
                $modules[$y][$x] = $dark;
                $reserved[$y][$x] = true;
            }
        };
        for ($i = 0; $i < 41; $i++) {
            $set(6, $i, $i % 2 === 0);
            $set($i, 6, $i % 2 === 0);
        }
        foreach ([[3, 3], [37, 3], [3, 37]] as [$cx, $cy]) {
            for ($dy = -4; $dy <= 4; $dy++) {
                for ($dx = -4; $dx <= 4; $dx++) {
                    $distance = max(abs($dx), abs($dy));
                    $set($cx + $dx, $cy + $dy, $distance !== 2 && $distance !== 4);
                }
            }
        }
        for ($dy = -2; $dy <= 2; $dy++) {
            for ($dx = -2; $dx <= 2; $dx++) {
                $set(34 + $dx, 34 + $dy, max(abs($dx), abs($dy)) !== 1);
            }
        }
        $format = function () use ($set): void {
            $value = 0x77C4;
            $bit = fn (int $i): bool => (($value >> $i) & 1) !== 0;
            for ($i = 0; $i <= 5; $i++) {
                $set(8, $i, $bit($i));
            }
            $set(8, 7, $bit(6));
            $set(8, 8, $bit(7));
            $set(7, 8, $bit(8));
            for ($i = 9; $i < 15; $i++) {
                $set(14 - $i, 8, $bit($i));
            }
            for ($i = 0; $i < 8; $i++) {
                $set(40 - $i, 8, $bit($i));
            }
            for ($i = 8; $i < 15; $i++) {
                $set(8, 41 - 15 + $i, $bit($i));
            }
            $set(8, 33, true);
        };
        $format();
        $index = 0;
        for ($right = 40; $right >= 1; $right -= 2) {
            if ($right === 6) {
                $right = 5;
            }
            for ($vertical = 0; $vertical < 41; $vertical++) {
                for ($j = 0; $j < 2; $j++) {
                    $x = $right - $j;
                    $y = (($right + 1) & 2) === 0 ? 40 - $vertical : $vertical;
                    if (! $reserved[$y][$x]) {
                        $dark = $index < strlen($stream) && $stream[$index] === '1';
                        $modules[$y][$x] = $dark !== (($x + $y) % 2 === 0);
                        $index++;
                    }
                }
            }
        }
        $path = '';
        foreach ($modules as $y => $row) {
            foreach ($row as $x => $dark) {
                if ($dark) {
                    $path .= 'M'.($x + 4).','.($y + 4).'h1v1h-1z';
                }
            }
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 49 49" width="245" height="245" role="img" aria-label="XLipa checkout QR code" shape-rendering="crispEdges"><rect width="49" height="49" fill="white"/><path d="'.$path.'" fill="black"/></svg>';
    }

    private static function multiply(int $x, int $y): int
    {
        $result = 0;
        for ($i = 7; $i >= 0; $i--) {
            $result = ($result << 1) ^ (($result >> 7) * 0x11D);
            $result ^= (($y >> $i) & 1) * $x;
        }

        return $result;
    }

    private static function remainder(array $data): array
    {
        $divisor = array_fill(0, 18, 0);
        $divisor[17] = 1;
        $root = 1;
        for ($i = 0; $i < 18; $i++) {
            for ($j = 0; $j < 18; $j++) {
                $divisor[$j] = self::multiply($divisor[$j], $root) ^ ($j + 1 < 18 ? $divisor[$j + 1] : 0);
            }
            $root = self::multiply($root, 2);
        }
        $result = array_fill(0, 18, 0);
        foreach ($data as $byte) {
            $factor = $byte ^ array_shift($result);
            $result[] = 0;
            for ($i = 0; $i < 18; $i++) {
                $result[$i] ^= self::multiply($divisor[$i], $factor);
            }
        }

        return $result;
    }
}
