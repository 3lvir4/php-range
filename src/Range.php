<?php

declare(strict_types=1);

namespace Elvir4\PhpRange;

use Generator;
use InvalidArgumentException;

/**
 * Represents a numeric range with inclusive upper and lower bounds with a negative or positive step.
 *
 * This class provides functionality to work with numeric ranges, allowing operations
 * such as checking for inclusion, determining the size, and other things.
 *
 * The behavior is *partially* inspired by Elixir ranges:
 * @link https://hexdocs.pm/elixir/1.15.7/Range.html Elixir v1.15.7 Range module documentation.
 */
class Range
{
    /**
     * @var int The lower bound of the range.
     */
    private int $lower;

    /**
     * @var int The upper bound of the range.
     */
    private int $upper;

    /**
     * @var int The step between elements in the range.
     */
    private int $step;

    /**
     * @var bool Indicates whether the range is empty.
     */
    private bool $isEmpty = false;

    /**
     * @var Range|null Singleton instance representing an empty range.
     */
    private static ?Range $emptyInstance = null;

    /**
     * Step defaults to 1 if the lower bound is less than or equal to the upper bound.
     * Otherwise, defaults to 1.
     *
     * @param int $lower The lower bound of the range.
     * @param int $upper The upper bound of the range.
     * @param int $step The step between elements in the range. If set to 0, it is automatically determined.
     */
    public function __construct(
        int $lower,
        int $upper,
        int $step = 0
    ) {
        if ($step === 0) {
            $step = $lower <= $upper ? 1 : -1;
        }

        $this->lower = $lower;
        $this->upper = $upper;
        $this->step = $step;
        if (($lower > $upper && $step > 0) || ($lower < $upper && $step < 0)) {
            $this->isEmpty = true;
        }
    }

    /**
     * Converts
     * @param string $str Elixir range notation.
     * @return Range
     */
    final public static function fromExFmt(string $str): Range
    {
        [$lower, $rest] = explode("..", $str);

        $step = 0;
        $upper = $rest;
        if (str_contains($rest, "//")) {
            [$upper, $step] = explode("//", $rest);
        }

        return new Range((int) $lower, (int) $upper, (int) $step);
    }

    /**
     * Construct a range from an array of the form [lowerBound, upperBound, step].
     * @param array{int, int, int} $packed
     * @psalm-param list{int, int, int} $packed
     * @return Range
     */
    final public static function fromPacked(array $packed): Range
    {
        if (count($packed) !== 3
            || !array_is_list($packed)
            || !is_int($packed[0])
            || !is_int($packed[1])
            || !is_int($packed[2])
        ) throw new InvalidArgumentException("$packed is not a valid input for Range::fromPacked().");

        return new Range(...$packed);
    }

    /**
     * Returns an empty range.
     * @return Range
     */
    public static function Empty(): Range {
        return self::$emptyInstance === null
            ? self::$emptyInstance = new Range(0, 1, -1)
            : self::$emptyInstance;
    }

    /**
     * Shifts the range in-place by the given number of steps.
     * @param int $steps
     * @return void
     */
    public function shiftMut(int $steps): void
    {
        $this->lower += $this->step * $steps;
        $this->upper += $this->step * $steps;
    }

    /**
     * Returns a new range shifted by the given number of steps.
     * @psalm-mutation-free
     * @param int $steps
     * @return Range
     */
    public function shift(int $steps): Range
    {
        return new Range(
            $this->lower + $this->step * $steps,
            $this->upper + $this->step * $steps,
            $this->step
        );
    }

    /**
     * @return bool True if the range evaluates to no values. False otherwise.
     */
    public function isEmpty(): bool {
        return $this->isEmpty;
    }

    /**
     * Check if the range contains the given integer value.
     * @psalm-mutation-free
     * @param int $num
     * @return bool
     */
    public function contains(int $num): bool
    {
        if ($this->isEmpty) return false;

        if ($this->step === 1) {
            return $this->lower <= $num && $num <= $this->upper;
        }

        if ($this->lower <= $this->upper) {
            return
                $this->lower <= $num && $num <= $this->upper
                && ($num - $this->lower) % $this->step === 0;
        } else {
            return
                $this->upper <= $num && $num <= $this->lower
                && ($num - $this->lower) % $this->step === 0;
        }
    }

