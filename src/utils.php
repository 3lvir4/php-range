<?php

declare(strict_types=1);

namespace Elvir4\PhpRange\Utils;

use InvalidArgumentException;

/**
 * @internal
 * Extended GCD algorithm implementation.
 * https://en.wikipedia.org/wiki/Extended_Euclidean_algorithm
 * @param int $a
 * @param int $b
 * @return array [gcd, u, v]
 * @psalm-return list{int, int, int} [gcd, u, v]
 */
function extendedGCD(int $a, int $b): array
{
    if ($a == 0) {
        return [$b, 0, 1];
    }
    if ($b == 0) {
        return [$a, 1, 0];
    }

    $sign_a = sign($a); $sign_b = sign($b);
    $a = abs($a); $b = abs($b);

    $x2 = 1;
    $x1 = 0;
    $y2 = 0;
    $y1 = 1;
    while ($b > 0) {
        $q  = \intdiv($a, $b);
        $r  = $a % $b;
        $x  = $x2 - ($q * $x1);
        $y  = $y2 - ($q * $y1);
        $x2 = $x1;
        $x1 = $x;
        $y2 = $y1;
        $y1 = $y;
        $a  = $b;
        $b  = $r;
    }
    return [$a, $sign_a * $x2, $sign_b * $y2];
}

/**
 * @internal
 * Math sign function implementation.
 * For given n, it returns the following:
 *   n > 0 => 1
 *   n < 0 => -1
 *   n == 0 => 0
 * @param int $n
 * @return int either 0, 1 or -1
 */
function sign(int $n): int
{
    if ($n < 0) {
        return -1;
    } elseif ($n > 0) {
        return 1;
    } else {
        return 0;
    }
}


