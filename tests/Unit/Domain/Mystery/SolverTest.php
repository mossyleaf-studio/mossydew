<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Mystery;

use App\Domain\Mystery\Bound;
use App\Domain\Mystery\Clue;
use App\Domain\Mystery\ClueKind;
use App\Domain\Mystery\Floorplan;
use App\Domain\Mystery\Furniture;
use App\Domain\Mystery\Gender;
use App\Domain\Mystery\Search;
use App\Domain\Mystery\Solver;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

final class SolverTest extends TestCase
{
    private const int BUDGET = 100_000;

    public function testFindsTheOnlySolution(): void
    {
        self::assertSame(1, new Solver()->count(FloorplanTest::manor(), 0, FloorplanTest::GENDERS, self::pinningClues(), self::BUDGET));
    }

    public function testFindsSeveralSolutionsOnceAClueIsMissing(): void
    {
        self::assertSame(2, new Solver()->count(FloorplanTest::manor(), 0, FloorplanTest::GENDERS, \array_slice(self::pinningClues(), 1), self::BUDGET));
    }

    public function testFindsNoSolutionForCluesThatContradictEachOther(): void
    {
        $clues = [
            new Clue(ClueKind::OnFurniture, person: 2, furniture: Furniture::Sofa),
            new Clue(ClueKind::InRow, person: 2, number: 0),
        ];

        self::assertSame(0, new Solver()->count(FloorplanTest::manor(), 0, FloorplanTest::GENDERS, $clues, self::BUDGET));
    }

    public function testNeverLeavesTheVictimAloneInARoom(): void
    {
        $clues = [];
        foreach ([1 => 7, 2 => 14, 3 => 16, 4 => 23] as $person => $cell) {
            $clues[] = new Clue(ClueKind::InRow, person: $person, number: intdiv($cell, 5));
            $clues[] = new Clue(ClueKind::InColumn, person: $person, number: $cell % 5);
        }

        self::assertSame(0, new Solver()->count(FloorplanTest::manor(), 0, FloorplanTest::GENDERS, $clues, self::BUDGET));
    }

    public function testGivesUpWhenTheBudgetRunsOut(): void
    {
        self::assertNull(new Solver()->count(FloorplanTest::manor(), 0, FloorplanTest::GENDERS, [], 1));
    }

    public function testAnExhaustedBudgetNeverProvesASingleSolution(): void
    {
        $search = new Search(FloorplanTest::manor(), 0, FloorplanTest::GENDERS, self::pinningClues(), self::BUDGET);
        $search->run();

        self::assertGreaterThan(1, $search->nodes);
        foreach (range(1, $search->nodes - 1) as $budget) {
            self::assertNull(new Solver()->count(FloorplanTest::manor(), 0, FloorplanTest::GENDERS, self::pinningClues(), $budget), (string) $budget);
        }
    }

