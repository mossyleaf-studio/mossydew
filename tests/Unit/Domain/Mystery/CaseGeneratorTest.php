<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Mystery;

use App\Domain\Mystery\Blueprint;
use App\Domain\Mystery\Bound;
use App\Domain\Mystery\CaseGenerator;
use App\Domain\Mystery\Clue;
use App\Domain\Mystery\ClueKind;
use App\Domain\Mystery\Deduction;
use App\Domain\Mystery\Floorplan;
use App\Domain\Mystery\Gender;
use App\Domain\Mystery\RoomKind;
use App\Domain\Mystery\Solver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

final class CaseGeneratorTest extends TestCase
{
    /** @var array<int, Blueprint> */
    private static array $cases = [];

    #[DataProvider('seeds')]
    public function testDrawsAFloorplanOfContiguousRoomsOfDistinctKinds(int $seed): void
    {
        $plan = self::generate($seed)->floorplan;
        $size = $plan->size;

        self::assertGreaterThanOrEqual(6, $size);
        self::assertLessThanOrEqual(14, $size);
        self::assertCount($size * $size, $plan->rooms);
        self::assertCount($size * $size, $plan->furniture);
        self::assertGreaterThanOrEqual((int) ceil($size / 2), \count($plan->kinds));
        self::assertLessThanOrEqual($size, \count($plan->kinds));
        self::assertCount(\count($plan->kinds), array_unique(array_map(static fn (RoomKind $kind): string => $kind->value, $plan->kinds)));
        foreach (array_keys($plan->kinds) as $room) {
            $cells = array_keys($plan->rooms, $room, true);
            self::assertGreaterThanOrEqual(2, \count($cells));
            self::assertCount(\count($cells), self::walk($plan, $cells[0]));
        }
    }

    #[DataProvider('seeds')]
    public function testHidesEveryoneOnePerRowAndColumnOffBlockingFurniture(int $seed): void
    {
        $case = self::generate($seed);
        $size = $case->floorplan->size;

        self::assertCount($size, $case->people);
        self::assertCount($size, array_unique($case->people));
        self::assertEqualsCanonicalizing(range(0, $size - 1), array_map(static fn (int $cell): int => intdiv($cell, $size), $case->solution));
        self::assertEqualsCanonicalizing(range(0, $size - 1), array_map(static fn (int $cell): int => $cell % $size, $case->solution));
        self::assertFalse(array_any($case->solution, $case->floorplan->blocks(...)));
        self::assertNotNull($case->floorplan->murdererIn($case->solution, $case->victim));
    }

    #[DataProvider('seeds')]
    public function testGivesTrueCluesThatProveASingleSolution(int $seed): void
    {
        $case = self::generate($seed);

        self::assertNotSame([], $case->clues);
        foreach ($case->clues as $clue) {
            self::assertTrue($clue->holdsFor($case->floorplan, $case->solution, $case->genders), (string) json_encode($clue->toArray()));
        }
        self::assertSame(1, new Solver()->count($case->floorplan, $case->victim, $case->genders, [...$case->rules, ...$case->clues], 1_000_000));
    }

    #[DataProvider('seeds')]
    public function testGivesOneCluePerPersonStartingWithTheVictimAloneWithTheMurderer(int $seed): void
    {
        $case = self::generate($seed);

        self::assertCount($case->floorplan->size, $case->clues);
        self::assertEquals(new Clue(ClueKind::AloneWithMurderer, person: $case->victim), $case->clues[0]);
        self::assertFalse(array_any(\array_slice($case->clues, 1), static fn (Clue $clue): bool => $clue->kind->isGiven()));
    }

