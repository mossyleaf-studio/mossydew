<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Mystery;

use App\Domain\Mystery\Clue;
use App\Domain\Mystery\ClueKind;
use App\Domain\Mystery\Furniture;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ClueTest extends TestCase
{
    #[DataProvider('clues')]
    public function testTellsWhetherItHoldsForAPlacement(Clue $clue, bool $holds): void
    {
        self::assertSame($holds, $clue->holdsFor(FloorplanTest::manor(), FloorplanTest::SOLUTION));
    }

    /** @return iterable<array{Clue, bool}> */
    public static function clues(): iterable
    {
        yield 'in the room' => [new Clue(ClueKind::InRoom, person: 3, room: 4), true];
        yield 'in another room' => [new Clue(ClueKind::InRoom, person: 3, room: 2), false];
        yield 'on the furniture' => [new Clue(ClueKind::OnFurniture, person: 2, furniture: Furniture::Sofa), true];
        yield 'on other furniture' => [new Clue(ClueKind::OnFurniture, person: 2, furniture: Furniture::Bed), false];
        yield 'beside the furniture' => [new Clue(ClueKind::BesideFurniture, person: 4, furniture: Furniture::Toilet), true];
        yield 'beside the furniture sat on' => [new Clue(ClueKind::BesideFurniture, person: 2, furniture: Furniture::Sofa), false];
        yield 'in the row' => [new Clue(ClueKind::InRow, person: 1, number: 2), true];
        yield 'in another row' => [new Clue(ClueKind::InRow, person: 1, number: 1), false];
        yield 'in the column' => [new Clue(ClueKind::InColumn, person: 1, number: 1), true];
        yield 'in another column' => [new Clue(ClueKind::InColumn, person: 1, number: 2), false];
        yield 'in the same room' => [new Clue(ClueKind::SameRoom, person: 3, other: 4), true];
        yield 'in different rooms' => [new Clue(ClueKind::SameRoom, person: 2, other: 4), false];
        yield 'further north' => [new Clue(ClueKind::NorthOf, person: 2, other: 1), true];
        yield 'further south' => [new Clue(ClueKind::NorthOf, person: 1, other: 2), false];
        yield 'further west' => [new Clue(ClueKind::WestOf, person: 1, other: 2), true];
        yield 'further east' => [new Clue(ClueKind::WestOf, person: 2, other: 1), false];
        yield 'nobody in the room' => [new Clue(ClueKind::EmptyRoom, room: 1), true];
        yield 'somebody in the room' => [new Clue(ClueKind::EmptyRoom, room: 0), false];
        yield 'the head count' => [new Clue(ClueKind::RoomHolds, room: 4, number: 2), true];
        yield 'another head count' => [new Clue(ClueKind::RoomHolds, room: 4, number: 1), false];
    }

    public function testSurvivesARoundTripThroughAnArray(): void
    {
        foreach (self::clues() as [$clue]) {
            self::assertEquals($clue, Clue::fromArray($clue->toArray()));
        }
    }

    public function testArrayHoldsOnlyTheKeysOfItsKind(): void
    {
        self::assertSame(['kind' => 'in_row', 'person' => 0, 'number' => 0], new Clue(ClueKind::InRow, person: 0, number: 0)->toArray());
        self::assertSame(['kind' => 'beside_furniture', 'person' => 4, 'furniture' => 'toilet'], new Clue(ClueKind::BesideFurniture, person: 4, furniture: Furniture::Toilet)->toArray());
        self::assertSame(['kind' => 'west_of', 'person' => 1, 'other' => 0], new Clue(ClueKind::WestOf, person: 1, other: 0)->toArray());
        self::assertSame(['kind' => 'room_holds', 'room' => 0, 'number' => 2], new Clue(ClueKind::RoomHolds, room: 0, number: 2)->toArray());
    }
}
