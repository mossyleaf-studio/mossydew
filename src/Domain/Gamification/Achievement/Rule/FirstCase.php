<?php

declare(strict_types=1);

namespace App\Domain\Gamification\Achievement\Rule;

use App\Domain\Gamification\Achievement\PlayerStats;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 85)]
final readonly class FirstCase extends Threshold
{
    public function id(): string
    {
        return 'first_case';
    }

    protected function measure(PlayerStats $stats): int
    {
        return $stats->casesSolved;
    }

    protected function target(): int
    {
        return 1;
    }
}