    #[DataProvider('timedSeeds')]
    public function testStatesUpToTwoTrueBoardRulesThatSpareTheCrimeScene(int $seed): void
    {
        $case = self::generate($seed);
        $plan = $case->floorplan;
        $crimeScene = $plan->roomOf($case->solution[$case->victim]);
        $heads = array_count_values(array_map($plan->roomOf(...), $case->solution));

        self::assertLessThanOrEqual(2, \count($case->rules));
        foreach ($case->rules as $rule) {
            $shape = (string) json_encode($rule->toArray());
            self::assertTrue($rule->kind->isRule(), $shape);
            self::assertNull($rule->person, $shape);
            self::assertNull($rule->then, $shape);
            self::assertTrue($rule->holdsFor($plan, $case->solution, $case->genders), $shape);
            self::assertFalse(ClueKind::EmptyRoom === $rule->kind && $crimeScene === $rule->room, $shape);
            self::assertFalse(ClueKind::Pairing === $rule->kind && $case->genders[$case->victim] === $rule->gender, $shape);
            if (ClueKind::RoomCap === $rule->kind) {
                self::assertGreaterThanOrEqual(2, $rule->number());
                self::assertContains($rule->number(), array_diff_key($heads, [$crimeScene => true]), $shape);
            }
        }
        foreach ($case->clues as $clue) {
            foreach (self::facts($clue) as $fact) {
                self::assertTrue(ClueKind::EmptyRoom === $fact->kind || !$fact->kind->isRule(), (string) json_encode($clue->toArray()));
            }
        }
    }

    #[DataProvider('timedSeeds')]
    public function testNeverStatesARuleTheOtherRuleImplies(int $seed): void
    {
        $rules = self::generate($seed)->rules;
        if (2 !== \count($rules)) {
            $this->expectNotToPerformAssertions();

            return;
        }
        [$count, $gendered] = ClueKind::SeatCount === $rules[0]->kind ? $rules : array_reverse($rules);
        $shape = (string) json_encode([$rules[0]->toArray(), $rules[1]->toArray()]);

        self::assertFalse(
            ClueKind::SeatCount === $count->kind && ClueKind::GenderSeated === $gendered->kind && $count->furniture === $gendered->furniture
            && ((0 === $count->number() && Bound::AtLeast !== $count->bound) || (Bound::AtLeast === $count->bound && $count->number() <= $gendered->number())),
            $shape,
        );
    }

    public function testStatesEveryKindOfRuleInMostCases(): void
    {
        $kinds = [];
        $withRules = 0;
        foreach (self::timedSeeds() as [$seed]) {
            $rules = self::generate($seed)->rules;
            $withRules += (int) ([] !== $rules);
            foreach ($rules as $rule) {
                $kinds[$rule->kind->value] = true;
            }
        }

        self::assertGreaterThanOrEqual(24, $withRules);
        self::assertEqualsCanonicalizing(['seat_count', 'every_room_occupied', 'room_cap', 'empty_room', 'gender_seated', 'pairing'], array_keys($kinds));
    }

    #[DataProvider('seeds')]
    public function testGivesEverySuspectExactlyOneClueAnchoredOnThem(int $seed): void
    {
        $case = self::generate($seed);
        $anchors = array_map(static fn (Clue $clue): ?int => $clue->person, \array_slice($case->clues, 1));
        sort($anchors);

        self::assertSame(array_values(array_diff(array_keys($case->people), [$case->victim])), $anchors);
    }

    #[DataProvider('seeds')]
    public function testNoDrawnClueTellsAboutTheVictim(int $seed): void
    {
        $case = self::generate($seed);
        $plan = $case->floorplan;
        $room = $plan->roomOf($case->solution[$case->victim]);

        foreach (\array_slice($case->clues, 1) as $clue) {
            foreach (self::facts($clue) as $fact) {
                self::assertNotContains($case->victim, [$fact->person, $fact->other], (string) json_encode($clue->toArray()));
                self::assertFalse(null === $fact->person && !$fact->companion && $room === $fact->room, (string) json_encode($clue->toArray()));
            }
            $describesTheirRoom = array_any(self::facts($clue), static fn (Clue $fact): bool => \in_array($fact->kind, [ClueKind::AloneWith, ClueKind::RoomHas, ClueKind::SomeoneOn, ClueKind::SomeoneBeside], true));
            self::assertFalse($describesTheirRoom && $room === $plan->roomOf($case->solution[$clue->person()]), (string) json_encode($clue->toArray()));
        }
    }

