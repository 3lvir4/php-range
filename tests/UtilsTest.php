<?php declare(strict_types=1);

namespace Elvir4\PhpRange\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use function Elvir4\PhpRange\Utils\extendedGCD;

class UtilsTest extends TestCase
{
    public static function extendedGCDProvided(): array {
        return [
            [708, 853, [1, 100, -83]],
            [305, 583, [1, 216, -113]],
            [414, 780, [6, 49, -26]],
            [117, -643, [1, 11, 2]],
            [788, -987, [1, -124, -99]],
            [621, 812, [1, 17, -13]],
            [-507, -706, [1, -149, 107]],
            [484, -576, [4, 25, 21]],
            [-858, 728, [26, 11, 13]],
            [175, -165, [5, -16, -17]],
        ];
    }

    #[DataProvider("extendedGCDProvided")]
    public function testExtendedGCD(int $a, int $b, array $expected): void
    {
        $this->assertEquals($expected, extendedGCD($a, $b));
    }
}
