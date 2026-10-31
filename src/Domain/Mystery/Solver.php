<?php

declare(strict_types=1);

namespace App\Domain\Mystery;

final readonly class Solver
{
    /**
     * @param list<Clue> $clues
     */
    public function count(Floorplan $plan, int $victim, array $clues, int $budget): ?int
    {
        $search = new Search($plan, $victim, $clues, $budget);

        return $search->run() ? \count($search->solutions) : null;
    }
}
