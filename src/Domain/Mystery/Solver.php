<?php

declare(strict_types=1);

namespace App\Domain\Mystery;

final readonly class Solver
{
    /**
     * @param list<Gender> $genders
     * @param list<Clue>   $clues
     */
    public function count(Floorplan $plan, int $victim, array $genders, array $clues, int $budget): ?int
    {
        $search = new Search($plan, $victim, $genders, $clues, $budget);

        return $search->run() ? \count($search->solutions) : null;
    }
}
