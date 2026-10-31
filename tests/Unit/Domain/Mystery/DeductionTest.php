<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Mystery;

use App\Domain\Mystery\Clue;
use App\Domain\Mystery\ClueKind;
use App\Domain\Mystery\Deduction;
use App\Domain\Mystery\Furniture;
use App\Domain\Mystery\Gender;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

final class DeductionTest extends TestCase
{
    public function testPlacesEveryoneInTheOrderTheCluesPinThem(): void
    {
        $deduction = self::deduce([...self::pins(), new Clue(ClueKind::NorthBy, person: 1, other: 4, number: 2)]);
        $placed = $deduction->placed();

        self::assertTrue($deduction->isSolved());
        self::assertSame([2 => 9, 4 => 23, 3 => 17], \array_slice($placed, 0, 3, true));
        ksort($placed);
        self::assertSame(FloorplanTest::SOLUTION, $placed);
    }

    public function testStopsWhereOnlyAGuessCouldGoOn(): void
    {
        $deduction = self::deduce(self::pins());

        self::assertFalse($deduction->isSolved());
        self::assertSame([2 => 9, 4 => 23, 3 => 17], $deduction->placed());
    }

    public function testLearningMoreLeavesAnEarlierCopyUntouched(): void
    {
        $deduction = self::deduce(self::pins());
        $further = clone $deduction;
        $further->learn(new Clue(ClueKind::InRow, person: 1, number: 2));

        self::assertTrue($further->isSolved());
        self::assertFalse($deduction->isSolved());
    }

    public function testPinsSomeoneWithBothFactsOfACompoundClue(): void
    {
        self::assertSame([], self::deduce([new Clue(ClueKind::InRow, person: 3, number: 3)])->placed());
        self::assertSame([], self::deduce([new Clue(ClueKind::InRoom, person: 3, room: 4)])->placed());
        self::assertSame(17, self::deduce([new Clue(ClueKind::InRow, person: 3, number: 3, then: new Clue(ClueKind::InRoom, person: 3, room: 4))])->placed()[3] ?? null);
    }

    public function testPinsTheCompanionOfSomeoneAloneWithThem(): void
    {
        $inTheBathroom = [new Clue(ClueKind::InRoom, person: 3, room: 4), new Clue(ClueKind::InRoom, person: 4, room: 4)];
        $aloneWithAWoman = new Clue(ClueKind::AloneWith, person: 3, gender: Gender::Female);

        self::assertSame([], self::deduce([...$inTheBathroom, $aloneWithAWoman])->placed());
        self::assertSame([4 => 23, 3 => 17], \array_slice(self::deduce([
            ...$inTheBathroom,
            new Clue(ClueKind::AloneWith, person: 3, gender: Gender::Female, then: new Clue(ClueKind::BesideFurniture, furniture: Furniture::Toilet, companion: true)),
        ])->placed(), 0, 2, true));
    }

    public function testKeepsEveryoneElseOffTheOnlySeatOfItsKind(): void
    {
        $placed = self::deduce([new Clue(ClueKind::OnlyOn, person: 2, furniture: Furniture::Sofa)])->placed();

        self::assertSame(9, $placed[2] ?? null);
    }

    public function testOnlyPlacesPeopleWhereEverySolutionPutsThem(): void
    {
        $manor = FloorplanTest::manor();
        $placements = SolverTest::placements($manor, 0);
        $randomizer = new Randomizer(new Mt19937(11));
        $trueClues = SolverTest::trueClues();
        $placedSomeone = false;
        for ($round = 0; $round < 600; ++$round) {
            $clues = 0 === $round % 2
                ? array_map(static fn (int $key): Clue => $trueClues[$key], $randomizer->pickArrayKeys($trueClues, $randomizer->getInt(1, 8)))
                : array_map(static fn (): Clue => SolverTest::randomClue($randomizer), range(0, $randomizer->getInt(0, 5)));
            $solutions = array_filter($placements, static fn (array $cells): bool => array_all($clues, static fn (Clue $clue): bool => $clue->holdsFor($manor, $cells, FloorplanTest::GENDERS)));
            if ([] === $solutions) {
                continue;
            }
            $placed = self::deduce($clues)->placed();
            ksort($placed);
            $placedSomeone = $placedSomeone || [] !== $placed;

            foreach ($solutions as $cells) {
                self::assertSame(array_intersect_key($cells, $placed), array_intersect_key($placed, $cells), (string) json_encode(array_map(static fn (Clue $clue): array => $clue->toArray(), $clues)));
            }
        }
        self::assertTrue($placedSomeone);
    }

    /**
     * @return list<Clue>
     */
    private static function pins(): array
    {
        return [
            new Clue(ClueKind::OnFurniture, person: 2, furniture: Furniture::Sofa),
            new Clue(ClueKind::FurnitureEast, person: 4, furniture: Furniture::Toilet, number: 1),
            new Clue(ClueKind::FurnitureNorth, person: 3, furniture: Furniture::Tv, number: 1),
        ];
    }

    /**
     * @param list<Clue> $clues
     */
    private static function deduce(array $clues): Deduction
    {
        $deduction = new Deduction(FloorplanTest::manor(), 0, FloorplanTest::GENDERS);
        foreach ($clues as $clue) {
            $deduction->learn($clue);
        }

        return $deduction;
    }
}
