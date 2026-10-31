<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Mystery;

use App\Domain\Identity\User;
use App\Domain\Mystery\Blueprint;
use App\Domain\Mystery\Clue;
use App\Domain\Mystery\ClueKind;
use App\Domain\Mystery\Exception\InvalidNotes;
use App\Domain\Mystery\Exception\WrongAccusation;
use App\Domain\Mystery\Floorplan;
use App\Domain\Mystery\Gender;
use App\Domain\Mystery\Investigation;
use App\Domain\Mystery\RoomKind;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class InvestigationTest extends TestCase
{
    public function testACaseOpensWithOnlyTheGivenClueRevealedAndEmptyNotes(): void
    {
        $blueprint = self::blueprint();
        $now = new \DateTimeImmutable('2026-10-06 09:00 UTC');

        $investigation = Investigation::open(self::owner(), $blueprint, $now);

        $size = $blueprint->floorplan->size;
        self::assertEquals($blueprint->floorplan, $investigation->floorplan());
        self::assertSame([$blueprint->people, $blueprint->genders, $blueprint->victim, $blueprint->solution], [$investigation->people(), $investigation->genders(), $investigation->victim(), $investigation->solution()]);
        self::assertEquals($blueprint->rules, $investigation->rules());
        self::assertEquals([new Clue(ClueKind::AloneWithMurderer, person: $blueprint->victim)], $investigation->revealedClues());
        self::assertSame(\count($blueprint->clues) - 1, $investigation->hiddenClueCount());
        self::assertSame(['placements' => array_fill(0, $size, null), 'crosses' => []], $investigation->notes());
        self::assertFalse($investigation->isSolved());
        self::assertEquals($now, $investigation->openedAt());
        self::assertNull($investigation->solvedAt());
    }

    public function testACaseWithoutAGivenClueOpensWithEveryClueHidden(): void
    {
        $blueprint = self::blueprint(given: false);

        $investigation = Investigation::open(self::owner(), $blueprint, new \DateTimeImmutable());

        self::assertSame([], $investigation->revealedClues());
        self::assertSame(\count($blueprint->clues), $investigation->hiddenClueCount());
        self::assertTrue($investigation->revealFor(new Ulid()));
        self::assertEquals(\array_slice($blueprint->clues, 0, 1), $investigation->revealedClues());
    }

    public function testAGiftOpensWithEveryClueRevealedAndTasksRevealNothingMore(): void
    {
        $blueprint = self::blueprint();

        $investigation = Investigation::open(self::owner(), $blueprint, new \DateTimeImmutable(), gift: true);

        self::assertEquals($blueprint->clues, $investigation->revealedClues());
        self::assertSame(0, $investigation->hiddenClueCount());
        self::assertFalse($investigation->revealFor(new Ulid()));
        self::assertEquals($blueprint->clues, $investigation->revealedClues());
    }

    public function testTheMurdererIsTheOnlySuspectInTheVictimsRoom(): void
    {
        $blueprint = self::blueprint();

        $investigation = Investigation::open(self::owner(), $blueprint, new \DateTimeImmutable());

        self::assertSame($blueprint->floorplan->murdererIn($blueprint->solution, $blueprint->victim), $investigation->murderer());
        self::assertNotSame($blueprint->victim, $investigation->murderer());
    }

    public function testEachTaskRevealsTheNextClueOnlyOnce(): void
    {
        $blueprint = self::blueprint();
        $investigation = Investigation::open(self::owner(), $blueprint, new \DateTimeImmutable());
        $first = new Ulid();

        self::assertTrue($investigation->revealFor($first));
        self::assertEquals(\array_slice($blueprint->clues, 0, 2), $investigation->revealedClues());

        self::assertFalse($investigation->revealFor($first));
        self::assertEquals(\array_slice($blueprint->clues, 0, 2), $investigation->revealedClues());

        self::assertTrue($investigation->revealFor(new Ulid()));
        self::assertEquals(\array_slice($blueprint->clues, 0, 3), $investigation->revealedClues());
        self::assertSame(\count($blueprint->clues) - 3, $investigation->hiddenClueCount());
    }

    public function testNothingIsRevealedPastTheLastClue(): void
    {
        $blueprint = self::blueprint();
        $investigation = Investigation::open(self::owner(), $blueprint, new \DateTimeImmutable());

        foreach (\array_slice($blueprint->clues, 1) as $ignored) {
            self::assertTrue($investigation->revealFor(new Ulid()));
        }

        self::assertFalse($investigation->revealFor(new Ulid()));
        self::assertEquals($blueprint->clues, $investigation->revealedClues());
        self::assertSame(0, $investigation->hiddenClueCount());
    }

    public function testNothingIsRevealedOnceTheCaseIsSolved(): void
    {
        $investigation = Investigation::open(self::owner(), self::blueprint(), new \DateTimeImmutable());
        $investigation->accuse($investigation->solution(), $investigation->murderer(), new \DateTimeImmutable());

        self::assertFalse($investigation->revealFor(new Ulid()));
        self::assertCount(1, $investigation->revealedClues());
    }

    public function testNotesKeepWhereEveryoneStandsAndWhichCellsAreCrossed(): void
    {
        $investigation = Investigation::open(self::owner(), self::blueprint(), new \DateTimeImmutable());
        $size = $investigation->floorplan()->size;
        $placements = [0, null, $size * $size - 1, ...array_fill(0, $size - 3, null)];

        $investigation->takeNotes($placements, [1, 5, $size * $size - 2]);

        self::assertSame(['placements' => $placements, 'crosses' => [1, 5, $size * $size - 2]], $investigation->notes());

        $investigation->takeNotes(array_fill(0, $size, null), []);

        self::assertSame(['placements' => array_fill(0, $size, null), 'crosses' => []], $investigation->notes());
    }

    /**
     * @param \Closure(int): array{list<?int>, list<int>} $notes
     */
    #[DataProvider('notesThatDoNotFit')]
    public function testRefusesNotesThatDoNotFitTheBoard(\Closure $notes): void
    {
        $investigation = Investigation::open(self::owner(), self::blueprint(), new \DateTimeImmutable());
        $size = $investigation->floorplan()->size;
        [$placements, $crosses] = $notes($size);

        try {
            $investigation->takeNotes($placements, $crosses);
            self::fail('The notes should have been refused.');
        } catch (InvalidNotes $refused) {
            self::assertSame('mystery.invalid_notes', $refused->getMessage());
        }

        self::assertSame(['placements' => array_fill(0, $size, null), 'crosses' => []], $investigation->notes());
    }

    /** @return iterable<string, array{\Closure(int): array{list<?int>, list<int>}}> */
    public static function notesThatDoNotFit(): iterable
    {
        yield 'someone missing' => [static fn (int $size): array => [array_fill(0, $size - 1, null), []]];
        yield 'someone too many' => [static fn (int $size): array => [array_fill(0, $size + 1, null), []]];
        yield 'a cell before the board' => [static fn (int $size): array => [[-1, ...array_fill(0, $size - 1, null)], []]];
        yield 'a cell after the board' => [static fn (int $size): array => [[$size * $size, ...array_fill(0, $size - 1, null)], []]];
        yield 'two people on one cell' => [static fn (int $size): array => [[4, 4, ...array_fill(0, $size - 2, null)], []]];
        yield 'a cross before the board' => [static fn (int $size): array => [array_fill(0, $size, null), [-1]]];
        yield 'a cross after the board' => [static fn (int $size): array => [array_fill(0, $size, null), [$size * $size]]];
        yield 'a cell crossed twice' => [static fn (int $size): array => [array_fill(0, $size, null), [3, 7, 3]]];
    }

    public function testARightAccusationSolvesTheCaseAndKeepsTheBoardAsNotes(): void
    {
        $blueprint = self::blueprint();
        $investigation = Investigation::open(self::owner(), $blueprint, new \DateTimeImmutable('2026-10-06 09:00 UTC'));
        $now = new \DateTimeImmutable('2026-10-07 18:30 UTC');

        $investigation->accuse($blueprint->solution, $investigation->murderer(), $now);

        self::assertTrue($investigation->isSolved());
        self::assertEquals($now, $investigation->solvedAt());
        self::assertSame($blueprint->solution, $investigation->notes()['placements']);
    }

    #[DataProvider('wrongAccusations')]
    public function testAWrongAccusationChangesNothingAndNeverSaysWhatIsWrong(string $mistake): void
    {
        $blueprint = self::blueprint();
        $investigation = Investigation::open(self::owner(), $blueprint, new \DateTimeImmutable());
        $solution = $blueprint->solution;
        $murderer = $investigation->murderer();
        $suspect = array_values(array_diff(array_keys($solution), [$murderer, $blueprint->victim]))[0];
        [$placements, $accused] = [
            'two people swapped' => [[$solution[1], $solution[0], ...\array_slice($solution, 2)], $murderer],
            'someone missing' => [\array_slice($solution, 1), $murderer],
            'another suspect' => [$solution, $suspect],
            'the victim' => [$solution, $blueprint->victim],
        ][$mistake];

        try {
            $investigation->accuse($placements, $accused, new \DateTimeImmutable());
            self::fail('The accusation should have been refused.');
        } catch (WrongAccusation $wrong) {
            self::assertSame(['mystery.wrong_accusation', []], [$wrong->getMessage(), $wrong->parameters()]);
        }

        self::assertFalse($investigation->isSolved());
        self::assertNull($investigation->solvedAt());
        self::assertSame(array_fill(0, $blueprint->floorplan->size, null), $investigation->notes()['placements']);
    }

    /** @return iterable<string, array{string}> */
    public static function wrongAccusations(): iterable
    {
        foreach (['two people swapped', 'someone missing', 'another suspect', 'the victim'] as $mistake) {
            yield $mistake => [$mistake];
        }
    }

    private static function blueprint(bool $given = true): Blueprint
    {
        $clues = [
            new Clue(ClueKind::InRow, person: 1, number: 1),
            new Clue(ClueKind::InColumn, person: 2, number: 2),
            new Clue(ClueKind::InRoom, person: 2, room: 1),
        ];

        return new Blueprint(
            new Floorplan(3, [0, 0, 1, 0, 0, 1, 1, 1, 1], [RoomKind::Kitchen, RoomKind::LivingRoom], array_fill(0, 9, null)),
            ['Vesper', 'Basil', 'Clover'],
            [Gender::Female, Gender::Male, Gender::Female],
            0,
            [0, 4, 8],
            $given ? [new Clue(ClueKind::AloneWithMurderer, person: 0), ...$clues] : $clues,
            [new Clue(ClueKind::EveryRoomOccupied), new Clue(ClueKind::RoomCap, number: 2)],
        );
    }

    private static function owner(): User
    {
        return User::join('account', 'louis@example.com', 'Louis', 'Europe/Paris', new \DateTimeImmutable('2026-10-06 08:00 UTC'));
    }
}