    #[DataProvider('seeds')]
    public function testNeverJoinsTwoFactsWhenOneImpliesTheOther(int $seed): void
    {
        $case = self::generate($seed);
        $plan = $case->floorplan;
        $repeated = [];
        foreach ($case->clues as $clue) {
            $then = $clue->then;
            $first = $clue->square() ?? $clue;
            if (
                null !== $then && !$then->companion && $then->kind->describesASquare() && $first->kind->describesASquare()
                && (
                    array_all(array_keys($plan->rooms), static fn (int $cell): bool => !$first->holdsAt($plan, $cell) || $then->holdsAt($plan, $cell))
                    || array_all(array_keys($plan->rooms), static fn (int $cell): bool => !$then->holdsAt($plan, $cell) || $first->holdsAt($plan, $cell))
                )
            ) {
                $repeated[] = $clue->toArray();
            }
        }

        self::assertSame([], $repeated);
    }

    #[DataProvider('timedSeeds')]
    public function testNeverJoinsTwoFactsThatPinTheSquareOnTheirOwn(int $seed): void
    {
        $case = self::generate($seed);
        $plan = $case->floorplan;
        $pinning = [];
        foreach ($case->clues as $clue) {
            $then = $clue->then;
            $first = $clue->square() ?? $clue;
            if (null === $then || $then->companion || !$then->kind->describesASquare() || !$first->kind->describesASquare()) {
                continue;
            }
            $squares = array_filter(array_keys($plan->rooms), static fn (int $cell): bool => !$plan->blocks($cell) && $first->holdsAt($plan, $cell) && $then->holdsAt($plan, $cell));
            if (\count($squares) < 2) {
                $pinning[] = $clue->toArray();
            }
        }

        self::assertSame([], $pinning);
    }

    #[DataProvider('seeds')]
    public function testOnlyCountsAtLeastTwoPeopleInAnotherRoomThanTheAnchors(int $seed): void
    {
        $case = self::generate($seed);
        $plan = $case->floorplan;
        $pointless = [];
        foreach (\array_slice($case->clues, 1) as $clue) {
            $then = $clue->then;
            if (null !== $then && ClueKind::RoomAtLeast === $then->kind && ($then->number() < 2 || $then->room() === $plan->roomOf($case->solution[$clue->person()]))) {
                $pointless[] = $clue->toArray();
            }
        }

        self::assertSame([], $pointless);
    }

    #[DataProvider('seeds')]
    public function testOnlySaysSomeoneIsNotBesideAPieceTheirRoomHolds(int $seed): void
    {
        $case = self::generate($seed);
        $plan = $case->floorplan;
        $pointless = [];
        foreach (\array_slice($case->clues, 1) as $clue) {
            foreach (self::facts($clue) as $fact) {
                if (ClueKind::NotBeside === $fact->kind && !$fact->companion && !$plan->furnishes($plan->roomOf($case->solution[$fact->person()]), $fact->furniture())) {
                    $pointless[] = $clue->toArray();
                }
            }
        }

        self::assertSame([], $pointless);
    }

    #[DataProvider('seeds')]
    public function testJoinsAtMostTwoFactsInAClue(int $seed): void
    {
        $case = self::generate($seed);

        foreach ($case->clues as $clue) {
            $then = $clue->then;
            self::assertNotSame(ClueKind::EmptyRoom, $clue->kind);
            self::assertFalse($clue->companion);
            if (null === $then) {
                continue;
            }
            $shape = (string) json_encode($clue->toArray());
            self::assertNull($then->then, $shape);
            self::assertFalse($then->kind->isGiven(), $shape);
            if ($then->companion) {
                self::assertSame(ClueKind::AloneWith, $clue->kind, $shape);
                self::assertNull($then->person, $shape);
                self::assertTrue($then->kind->describesASquare(), $shape);
            } else {
                self::assertTrue($then->person === $clue->person || (null === $then->person && null !== $then->room), $shape);
            }
        }
    }