    /**
     * Check if the range passed as a parameter is a subset of this range.
     * It follows math set-inclusion rules. So, for given ranges A, B:
     *  - if A is empty, A includes B only if B is also empty
     *  - if B is empty, A includes B
     *  - otherwise, A includes B if all elements of B are in A
     * @psalm-mutation-free
     * @param Range $other
     * @return bool
     */
    public function includes(Range $other): bool
    {
        if ($this->isEmpty) {
            return $other->isEmpty;
        }
        if ($other->isEmpty) return true;

        if ($other->isSingle()) return $this->contains($other->lower);

        if (abs($this->step) === 1) return $this->contains($other->lower) && $this->contains($other->last());

        if ($other->step % $this->step !== 0) return false;

        if ($this->contains($other->lower) === false) return false;

        if (abs($this->step) === abs($other->step))
            return $this->contains($other->lower) && $this->contains($other->last());

        return
            abs($this->step) < abs($other->step)
            && $other->size() <= $this->size();
    }

    /**
     * @psalm-mutation-free
     * @return bool True if the range evaluates to only one value. False otherwise.
     */
    public function isSingle(): bool
    {
        return $this->upper === $this->lower || $this->lower === $this->last();
    }

    /**
     * Returns the size of the range.
     * @psalm-mutation-free
     * @return int
     */
    public function size(): int
    {
        if ($this->isEmpty) return 0;
        return abs(intdiv($this->upper - $this->lower, $this->step)) + 1;
    }

    /**
     * Check if the range intersects with the other given range.
     * @psalm-mutation-free
     * @param Range $other
     * @return bool
     */
    public function intersects(Range $other): bool
    {
        if ($this->isEmpty || $other->isEmpty) return false;

        [$lower1, $upper1, $step1] = $this->normalizedBag();
        [$lower2, $upper2, $step2] = $other->normalizedBag();

        if ($lower1 > $upper2 || $lower2 > $upper1) return false;
        if ($step1 === 1 && $step2 === 1) return true;

        [$gcd, $u, $v] = Utils\extendedGCD(-$step1, $step2);
        if (($lower2 - $lower1) % $gcd !== 0) return false;

        $c = $lower1 - $lower2 + $step2 - $step1;
        $t1 = (-$c / $step2) * $u;
        $t2 = (-$c / $step1) * $v;

        $t = (int) max(floor($t1) + 1, floor($t2) + 1);
        $x = intdiv($c * $u + $t * $step2, $gcd) - 1;
        $y = intdiv($c * $v + $t * $step1, $gcd) - 1;

        return
            $x >= 0 && $y >= 0
            && $lower1 + $x * $step1 <= $upper1
            && $lower2 + $y * $step2 <= $upper2;
    }

    /**
     * @psalm-mutation-free
     * @return array<int>
     * @psalm-return list<int>
     */
    public function toList(): array
    {
        $l = [];
        if ($this->isEmpty) {
            return $l;
        }

        if ($this->step > 0) {
            for ($i = $this->lower; $i <= $this->upper; $i += $this->step) {
                $l[] = $i;
            }
        } else {
            for ($i = $this->lower; $i >= $this->upper; $i += $this->step) {
                $l[] = $i;
            }
        }
        return $l;
    }


    /**
     * Return the $n-th element of the range (0-based).
     * @psalm-mutation-free
     * @param int $n
     * @return int|null
     */
    public function nth(int $n): ?int
    {
        if ($this->isEmpty) return null;
        if ($n < 0) throw new InvalidArgumentException("Out of bounds."); // uninspired

        $v = $this->lower + $this->step * $n;
        if ($v > $this->upper) {
            return null;
        } else {
            return $v;
        }
    }


    /**
     * Returns a new range having the first $n elements of the range.
     * @psalm-mutation-free
     * @param int $n
     * @return Range
     */
    public function take(int $n): Range
    {
        if ($this->isEmpty) return clone $this;
        if ($n < 0) throw new InvalidArgumentException("Can't take less than 0 elements.");
        if ($n === 0) {
            return Range::Empty();
        }

        $last = $this->lower + $n * $this->step;
        if  (abs($last) > abs($this->upper)) {
            $last = $this->last();
        }

        return new Range(
            $this->lower,
            $last,
            $this->step
        );
    }

    /**
     * Truncate the range in-place, leaving only the first $n elements.
     * @param int $n
     * @return void
     */
    public function takeMut(int $n): void
    {
        if ($this->isEmpty) return;
        if ($n < 0) throw new InvalidArgumentException("Can't take less than 0 elements.");
        if ($n === 0) {
            $this->lower = 0;
            $this->upper = -1;
            $this->step = 1;
            $this->isEmpty = true;
            return;
        }

        $last = $this->lower + $n * $this->step;
        if  (abs($last) > abs($this->upper)) {
            $last = $this->last();
        }
        $this->upper = $last;
    }

