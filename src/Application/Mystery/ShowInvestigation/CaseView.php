<?php

declare(strict_types=1);

namespace App\Application\Mystery\ShowInvestigation;

use App\Domain\Mystery\Clue;
use App\Domain\Mystery\Gender;
use App\Domain\Mystery\Investigation;

/**
 * @phpstan-import-type ClueData from Clue
 */
final readonly class CaseView
{
    private function __construct(
        public string $id,
        public int $size,
        /** @var list<int> */
        public array $rooms,
        /** @var list<string> */
        public array $kinds,
        /** @var list<?string> */
        public array $furniture,
        /** @var list<string> */
        public array $people,
        /** @var list<string> */
        public array $genders,
        public int $victim,
        /** @var list<ClueData> */
        public array $clues,
        /** @var list<ClueData> */
        public array $rules,
        public int $hiddenClues,
        /** @var array{placements: list<?int>, crosses: list<int>} */
        public array $notes,
        public bool $solved,
        /** @var ?list<int> */
        public ?array $solution,
        public ?int $murderer,
    ) {
    }

    public static function of(Investigation $investigation): self
    {
        $floorplan = $investigation->floorplan()->toArray();
        $solved = $investigation->isSolved();

        return new self(
            (string) $investigation->id(),
            $floorplan['size'],
            $floorplan['rooms'],
            $floorplan['kinds'],
            $floorplan['furniture'],
            $investigation->people(),
            array_map(static fn (Gender $gender): string => $gender->value, $investigation->genders()),
            $investigation->victim(),
            array_map(static fn (Clue $clue): array => $clue->toArray(), $investigation->revealedClues()),
            array_map(static fn (Clue $rule): array => $rule->toArray(), $investigation->rules()),
            $investigation->hiddenClueCount(),
            $investigation->notes(),
            $solved,
            $solved ? $investigation->solution() : null,
            $solved ? $investigation->murderer() : null,
        );
    }
}