    #[DataProvider('seeds')]
    public function testGivesEveryoneAGender(int $seed): void
    {
        $case = self::generate($seed);

        self::assertCount(\count($case->people), $case->genders);
    }

    public function testMixesManyKindsOfFactsWithoutLeaningOnFurniture(): void
    {
        $kinds = [];
        $clues = $compounds = 0;
        $genders = [];
        foreach (self::timedSeeds() as [$seed]) {
            $case = self::generate($seed);
            foreach (\array_slice($case->clues, 1) as $clue) {
                ++$clues;
                $compounds += (int) (null !== $clue->then);
                foreach (self::facts($clue) as $fact) {
                    $kinds[] = $fact->kind;
                }
            }
            array_push($genders, ...$case->genders);
        }
        $furniture = array_filter($kinds, static fn (ClueKind $kind): bool => \in_array($kind, [ClueKind::FurnitureNorth, ClueKind::FurnitureSouth, ClueKind::FurnitureEast, ClueKind::FurnitureWest], true));

        self::assertLessThan(0.4, \count($furniture) / \count($kinds));
        self::assertGreaterThan(0.3, $compounds / $clues);
        foreach (ClueKind::cases() as $kind) {
            self::assertTrue($kind->isGiven() || ($kind->isRule() && ClueKind::EmptyRoom !== $kind) || \in_array($kind, $kinds, true), $kind->value);
        }
        self::assertContains(Gender::Female, $genders);
        self::assertContains(Gender::Male, $genders);
    }

    #[DataProvider('seeds')]
    public function testCanBeSolvedByDeductionAloneWithTheVictimPlacedLast(int $seed): void
    {
        $case = self::generate($seed);
        $deduction = new Deduction($case->floorplan, $case->victim, $case->genders);
        foreach ([...$case->rules, ...$case->clues] as $clue) {
            $deduction->learn($clue);
        }
        $placed = $deduction->placed();

        self::assertTrue($deduction->isSolved());
        self::assertGreaterThanOrEqual($case->floorplan->size - 2, array_search($case->victim, array_keys($placed), true));
        ksort($placed);
        self::assertSame($case->solution, $placed);
    }

    #[DataProvider('seeds')]
    public function testOnlyTheVictimsNameStartsWithAV(int $seed): void
    {
        $case = self::generate($seed);

        foreach ($case->people as $person => $name) {
            self::assertSame($case->victim === $person, str_starts_with($name, 'V'), $name);
        }
    }

    #[DataProvider('seeds')]
    public function testFurnishesAQuarterOfTheSquaresBlockingFewAndLeavesTwoFreeCellsInEveryRoom(int $seed): void
    {
        $plan = self::generate($seed)->floorplan;
        $cells = $plan->size * $plan->size;
        $blocked = \count(array_filter(array_keys($plan->furniture), $plan->blocks(...)));

        self::assertGreaterThanOrEqual(0.18 * $cells, \count(array_filter($plan->furniture)));
        self::assertLessThanOrEqual(0.32 * $cells, \count(array_filter($plan->furniture)));
        self::assertLessThanOrEqual(round(0.14 * $cells), $blocked);
        self::assertGreaterThanOrEqual(0.05 * $cells, $blocked);
        foreach (array_keys($plan->kinds) as $room) {
            $free = array_filter(array_keys($plan->rooms, $room, true), static fn (int $cell): bool => !$plan->blocks($cell));
            self::assertGreaterThanOrEqual(2, \count($free));
        }
    }

