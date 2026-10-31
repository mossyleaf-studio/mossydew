<?php

declare(strict_types=1);

namespace App\Domain\Gamification\Achievement\Rule;

use App\Domain\Gamification\Achievement\PlayerStats;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 35)]
final readonly class TenCases extends Threshold
{
    public function id(): string
    {
        return 'ten_cases';
    }

    protected function measure(PlayerStats $stats): int
    {
        return $stats->casesSolved;
    }

    protected function target(): int
    {
        return 10;
    }
}
