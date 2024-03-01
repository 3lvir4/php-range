<?php

declare(strict_types=1);

namespace Elvir4\PhpRange;

use Elvir4\FunFp\IterOps;
use Elvir4\FunFp\IterTrait;
use Iterator;
use function Elvir4\PhpRange\Utils\sign;

/**
 * @implements IterOps<int, int>
 * @implements Iterator<int, int>
 */
class RangeIter implements Iterator, IterOps
{
    /**
     * @use IterTrait<int, int>
     */
    use IterTrait;
    protected int $curr;
    protected int $index = 0;
    protected int $inBoundsFactor;
    protected bool $isEmptyRange = false;

    public function __construct(
        protected int $lower,
        protected int $upper,
        protected int $step
    ) {
        if (sign($this->upper - $this->lower) !== sign($this->step)) {
            $this->isEmptyRange = true;
        }

        $this->curr = $this->lower;
        if ($this->lower > $this->upper) {
            $this->inBoundsFactor = -1;
        } else {
            $this->inBoundsFactor = 1;
        }
    }

    public function current(): mixed
    {
        return $this->curr;
    }

    public function next(): void
    {
        $this->curr += $this->step;
        $this->index++;
    }

    public function key(): int
    {
        return $this->index;
    }

    public function valid(): bool
    {
        return !$this->isEmptyRange
            && ($this->curr - $this->lower) * $this->inBoundsFactor >= 0
            && ($this->curr - $this->upper) * $this->inBoundsFactor <= 0;
    }

    public function rewind(): void
    {
        $this->index = 0;
        $this->curr = $this->lower;
    }

    public function getIter(): Iterator
    {
        return $this;
    }
}