    /**
     * Returns a new range skipping the $n first elements of the range.
     * @psalm-mutation-free
     * @param int $n
     * @return Range
     */
    public function skip(int $n): Range
    {
        return new Range(
            $this->lower + $n * $this->step,
            $this->upper,
            $this->step
        );
    }

    /**
     * Truncate the range in-place, leaving only the elements after the $n-th element.
     * @param int $n
     * @return void
     */
    public function skipMut(int $n): void
    {
        $this->lower = $this->lower + $n * $this->step;
        $this->ensureIsEmptyConsistency();
    }

    /**
     * @psalm-mutation-free
     * @return Range Reversed range.
     */
    public function rev(): Range
    {
        return new Range($this->upper, $this->lower, -$this->step);
    }

    /**
     * Reverse the range in place.
     * @return void
     */
    public function revMut(): void
    {
        $tmp = $this->lower;
        $this->lower = $this->upper;
        $this->upper = $tmp;
        $this->step = -$this->step;
    }

    /**
     * @psalm-mutation-free
     * @template O
     * @param callable(int): O $f
     * @return O[]
     */
    public function map(callable $f): array
    {
        $result = [];
        if ($this->step > 0) {
            for ($i = $this->lower; $i <= $this->upper; $i += $this->step) {
                $result[] = $f($i);
            }
        } else {
            for ($i = $this->lower; $i >= $this->upper; $i += $this->step) {
                $result[] = $f($i);
            }
        }
        return $result;
    }

    /**
     * Returns the result of the addition of both ranges.
     * @psalm-mutation-free
     * @param Range $other
     * @return Range
     */
    public function add(Range $other): Range
    {
        if ($this->isEmpty) return clone $other;
        if ($other->isEmpty) return clone $this;

        return new Range(
            $this->lower + $other->lower,
            $this->upper + $other->upper,
            $this->step + $other->step
        );
    }

    /**
     * Add the provided range to this range.
     * @param Range $other
     * @return void
     */
    public function addMut(Range $other): void
    {
        if ($other->isEmpty) return;
        if ($this->isEmpty) {
            $this->lower = $other->lower;
            $this->upper = $other->upper;
            $this->step = $other->step;
            return;
        }

        $this->lower += $other->lower;
        $this->upper += $other->upper;
        $this->step += $other->upper;
        $this->ensureIsEmptyConsistency();
        return;
    }

    /**
     * Returns the arithmetic negation of the range.
     * @psalm-mutation-free
     * @return Range
     */
    public function neg(): Range {
        return new Range(
            -$this->lower,
            -$this->upper,
            -$this->step
        );
    }

    /**
     * Arithmetically negates the range in-place.
     * @return void
     */
    public function negMut(): void
    {
        $this->lower = -$this->lower;
        $this->upper = -$this->upper;
        $this->step = -$this->step;
    }

    /**
     * Returns the result of the subtraction of both ranges.
     * @psalm-mutation-free
     * @param Range $other
     * @return Range
     */
    public function sub(Range $other): Range
    {
        if ($this->isEmpty) return clone $other;
        if ($other->isEmpty) return clone $this;

        return new Range(
            $this->lower - $other->lower,
            $this->upper - $other->upper,
            $this->step - $other->step
        );
    }

    /**
     * Subtract the provided range from theis range.
     * @param Range $other
     * @return void
     */
    public function subMut(Range $other): void
    {
        if ($other->isEmpty) return;
        if ($this->isEmpty) {
            $this->lower = -$other->lower;
            $this->upper = -$other->upper;
            $this->step = -$other->step;
            return;
        }

        $this->lower -= $other->lower;
        $this->upper -= $other->upper;
        $this->step -= $other->upper;
        $this->ensureIsEmptyConsistency();
        return;
    }

    /**
     * @psalm-mutation-free
     * @param int $factor
     * @return Range
     */
    public function scale(int $factor): Range
    {
        return new Range(
            $this->lower * $factor,
            $this->upper * $factor,
            $this->step * $factor
        );
    }

    /**
     * Multiply the values of the range by the given factor.
     * @param int $factor
     * @return void
     */
    public function scaleMut(int $factor): void
    {
        $this->lower *= $factor;
        $this->upper *= $factor;
        $this->step *= $factor;
    }

    /**
     * @return bool
     */
    public function anyEven(): bool
    {
        if ($this->isSingle()) return $this->lower % 2 === 0;
        return !$this->isEmpty && ($this->lower % 2 === 0 || $this->step % 2 === 1);
    }

    /**
     * @return bool
     */
    public function anyOdd(): bool
    {
        if ($this->isSingle()) return $this->lower % 2 === 1;
        return !$this->isEmpty && ($this->lower % 2 === 1 || $this->step % 2 === 1);
    }

