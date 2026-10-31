<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Mystery;

use App\Domain\Mystery\Blueprint;
use App\Domain\Mystery\CaseGenerator;
use App\Domain\Mystery\Clue;
use App\Domain\Mystery\ClueKind;
use App\Domain\Mystery\Floorplan;
use App\Domain\Mystery\RoomKind;
use App\Domain\Mystery\Solver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

final class CaseGeneratorTest extends TestCase
{
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
        self::assertLessThanOrEqual((int) ceil(0.8 * $size), \count($plan->kinds));
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
            self::assertTrue($clue->holdsFor($case->floorplan, $case->solution), (string) json_encode($clue->toArray()));
        }
        self::assertSame(1, new Solver()->count($case->floorplan, $case->victim, $case->clues, 1_000_000));
    }

    #[DataProvider('seeds')]
    public function testNeverPairsTheVictimInASameRoomClue(int $seed): void
    {
        $case = self::generate($seed);

        self::assertFalse(array_any(
            $case->clues,
            static fn (Clue $clue): bool => ClueKind::SameRoom === $clue->kind && \in_array($case->victim, [$clue->person, $clue->other], true),
        ));
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

    public function testCoversTheSmallestAndLargestGrids(): void
    {
        $sizes = array_map(static fn (array $seed): int => self::generate($seed[0])->floorplan->size, iterator_to_array(self::seeds(), false));

        self::assertContains(6, $sizes);
        self::assertContains(14, $sizes);
    }

    public function testTheSameSeedDrawsTheSameCase(): void
    {
        self::assertEquals(self::generate(3), self::generate(3));
        self::assertNotEquals(self::generate(3), self::generate(4));
    }

    #[DataProvider('timedSeeds')]
    public function testDrawsACaseWithinTwoSeconds(int $seed): void
    {
        $start = hrtime(true);
        self::generate($seed);

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
        yield from self::seeds();
        yield 'slow 14x14 seed 38' => [38];
    }

    private static function generate(int $seed): Blueprint
    {
        return new CaseGenerator(new Randomizer(new Mt19937($seed)))->generate();
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