    public function testAgreesWithAnExhaustiveCount(): void
    {
        $manor = FloorplanTest::manor();
        $placements = self::placements($manor, 0);
        $randomizer = new Randomizer(new Mt19937(7));
        $outcomes = [];
        for ($round = 0; $round < 240; ++$round) {
            $clues = 0 === $round % 2
                ? array_map(static fn (int $key): Clue => self::trueClues()[$key], $randomizer->pickArrayKeys(self::trueClues(), $randomizer->getInt(3, 8)))
                : array_map(static fn (): Clue => self::randomClue($randomizer), range(0, $randomizer->getInt(0, 5)));
            $expected = min(2, \count(array_filter(
                $placements,
                static fn (array $cells): bool => array_all($clues, static fn (Clue $clue): bool => $clue->holdsFor($manor, $cells, FloorplanTest::GENDERS)),
            )));
            $outcomes[$expected] = true;

            self::assertSame($expected, new Solver()->count($manor, 0, FloorplanTest::GENDERS, $clues, self::BUDGET), (string) json_encode(array_map(static fn (Clue $clue): array => $clue->toArray(), $clues)));
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
    public static function trueClues(): array
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
            new Clue(ClueKind::NorthBy, person: 2, other: 1, number: 1),
            new Clue(ClueKind::NorthBy, person: 1, other: 4, number: 2),
            new Clue(ClueKind::WestBy, person: 1, other: 2, number: 3),
            new Clue(ClueKind::WestBy, person: 3, other: 4, number: 1),
            new Clue(ClueKind::FurnitureNorth, person: 4, furniture: Furniture::Bath, number: 1),
            new Clue(ClueKind::FurnitureNorth, person: 3, furniture: Furniture::Tv, number: 1),
            new Clue(ClueKind::FurnitureSouth, person: 0, furniture: Furniture::Armchair, number: 1),
            new Clue(ClueKind::FurnitureEast, person: 4, furniture: Furniture::Toilet, number: 1),
            new Clue(ClueKind::FurnitureEast, person: 1, furniture: Furniture::Tv, number: 1),
            new Clue(ClueKind::AloneWithMurderer, person: 0),
            new Clue(ClueKind::Alone, person: 2),
            new Clue(ClueKind::AloneWith, person: 3, gender: Gender::Female),
            new Clue(ClueKind::AloneWith, person: 4, gender: Gender::Male),
            new Clue(ClueKind::GridCorner, person: 0),
            new Clue(ClueKind::RoomCorner, person: 1),
            new Clue(ClueKind::RoomCorner, person: 3),
            new Clue(ClueKind::OnlyOn, person: 2, furniture: Furniture::Sofa),
            new Clue(ClueKind::InRoom, person: 3, room: 4, then: new Clue(ClueKind::RoomCorner, person: 3)),
            new Clue(ClueKind::InRow, person: 1, number: 2, then: new Clue(ClueKind::EmptyRoom, room: 1)),
            new Clue(ClueKind::Alone, person: 2, then: new Clue(ClueKind::RoomHolds, room: 4, number: 2)),
            new Clue(ClueKind::AloneWith, person: 3, gender: Gender::Female, then: new Clue(ClueKind::BesideFurniture, furniture: Furniture::Toilet, companion: true)),
            new Clue(ClueKind::AloneWith, person: 4, gender: Gender::Male, then: new Clue(ClueKind::RoomCorner, companion: true)),
            new Clue(ClueKind::RoomHas, person: 1, furniture: Furniture::Stove),
            new Clue(ClueKind::NotBeside, person: 4, furniture: Furniture::Sofa),
            new Clue(ClueKind::NotBeside, person: 3, furniture: Furniture::Toilet),
            new Clue(ClueKind::SomeoneBeside, person: 3, furniture: Furniture::Toilet),
            new Clue(ClueKind::SomeoneBeside, person: 4, furniture: Furniture::Bath),
            new Clue(ClueKind::SameDiagonal, person: 3, other: 4),
            new Clue(ClueKind::SameDiagonal, person: 2, other: 3),
            new Clue(ClueKind::InRoom, person: 3, room: 4, then: new Clue(ClueKind::NotBeside, person: 3, furniture: Furniture::Toilet)),
            new Clue(ClueKind::AloneWith, person: 4, gender: Gender::Male, then: new Clue(ClueKind::NotBeside, furniture: Furniture::Toilet, companion: true)),
            new Clue(ClueKind::NorthOfFurniture, person: 0, furniture: Furniture::Toilet),
            new Clue(ClueKind::SouthOfFurniture, person: 4, furniture: Furniture::Stove),
            new Clue(ClueKind::EastOfFurniture, person: 2, furniture: Furniture::Bed),
            new Clue(ClueKind::WestOfFurniture, person: 0, furniture: Furniture::Sofa),
            new Clue(ClueKind::InRow, person: 1, number: 2, then: new Clue(ClueKind::RoomAtLeast, room: 4, number: 2)),
            new Clue(ClueKind::RoomAtLeast, room: 0, number: 2),
            new Clue(ClueKind::SeatCount, furniture: Furniture::Sofa, number: 1, bound: Bound::Exactly),
            new Clue(ClueKind::SeatCount, furniture: Furniture::Bed, number: 0, bound: Bound::AtMost),
            new Clue(ClueKind::SeatCount, furniture: Furniture::Sofa, number: 1, bound: Bound::AtLeast),
            new Clue(ClueKind::RoomCap, number: 2),
            new Clue(ClueKind::GenderSeated, furniture: Furniture::Sofa, number: 1, gender: Gender::Female),
            new Clue(ClueKind::GenderSeated, furniture: Furniture::Armchair, number: 0, gender: Gender::Male),
            new Clue(ClueKind::Pairing, gender: Gender::Male),
            new Clue(ClueKind::AloneWith, person: 3, gender: Gender::Female, then: new Clue(ClueKind::SouthOfFurniture, furniture: Furniture::Stove, companion: true)),
        ];
    }

    public static function randomClue(Randomizer $randomizer): Clue
    {
        $clue = self::randomFact($randomizer);
        $then = self::randomFact($randomizer);
        if ($clue->kind->isGiven() || $then->kind->isGiven() || 0 !== $randomizer->getInt(0, 2)) {
            return $clue;
        }
        if (ClueKind::AloneWith === $clue->kind && $then->kind->describesASquare()) {
            $then = new Clue($then->kind, room: $then->room, furniture: $then->furniture, number: $then->number, companion: true);
        } elseif (null !== $then->person) {
            $then = new Clue($then->kind, $clue->person ?? $then->person, $then->other, $then->room, $then->furniture, $then->number, $then->gender, $then->bound);
        }

        return new Clue($clue->kind, $clue->person, $clue->other, $clue->room, $clue->furniture, $clue->number, $clue->gender, $clue->bound, then: $then);
    }

