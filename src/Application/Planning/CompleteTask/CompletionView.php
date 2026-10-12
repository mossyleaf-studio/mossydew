<?php

declare(strict_types=1);

namespace App\Application\Planning\CompleteTask;

use App\Application\Gamification\PlayerView;
use App\Application\Gamification\SpeciesView;
use App\Application\Planning\TaskView;
use App\Domain\Gamification\Greenhouse\DewGain;
use App\Domain\Gamification\Reward;

final readonly class CompletionView
{
    /**
     * @param list<SpeciesView> $newSpecies
     * @param list<string>      $goalsReached
     */
    public function __construct(
        public TaskView $task,
        public ?Reward $reward,
        public ?DewGain $dew,
        public ?int $leveledUpTo,
        public array $newSpecies,
        public PlayerView $player,
        public ?TaskView $parent = null,
        public array $goalsReached = [],
    ) {
    }
}
