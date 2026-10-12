<?php

declare(strict_types=1);

namespace App\Domain\Gamification\Achievement\Rule;

use App\Domain\Gamification\Achievement\PlayerStats;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 5)]
final readonly class CommonGround extends Threshold
{
    public function id(): string
    {
        return 'common_ground';
    }

    protected function measure(PlayerStats $stats): int
    {
        return $stats->goalsReached;
    }

    protected function target(): int
    {
        return 1;
    }
}
