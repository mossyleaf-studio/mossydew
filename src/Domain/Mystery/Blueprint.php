<?php

declare(strict_types=1);

namespace App\Domain\Mystery;

final readonly class Blueprint
{
    /**
     * @param list<string> $people
     * @param list<Gender> $genders
     * @param list<int>    $solution
     * @param list<Clue>   $clues
     * @param list<Clue>   $rules
     */
    public function __construct(
        public Floorplan $floorplan,
        public array $people,
        public array $genders,
        public int $victim,
        public array $solution,
        public array $clues,
        public array $rules,
    ) {
    }
}