    private static function randomFact(Randomizer $randomizer): Clue
    {
        $person = $randomizer->getInt(0, 4);
        $other = ($person + $randomizer->getInt(1, 4)) % 5;
        $room = $randomizer->getInt(0, 4);
        $furniture = Furniture::cases()[$randomizer->getInt(0, \count(Furniture::cases()) - 1)];
        $distance = $randomizer->getInt(1, 3);

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
            ClueKind::NorthBy => new Clue(ClueKind::NorthBy, person: $person, other: $other, number: $distance),
            ClueKind::WestBy => new Clue(ClueKind::WestBy, person: $person, other: $other, number: $distance),
            ClueKind::FurnitureNorth => new Clue(ClueKind::FurnitureNorth, person: $person, furniture: $furniture, number: $distance),
            ClueKind::FurnitureSouth => new Clue(ClueKind::FurnitureSouth, person: $person, furniture: $furniture, number: $distance),
            ClueKind::FurnitureEast => new Clue(ClueKind::FurnitureEast, person: $person, furniture: $furniture, number: $distance),
            ClueKind::FurnitureWest => new Clue(ClueKind::FurnitureWest, person: $person, furniture: $furniture, number: $distance),
            ClueKind::AloneWithMurderer => new Clue(ClueKind::AloneWithMurderer, person: 0),
            ClueKind::Alone => new Clue(ClueKind::Alone, person: $person),
            ClueKind::AloneWith => new Clue(ClueKind::AloneWith, person: $person, gender: Gender::cases()[$randomizer->getInt(0, 1)]),
            ClueKind::GridCorner => new Clue(ClueKind::GridCorner, person: $person),
            ClueKind::RoomCorner => new Clue(ClueKind::RoomCorner, person: $person),
            ClueKind::OnlyOn => new Clue(ClueKind::OnlyOn, person: $person, furniture: [Furniture::Armchair, Furniture::Sofa, Furniture::Bed][$randomizer->getInt(0, 2)]),
            ClueKind::RoomHas => new Clue(ClueKind::RoomHas, person: $person, furniture: $furniture),
            ClueKind::NotBeside => new Clue(ClueKind::NotBeside, person: $person, furniture: $furniture),
            ClueKind::SomeoneOn => new Clue(ClueKind::SomeoneOn, person: $person, gender: Gender::cases()[$randomizer->getInt(0, 1)], furniture: [Furniture::Armchair, Furniture::Sofa, Furniture::Bed][$randomizer->getInt(0, 2)]),
            ClueKind::SomeoneBeside => new Clue(ClueKind::SomeoneBeside, person: $person, furniture: $furniture),
            ClueKind::SameDiagonal => new Clue(ClueKind::SameDiagonal, person: $person, other: $other),
            ClueKind::RoomAtLeast => new Clue(ClueKind::RoomAtLeast, room: $room, number: $randomizer->getInt(2, 3)),
            ClueKind::SeatCount => new Clue(ClueKind::SeatCount, furniture: [Furniture::Armchair, Furniture::Sofa, Furniture::Bed][$randomizer->getInt(0, 2)], number: $randomizer->getInt(0, 2), bound: Bound::cases()[$randomizer->getInt(0, 2)]),
            ClueKind::EveryRoomOccupied => new Clue(ClueKind::EveryRoomOccupied),
            ClueKind::RoomCap => new Clue(ClueKind::RoomCap, number: $randomizer->getInt(1, 3)),
            ClueKind::GenderSeated => new Clue(ClueKind::GenderSeated, furniture: [Furniture::Armchair, Furniture::Sofa, Furniture::Bed][$randomizer->getInt(0, 2)], number: $randomizer->getInt(0, 1), gender: Gender::cases()[$randomizer->getInt(0, 1)]),
            ClueKind::Pairing => new Clue(ClueKind::Pairing, gender: Gender::cases()[$randomizer->getInt(0, 1)]),
            ClueKind::NorthOfFurniture => new Clue(ClueKind::NorthOfFurniture, person: $person, furniture: $furniture),
            ClueKind::SouthOfFurniture => new Clue(ClueKind::SouthOfFurniture, person: $person, furniture: $furniture),
            ClueKind::EastOfFurniture => new Clue(ClueKind::EastOfFurniture, person: $person, furniture: $furniture),
            ClueKind::WestOfFurniture => new Clue(ClueKind::WestOfFurniture, person: $person, furniture: $furniture),
        };
    }

    /**
     * @return list<list<int>>
     */
    public static function placements(Floorplan $plan, int $victim): array
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
