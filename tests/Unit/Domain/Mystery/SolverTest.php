<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Mystery;

use App\Domain\Mystery\Clue;
use App\Domain\Mystery\ClueKind;
use App\Domain\Mystery\Floorplan;
use App\Domain\Mystery\Furniture;
use App\Domain\Mystery\Solver;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

final class SolverTest extends TestCase
{
    private const int BUDGET = 100_000;

    public function testFindsTheOnlySolution(): void
    {
        self::assertSame(1, new Solver()->count(FloorplanTest::manor(), 0, self::pinningClues(), self::BUDGET));
    }

    public function testFindsSeveralSolutionsOnceAClueIsMissing(): void
    {
        self::assertSame(2, new Solver()->count(FloorplanTest::manor(), 0, \array_slice(self::pinningClues(), 1), self::BUDGET));
    }

    public function testFindsNoSolutionForCluesThatContradictEachOther(): void
    {
        $clues = [
            new Clue(ClueKind::OnFurniture, person: 2, furniture: Furniture::Sofa),
            new Clue(ClueKind::InRow, person: 2, number: 0),
        ];

        self::assertSame(0, new Solver()->count(FloorplanTest::manor(), 0, $clues, self::BUDGET));
    }

    public function testNeverLeavesTheVictimAloneInARoom(): void
    {
        $clues = [];
        foreach ([1 => 7, 2 => 14, 3 => 16, 4 => 23] as $person => $cell) {
            $clues[] = new Clue(ClueKind::InRow, person: $person, number: intdiv($cell, 5));
            $clues[] = new Clue(ClueKind::InColumn, person: $person, number: $cell % 5);
        }

        self::assertSame(0, new Solver()->count(FloorplanTest::manor(), 0, $clues, self::BUDGET));
    }

    public function testGivesUpWhenTheBudgetRunsOut(): void
    {
        self::assertNull(new Solver()->count(FloorplanTest::manor(), 0, [], 1));
    }

    public function testAgreesWithAnExhaustiveCount(): void
    {
        $manor = FloorplanTest::manor();
        $placements = self::placements($manor, 0);
        $randomizer = new Randomizer(new Mt19937(7));
        $outcomes = [];
        for ($round = 0; $round < 60; ++$round) {
            $clues = 0 === $round % 2
                ? array_map(static fn (int $key): Clue => self::trueClues()[$key], $randomizer->pickArrayKeys(self::trueClues(), $randomizer->getInt(3, 8)))
                : array_map(static fn (): Clue => self::randomClue($randomizer), range(0, $randomizer->getInt(0, 5)));
            $expected = min(2, \count(array_filter(
                $placements,
                static fn (array $cells): bool => array_all($clues, static fn (Clue $clue): bool => $clue->holdsFor($manor, $cells)),
            )));
            $outcomes[$expected] = true;

            self::assertSame($expected, new Solver()->count($manor, 0, $clues, self::BUDGET), (string) json_encode(array_map(static fn (Clue $clue): array => $clue->toArray(), $clues)));
        }
        self::assertCount(3, $outcomes);
    }

    /**
     * @return list<Clue>
     */
    private static function pinningClues(): array
    {
        return [
            new Clue(ClueKind::NorthOf, person: 2, other: 1),
            new Clue(ClueKind::OnFurniture, person: 2, furniture: Furniture::Sofa),
            new Clue(ClueKind::BesideFurniture, person: 4, furniture: Furniture::Toilet),
            new Clue(ClueKind::SameRoom, person: 3, other: 4),
        ];
    }

    /**
     * @return non-empty-list<Clue>
     */
    private static function trueClues(): array
    {
        return [
            ...self::pinningClues(),
            new Clue(ClueKind::BesideFurniture, person: 4, furniture: Furniture::Bath),
            new Clue(ClueKind::InRoom, person: 3, room: 4),
            new Clue(ClueKind::WestOf, person: 1, other: 2),
            new Clue(ClueKind::EmptyRoom, room: 1),
            new Clue(ClueKind::EmptyRoom, room: 3),
            new Clue(ClueKind::RoomHolds, room: 4, number: 2),
            new Clue(ClueKind::InRow, person: 4, number: 4),
            new Clue(ClueKind::InColumn, person: 1, number: 1),
        ];
    }

    private static function randomClue(Randomizer $randomizer): Clue
    {
        $person = $randomizer->getInt(0, 4);
        $other = ($person + $randomizer->getInt(1, 4)) % 5;
        $room = $randomizer->getInt(0, 4);
        $furniture = Furniture::cases()[$randomizer->getInt(0, \count(Furniture::cases()) - 1)];

        return match (ClueKind::cases()[$randomizer->getInt(0, \count(ClueKind::cases()) - 1)]) {
            ClueKind::InRoom => new Clue(ClueKind::InRoom, person: $person, room: $room),
            ClueKind::OnFurniture => new Clue(ClueKind::OnFurniture, person: $person, furniture: $furniture),
            ClueKind::BesideFurniture => new Clue(ClueKind::BesideFurniture, person: $person, furniture: $furniture),
            ClueKind::InRow => new Clue(ClueKind::InRow, person: $person, number: $randomizer->getInt(0, 4)),
            ClueKind::InColumn => new Clue(ClueKind::InColumn, person: $person, number: $randomizer->getInt(0, 4)),
            ClueKind::SameRoom => new Clue(ClueKind::SameRoom, person: $person, other: $other),
            ClueKind::NorthOf => new Clue(ClueKind::NorthOf, person: $person, other: $other),
            ClueKind::WestOf => new Clue(ClueKind::WestOf, person: $person, other: $other),
            ClueKind::EmptyRoom => new Clue(ClueKind::EmptyRoom, room: $room),
            ClueKind::RoomHolds => new Clue(ClueKind::RoomHolds, room: $room, number: $randomizer->getInt(1, 3)),
        };
    }

    /**
     * @return list<list<int>>
     */
    private static function placements(Floorplan $plan, int $victim): array
    {
        $placements = [];
        foreach (self::permutations(range(0, $plan->size - 1)) as $rows) {
            foreach (self::permutations(range(0, $plan->size - 1)) as $columns) {
                $cells = array_map(static fn (int $row, int $column): int => $row * $plan->size + $column, $rows, $columns);
                if (!array_any($cells, $plan->blocks(...)) && null !== $plan->murdererIn($cells, $victim)) {
                    $placements[] = $cells;
                }
            }
        }

        return $placements;
    }

    /**
     * @param list<int> $items
     *
     * @return \Generator<int, list<int>>
     */
    private static function permutations(array $items): \Generator
    {
        if ([] === $items) {
            yield [];

            return;
        }
        foreach ($items as $index => $item) {
            $rest = $items;
            unset($rest[$index]);
            foreach (self::permutations(array_values($rest)) as $permutation) {
                yield [$item, ...$permutation];
            }
        }
    }
}
