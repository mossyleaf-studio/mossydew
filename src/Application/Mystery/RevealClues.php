<?php

declare(strict_types=1);

namespace App\Application\Mystery;

use App\Domain\Identity\User;
use App\Domain\Mystery\InvestigationRepository;
use App\Domain\Planning\Task;

final readonly class RevealClues
{
    public function __construct(private InvestigationRepository $investigations)
    {
    }

    /**
     * @param list<Task> $rewarded
     */
    public function __invoke(User $owner, array $rewarded): int
    {
        if ([] === $rewarded || !$owner->playsMysteries()) {
            return 0;
        }
        $investigation = $this->investigations->openLockedOf($owner);
        if (null === $investigation) {
            return 0;
        }
        $revealed = 0;
        foreach ($rewarded as $task) {
            if ($investigation->revealFor($task->id())) {
                ++$revealed;
            }
        }

        return $revealed;
    }
}