    /**
     * @return bool
     */
    public function allEven(): bool
    {
        if ($this->isSingle()) return $this->lower % 2 === 0;
        return !$this->isEmpty && $this->lower % 2 === 0 && $this->step % 2 === 0;
    }

    /**
     * @return bool
     */
    public function allOdd(): bool
    {
        if ($this->isSingle()) return $this->lower % 2 === 1;
        return !$this->isEmpty && $this->lower % 2 === 1 && $this->step % 2 === 0;
    }



    /**
     * Calculate the sum of all the values in the range.
     * @psalm-mutation-free
     * @return int
     */
    public function sum(): int
    {
        if ($this->isEmpty) return 0;

        $size = $this->size();
        return (int) ($size * ($this->lower + $this->last()) / 2);
    }

    /**
     * @psalm-mutation-free
     * @template O
     * @param O $acc
     * @param callable(int, O): O $f
     * @return O
     */
    public function reduce(mixed $acc, callable $f): mixed
    {
        if ($this->step > 0) {
            for ($i = $this->lower; $i <= $this->upper; $i += $this->step) {
                $acc = $f($i, $acc);
            }
        } else {
            for ($i = $this->lower; $i >= $this->upper; $i += $this->step) {
                $acc = $f($i, $acc);
            }
        }
        return $acc;
    }

    /**
     * @psalm-mutation-free
     * @return int|null
     */
    public function min(): ?int
    {
        if ($this->isEmpty) return null;
        if ($this->lower <= $this->upper) {
            return $this->lower;
        } else {
            return $this->last();
        }
    }

    /**
     * @psalm-mutation-free
     * @return int|null
     */
    public function max(): ?int
    {
        if ($this->isEmpty) return null;
        if ($this->lower > $this->upper) {
            return $this->lower;
        } else {
            return $this->last();
        }
    }

    /**
     * Strict bounds and step comparison.
     * Example:
     *   Range(1, 9, 2) equals to Range(1, 9, 2)
     *  **but** Range(1, 9, 2) not equals to Range(1, 10, 2)
     *  despite the fact that they both yields 1, 3, 5, 7, and 9.
     * @psalm-mutation-free
     * @param Range $other
     * @return bool
     */
    public function equalsTo(Range $other): bool
    {
        return $this->lower === $other->lower
            && $this->upper === $other->upper
            && $this->step === $other->step;
    }

    /**
     * Returns last element of the range.
     * @psalm-mutation-free
     * @return int|null
     */
    public function last(): ?int
    {
        if ($this->isEmpty) return null;
        return $this->lower + ($this->size() - 1) * $this->step;
    }

    /**
     * Returns first element of the range.
     * @psalm-mutation-free
     * @return int|null
     */
    public function first(): ?int
    {
        if ($this->isEmpty) return null;
        return $this->lower;
    }

    /**
     * Lower bound of the range.
     * @psalm-mutation-free
     * @return int
     */
    public function lower(): int
    {
        return $this->lower;
    }

    /**
     * Upper bound of the range.
     * @psalm-mutation-free
     * @return int
     */
    public function upper(): int
    {
        return $this->upper;
    }

    /**
     * Step of the range.
     * @psalm-mutation-free
     * @return int
     */
    public function step(): int
    {
        return $this->step;
    }

    /**
     * Returns the lower/upper bounds along with the step of the range in one go.
     * @psalm-mutation-free
     * @return array [lowerBound, upperBound, step]
     * @psalm-return list{int, int, int} [lowerBound, upperBound, step]
     */
    public function unpack(): array
    {
        return [$this->lower, $this->upper, $this->step];
    }

    /**
     * Returns a generator over the values of the range.
     * @psalm-mutation-free
     * @return Generator<int>
     */
    public function gen(): Generator
    {
        if ($this->isEmpty) { return; }
        if ($this->step > 0) {
            for ($i = $this->lower; $i <= $this->upper; $i += $this->step) {
                yield $i;
            }
        } else {
            for ($i = $this->lower; $i >= $this->upper; $i += $this->step) {
                yield $i;
            }
        }
    }

    private function normalizedBag(): array
    {
        if ($this->lower <= $this->upper) {
            return [$this->lower, $this->upper, $this->step];
        }

        $newLower = $this->lower - abs(intdiv($this->lower - $this->upper, $this->step) * $this->step);
        return [$newLower, $this->lower, -$this->step];
    }
    
    private function ensureIsEmptyConsistency(): void
    {
        if (($this->lower > $this->upper && $this->step > 0) || ($this->lower < $this->upper && $this->step < 0)) {
            $this->isEmpty = true;
        }
    }
}