    public function testSeatsHoldSomeoneNoMoreOftenThanAnyOtherSquare(): void
    {
        $seats = $seated = $bare = $standing = 0;
        foreach (range(1, 120) as $seed) {
            $case = self::generate($seed);
            $taken = array_flip($case->solution);
            foreach ($case->floorplan->furniture as $cell => $piece) {
                if (null === $piece) {
                    ++$bare;
                    $standing += (int) isset($taken[$cell]);
                } elseif ($piece->canHoldSomeone()) {
                    ++$seats;
                    $seated += (int) isset($taken[$cell]);
                }
            }
        }

        self::assertLessThan(2 * $standing / $bare, $seated / $seats);
    }

    public function testPointsAtFurnitureFurtherAwayThanTheNextSquare(): void
    {
        $distances = [];
        foreach (self::seeds() as [$seed]) {
            foreach (self::generate($seed)->clues as $clue) {
                if (\in_array($clue->kind, [ClueKind::FurnitureNorth, ClueKind::FurnitureSouth, ClueKind::FurnitureEast, ClueKind::FurnitureWest], true)) {
                    $distances[] = $clue->number();
                }
            }
        }

        self::assertContains(1, $distances);
        self::assertNotEmpty(array_filter($distances, static fn (int $distance): bool => $distance > 1));
    }

    public function testCoversTheSmallestAndLargestGrids(): void
    {
        $sizes = array_map(static fn (array $seed): int => self::generate($seed[0])->floorplan->size, iterator_to_array(self::seeds(), false));

        self::assertContains(6, $sizes);
        self::assertContains(14, $sizes);
    }

    public function testDrawsACaseOfTheSizeAskedFor(): void
    {
        foreach ([6, 9, 14] as $size) {
            $case = new CaseGenerator(new Randomizer(new Mt19937($size)))->generate($size);

            self::assertSame($size, $case->floorplan->size);
            self::assertCount($size, $case->people);
            self::assertCount($size, $case->clues);
        }
    }

    public function testTheSameSeedDrawsTheSameCase(): void
    {
        self::assertEquals(self::draw(3), self::draw(3));
        self::assertNotEquals(self::draw(3), self::draw(4));
    }

    #[DataProvider('timedSeeds')]
    public function testDrawsACaseWithinTwoSeconds(int $seed): void
    {
        $start = hrtime(true);
        self::draw($seed);

        self::assertLessThan(2.0, (hrtime(true) - $start) / 1e9);
    }

    /** @return iterable<array{int}> */
    public static function seeds(): iterable
    {
        foreach ([1, 2, 5, 6, 9, 10, 12, 15, 22, 37] as $seed) {
            yield 'seed '.$seed => [$seed];
        }
    }

    /** @return iterable<array{int}> */
    public static function timedSeeds(): iterable
    {
        foreach (range(1, 40) as $seed) {
            yield 'seed '.$seed => [$seed];
        }
    }

    private static function generate(int $seed): Blueprint
    {
        return self::$cases[$seed] ??= self::draw($seed);
    }

    private static function draw(int $seed): Blueprint
    {
        return new CaseGenerator(new Randomizer(new Mt19937($seed)))->generate();
    }

    /**
     * @return list<Clue>
     */
    private static function facts(Clue $clue): array
    {
        return null === $clue->then ? [$clue] : [$clue, $clue->then];
    }

    /**
     * @return array<int, true>
     */
    private static function walk(Floorplan $plan, int $start): array
    {
        $seen = [$start => true];
        $queue = [$start];
        while ([] !== $queue) {
            foreach (Floorplan::neighbours($plan->size, array_pop($queue)) as $next) {
                if ($plan->rooms[$next] === $plan->rooms[$start] && !isset($seen[$next])) {
                    $seen[$next] = true;
                    $queue[] = $next;
                }
            }
        }

        return $seen;
    }
}
