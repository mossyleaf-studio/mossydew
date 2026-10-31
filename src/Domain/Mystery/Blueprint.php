<?php

declare(strict_types=1);

namespace App\Domain\Mystery;

final readonly class Blueprint
{
    /**
     * @param list<string> $people
     * @param list<int>    $solution
     * @param list<Clue>   $clues
     */
    public function __construct(
        public Floorplan $floorplan,
        public array $people,
        public int $victim,
        public array $solution,
        public array $clues,
    ) {
    }
}
