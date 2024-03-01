<?php

namespace Elvir4\PhpRange\Tests;

use Elvir4\PhpRange\Range;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use function Elvir4\FunFp\Helpers\op;

class RangeTest extends TestCase
{
    public static Range $emptyRange;

    protected function setUp(): void
    {
        parent::setUp();
        self::$emptyRange = Range::Empty();
        $this->assertTrue(self::$emptyRange->isEmpty());
    }

    public function testFromExFmt(): void
    {
        $r1 = Range::fromExFmt("2..14//3");
        $r2 = Range::fromExFmt("125..17");

        $this->assertEquals([2, 14, 3], $r1->unpack());
        $this->assertEquals([125, 17, -1], $r2->unpack());
    }

    public static function sizeProvider(): array {
        return [
            [-453, -913, 71, 0],
            [-63, -580, 99, 0],
            [422, -284, -81, 9],
            [-847, -766, -74, 0],
            [-670, 497, -28, 0],
            [680, -9, -1, 690],
            [-395, -629, -63, 4],
            [-349, -273, -35, 0],
            [-519, 577, -22, 0],
            [-377, 160, 8, 68],
            [687, 384, -81, 4],
            [-620, -569, 49, 2],
            [-660, -662, 96, 0],
            [-20, -420, -6, 67],
            [-9, -263, -23, 12],
            [-992, 510, -30, 0],
            [162, -841, -22, 46],
            [27, 566, -87, 0],
            [-796, 836, -68, 0],
            [-259, 21, -45, 0],
        ];
    }

    #[DataProvider("sizeProvider")]
    public function testSize($first, $last, $step, $expected): void
    {
        $r = new Range($first, $last, $step);
        $this->assertEquals($expected, $r->size());
    }

    public function testIncludes(): void
    {
        $r1 = new Range(1, 17, 3);
        $r2 = new Range(2, 15, 6);

        $this->assertFalse($r1->includes($r2) && $r2->includes($r1));

        $r1 = new Range(20, 1, -2);
        $r2 = new Range(2, 14, 2);

        $this->assertTrue($r1->includes($r2));
        $this->assertFalse($r2->includes($r1));

        $r1 = new Range(23, 7, -3);
        $r2 = new Range(8, 19, 6);

        $this->assertTrue($r1->includes($r2));
        $this->assertFalse($r2->includes($r1));
    }

    public function testMin(): void
    {
        $r1 = new Range(1, 10, 3);
        $r2 = new Range(2, -13, -2);

        $this->assertEquals(1, $r1->min());
        $this->assertEquals(-12, $r2->min());
        $this->assertNull(self::$emptyRange->min());
    }

    public function testMax(): void
    {
        $r1 = new Range(5, 18, 3);
        $r2 = new Range(7, -19, -2);

        $this->assertEquals(17, $r1->max());
        $this->assertEquals(7, $r2->max());
        $this->assertNull(self::$emptyRange->max());
    }

    public static function intersectsProvider(): array {
        return [
            [ [-408, 258, -72], [-832, 992, 51], false],
            [ [-163, -555, 48], [-139, -761, 27], false],
            [ [289, 12, -70], [-105, 12, -44], false],
            [ [-937, -260, 40], [666, -749, 97], false],
            [ [981, -329, 53], [-896, -566, 39], false],
            [ [453, 555, -93], [167, 299, 33], false],
            [ [-365, -240, 87], [420, -401, -2], true],
            [ [-274, 866, 2], [306, 265, 65], false],
            [ [446, 852, -83], [369, -662, 78], false],
            [ [955, 859, -69], [275, -463, -97], false],
            [ [230, 542, 68], [868, 596, 57], false],
            [ [716, -423, 28], [176, 953, 10], false],
            [ [608, -319, -77], [-856, -874, 59], false],
            [ [741, 738, -64], [-825, -884, 25], false],
            [ [-766, -911, -27], [-867, -676, 91], false],
            [ [-345, -789, 26], [-869, 928, 56], false],
            [ [1, -266, -56], [137, 825, -16], false],
            [ [717, 423, 19], [-979, 49, 34], false],
            [ [847, -790, -37], [-757, -850, -24], true],
            [ [-549, 94, -85], [-314, 824, 2], false],
            [ [-441, 429, -77], [85, 454, -59], false],
            [ [558, -668, 58], [-155, -649, -7], false],
            [ [426, 732, 2], [975, -506, -85], true],
            [ [-682, 689, -41], [-59, -247, -51], false],
            [ [-468, -803, 52], [-866, -398, -36], false],
            [ [-143, -14, -77], [-897, -316, 44], false],
            [ [-217, 24, 21], [352, 402, -31], false],
            [ [462, 651, 39], [-370, -233, -60], false],
            [ [-651, 262, -26], [151, 632, 68], false],
            [ [21, 533, 99], [-111, -767, 1], false],
        ];
    }

    #[DataProvider("intersectsProvider")]
    public function testIntersects(array $bag1, array $bag2, bool $expected): void
    {
        $r1 = new Range(...$bag1);
        $r2 = new Range(...$bag2);
        $this->assertEquals($expected, $r1->intersects($r2));
    }

    public function testSum(): void
    {
        $r1 = new Range(-147, 29, 8);
        $r2 = new Range(-9, -180, -3);

        $this->assertEquals(0, self::$emptyRange->sum());
        $this->assertEquals(
            $r1->reduce(0, fn($acc, $n) => $acc + $n),
            $r1->sum()
        );
        $this->assertEquals(
            $r2->reduce(0, fn($acc, $n) => $acc + $n),
            $r2->sum()
        );
    }

    public function testSkip(): void
    {
        $r1 = new Range(2, 11, 2);
        $r2 = new Range(4, -17, -3);
        $this->assertTrue((new Range(6, 11, 2))->equalsTo($r1->skip(2)));
        $this->assertTrue((new Range(-5, -17, -3))->equalsTo($r2->skip(3)));
        $this->assertTrue(self::$emptyRange->skip(15)->isEmpty(), "empty case");
    }

    public function testTake(): void
    {
        $r1 = new Range(2, 11, 2);
        $r2 = new Range(4, -17, -3);
        $r3 = new Range(-4, 17, 5);
        $r4 = new Range(7, -19, -4);

        $this->assertTrue(
            (new Range(2, 6, 2))->equalsTo($r1->take(2))
        );
        $this->assertTrue(
            (new Range(4, -5, -3))->equalsTo($r2->take(3))
        );
        $this->assertTrue(self::$emptyRange->take(15)->isEmpty(), "empty case");
        $this->assertTrue(
            (new Range(-4, 16, 5))->equalsTo($r3->take(6))
        );
        $this->assertTrue(
            (new Range(7, -17, -4))->equalsTo($r4->take(10))
        );
    }

    public function testContains(): void
    {
        $r1 = new Range(7, 523, 19);
        $r2 = new Range(16, -256, -33);

        $this->assertFalse(self::$emptyRange->contains(15));
        $this->assertTrue($r1->contains(349));
        $this->assertFalse($r1->contains(577));
        $this->assertFalse($r1->contains(241));

        $this->assertTrue($r2->contains(-149));
        $this->assertFalse($r2->contains(-380));
        $this->assertFalse($r2->contains(-21));
    }
}
