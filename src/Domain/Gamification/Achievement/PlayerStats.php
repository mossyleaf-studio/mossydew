<?php

declare(strict_types=1);

namespace App\Domain\Gamification\Achievement;

final readonly class PlayerStats
{
    public function __construct(
        public int $tasksCompleted = 0,
        public int $doFirstCompleted = 0,
        public int $scheduleCompleted = 0,
        public int $tasksClassified = 0,
        public int $bestStreak = 0,
        public int $level = 1,
        public int $dewGathered = 0,
        public int $expeditions = 0,
        public int $glasshouseLevel = 1,
        public int $goalsReached = 0,
        public int $casesSolved = 0,
    ) {
    }
}
