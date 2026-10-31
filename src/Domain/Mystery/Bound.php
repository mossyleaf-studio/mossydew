<?php

declare(strict_types=1);

namespace App\Domain\Mystery;

enum Bound: string
{
    case Exactly = 'exactly';
    case AtLeast = 'at_least';
    case AtMost = 'at_most';

    public function admits(int $count, int $number): bool
    {
        return match ($this) {
            self::Exactly => $count === $number,
            self::AtLeast => $count >= $number,
            self::AtMost => $count <= $number,
        };
    }
}
