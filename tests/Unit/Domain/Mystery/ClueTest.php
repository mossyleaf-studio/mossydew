<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Mystery;

use App\Domain\Mystery\Bound;
use App\Domain\Mystery\Clue;
use App\Domain\Mystery\ClueKind;
use App\Domain\Mystery\Furniture;
use App\Domain\Mystery\Gender;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ClueTest extends TestCase
{
    #[DataProvider('clues')]
    public function testTellsWhetherItHoldsForAPlacement(Clue $clue, bool $holds): void
    {
        self::assertSame($holds, $clue->holdsFor(FloorplanTest::manor(), FloorplanTest::SOLUTION, FloorplanTest::GENDERS));
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
        yield 'rows further north' => [new Clue(ClueKind::NorthBy, person: 2, other: 1, number: 1), true];
        yield 'other rows further north' => [new Clue(ClueKind::NorthBy, person: 2, other: 1, number: 2), false];
        yield 'rows further south' => [new Clue(ClueKind::NorthBy, person: 1, other: 2, number: 1), false];
        yield 'columns further west' => [new Clue(ClueKind::WestBy, person: 1, other: 2, number: 3), true];
        yield 'other columns further west' => [new Clue(ClueKind::WestBy, person: 1, other: 2, number: 2), false];
        yield 'columns further east' => [new Clue(ClueKind::WestBy, person: 2, other: 1, number: 3), false];
        yield 'furniture to the north' => [new Clue(ClueKind::FurnitureNorth, person: 4, furniture: Furniture::Bath, number: 1), true];
        yield 'other furniture to the north' => [new Clue(ClueKind::FurnitureNorth, person: 4, furniture: Furniture::Toilet, number: 1), false];
        yield 'nothing north of the top row' => [new Clue(ClueKind::FurnitureNorth, person: 0, furniture: Furniture::Stove, number: 1), false];
        yield 'furniture to the south' => [new Clue(ClueKind::FurnitureSouth, person: 0, furniture: Furniture::Armchair, number: 1), true];
        yield 'nothing to the south' => [new Clue(ClueKind::FurnitureSouth, person: 2, furniture: Furniture::Sofa, number: 1), false];
        yield 'furniture to the east' => [new Clue(ClueKind::FurnitureEast, person: 4, furniture: Furniture::Toilet, number: 1), true];
        yield 'furniture to the west, not the east' => [new Clue(ClueKind::FurnitureWest, person: 4, furniture: Furniture::Toilet, number: 1), false];
        yield 'nothing to the west' => [new Clue(ClueKind::FurnitureWest, person: 1, furniture: Furniture::Armchair, number: 1), false];
        yield 'nothing east of the last column' => [new Clue(ClueKind::FurnitureEast, person: 2, furniture: Furniture::Tv, number: 1), false];
        yield 'furniture squares to the south' => [new Clue(ClueKind::FurnitureSouth, person: 0, furniture: Furniture::Bed, number: 3), true];
        yield 'furniture other squares to the south' => [new Clue(ClueKind::FurnitureSouth, person: 0, furniture: Furniture::Bed, number: 2), false];
        yield 'furniture squares to the north' => [new Clue(ClueKind::FurnitureNorth, person: 4, furniture: Furniture::Books, number: 4), true];
        yield 'furniture squares to the north, not next door' => [new Clue(ClueKind::FurnitureNorth, person: 4, furniture: Furniture::Bath, number: 4), false];
        yield 'furniture squares north past the top row' => [new Clue(ClueKind::FurnitureNorth, person: 1, furniture: Furniture::Stove, number: 3), false];
        yield 'beside furniture through a wall' => [new Clue(ClueKind::BesideFurniture, person: 3, furniture: Furniture::Tv), false];
        yield 'a piece in the room' => [new Clue(ClueKind::RoomHas, person: 1, furniture: Furniture::Stove), true];
        yield 'no such piece in the room' => [new Clue(ClueKind::RoomHas, person: 1, furniture: Furniture::Bed), false];
        yield 'not beside the piece' => [new Clue(ClueKind::NotBeside, person: 4, furniture: Furniture::Sofa), true];
        yield 'beside the piece after all' => [new Clue(ClueKind::NotBeside, person: 4, furniture: Furniture::Toilet), false];
        yield 'not beside a piece behind a wall' => [new Clue(ClueKind::NotBeside, person: 3, furniture: Furniture::Tv), true];
        yield 'someone else beside the piece in the room' => [new Clue(ClueKind::SomeoneBeside, person: 3, furniture: Furniture::Toilet), true];
        yield 'nobody else beside the piece in the room' => [new Clue(ClueKind::SomeoneBeside, person: 4, furniture: Furniture::Toilet), false];
        yield 'someone else beside another piece in the room' => [new Clue(ClueKind::SomeoneBeside, person: 4, furniture: Furniture::Bath), true];
        yield 'nobody sitting in the room' => [new Clue(ClueKind::SomeoneOn, person: 3, gender: Gender::Female, furniture: Furniture::Armchair), false];
        yield 'on the same diagonal' => [new Clue(ClueKind::SameDiagonal, person: 3, other: 4), true];
        yield 'on the same anti-diagonal' => [new Clue(ClueKind::SameDiagonal, person: 2, other: 3), true];
        yield 'on different diagonals' => [new Clue(ClueKind::SameDiagonal, person: 1, other: 2), false];
        yield 'north of a piece' => [new Clue(ClueKind::NorthOfFurniture, person: 0, furniture: Furniture::Toilet), true];
        yield 'on the row of the piece, not north of it' => [new Clue(ClueKind::NorthOfFurniture, person: 4, furniture: Furniture::Toilet), false];
        yield 'south of a piece' => [new Clue(ClueKind::SouthOfFurniture, person: 4, furniture: Furniture::Stove), true];
        yield 'north of the piece, not south' => [new Clue(ClueKind::SouthOfFurniture, person: 0, furniture: Furniture::Bed), false];
        yield 'east of a piece' => [new Clue(ClueKind::EastOfFurniture, person: 2, furniture: Furniture::Bed), true];
        yield 'on the column of the piece, not east of it' => [new Clue(ClueKind::EastOfFurniture, person: 0, furniture: Furniture::Bed), false];
        yield 'west of a piece' => [new Clue(ClueKind::WestOfFurniture, person: 0, furniture: Furniture::Sofa), true];
        yield 'on the piece, not west of it' => [new Clue(ClueKind::WestOfFurniture, person: 2, furniture: Furniture::Sofa), false];
        yield 'at least two in the room' => [new Clue(ClueKind::RoomAtLeast, room: 4, number: 2), true];
        yield 'fewer than three in the room' => [new Clue(ClueKind::RoomAtLeast, room: 4, number: 3), false];
        yield 'fewer than two in an empty room' => [new Clue(ClueKind::RoomAtLeast, room: 1, number: 2), false];
        yield 'exactly one on a sofa' => [new Clue(ClueKind::SeatCount, furniture: Furniture::Sofa, number: 1, bound: Bound::Exactly), true];
        yield 'nobody on an armchair' => [new Clue(ClueKind::SeatCount, furniture: Furniture::Armchair, number: 0, bound: Bound::Exactly), true];
        yield 'not two on a sofa' => [new Clue(ClueKind::SeatCount, furniture: Furniture::Sofa, number: 2, bound: Bound::AtLeast), false];
        yield 'at least one on a sofa' => [new Clue(ClueKind::SeatCount, furniture: Furniture::Sofa, number: 1, bound: Bound::AtLeast), true];
        yield 'at most one on a sofa' => [new Clue(ClueKind::SeatCount, furniture: Furniture::Sofa, number: 1, bound: Bound::AtMost), true];
        yield 'not at most none on a sofa' => [new Clue(ClueKind::SeatCount, furniture: Furniture::Sofa, number: 0, bound: Bound::AtMost), false];
        yield 'some room is empty' => [new Clue(ClueKind::EveryRoomOccupied), false];
        yield 'no room holds more than two' => [new Clue(ClueKind::RoomCap, number: 2), true];
        yield 'a room holds more than one' => [new Clue(ClueKind::RoomCap, number: 1), false];
        yield 'one woman on a sofa' => [new Clue(ClueKind::GenderSeated, furniture: Furniture::Sofa, number: 1, gender: Gender::Female), true];
        yield 'no man on a sofa' => [new Clue(ClueKind::GenderSeated, furniture: Furniture::Sofa, number: 0, gender: Gender::Male), true];
        yield 'not one man on a sofa' => [new Clue(ClueKind::GenderSeated, furniture: Furniture::Sofa, number: 1, gender: Gender::Male), false];
        yield 'every man with a woman' => [new Clue(ClueKind::Pairing, gender: Gender::Male), true];
        yield 'a woman without a man' => [new Clue(ClueKind::Pairing, gender: Gender::Female), false];
        yield 'alone in the room' => [new Clue(ClueKind::Alone, person: 2), true];
        yield 'not alone in the room' => [new Clue(ClueKind::Alone, person: 3), false];
        yield 'the victim is never alone' => [new Clue(ClueKind::Alone, person: 0), false];
        yield 'alone with a woman' => [new Clue(ClueKind::AloneWith, person: 3, gender: Gender::Female), true];
        yield 'alone with a man, not a woman' => [new Clue(ClueKind::AloneWith, person: 3, gender: Gender::Male), false];
        yield 'alone with nobody' => [new Clue(ClueKind::AloneWith, person: 2, gender: Gender::Female), false];
        yield 'in a corner of the grid' => [new Clue(ClueKind::GridCorner, person: 0), true];
        yield 'on the edge, not in a corner of the grid' => [new Clue(ClueKind::GridCorner, person: 2), false];
        yield 'in a corner of the room' => [new Clue(ClueKind::RoomCorner, person: 2), true];
        yield 'along a wall, not in a corner of the room' => [new Clue(ClueKind::RoomCorner, person: 4), false];
        yield 'the only one on a sofa' => [new Clue(ClueKind::OnlyOn, person: 2, furniture: Furniture::Sofa), true];
        yield 'not on an armchair at all' => [new Clue(ClueKind::OnlyOn, person: 2, furniture: Furniture::Armchair), false];
        yield 'in the room and in its corner' => [new Clue(ClueKind::InRoom, person: 2, room: 2, then: new Clue(ClueKind::RoomCorner, person: 2)), true];
        yield 'in the room but not in a corner of the grid' => [new Clue(ClueKind::InRoom, person: 2, room: 2, then: new Clue(ClueKind::GridCorner, person: 2)), false];
        yield 'in the row while a room is empty' => [new Clue(ClueKind::InRow, person: 1, number: 2, then: new Clue(ClueKind::EmptyRoom, room: 1)), true];
        yield 'in the row while a full room is said empty' => [new Clue(ClueKind::InRow, person: 1, number: 2, then: new Clue(ClueKind::EmptyRoom, room: 4)), false];
        yield 'in another row while a room is empty' => [new Clue(ClueKind::InRow, person: 1, number: 1, then: new Clue(ClueKind::EmptyRoom, room: 1)), false];
        yield 'alone with a woman beside the toilet' => [new Clue(ClueKind::AloneWith, person: 3, gender: Gender::Female, then: new Clue(ClueKind::BesideFurniture, furniture: Furniture::Toilet, companion: true)), true];
        yield 'alone with a woman not in the bath' => [new Clue(ClueKind::AloneWith, person: 3, gender: Gender::Female, then: new Clue(ClueKind::OnFurniture, furniture: Furniture::Bath, companion: true)), false];
        yield 'the companion of nobody' => [new Clue(ClueKind::AloneWith, person: 2, gender: Gender::Female, then: new Clue(ClueKind::RoomCorner, companion: true)), false];
    }

    public function testSittingOnTheOnlySeatOfItsKindFailsOnceSomeoneElseSitsOnOne(): void
    {
        $clue = new Clue(ClueKind::OnlyOn, person: 2, furniture: Furniture::Sofa);

        self::assertFalse($clue->holdsFor(FloorplanTest::manor()->with(19, Furniture::Sofa), [0, 11, 9, 19, 23], FloorplanTest::GENDERS));
    }

    public function testSomeoneElseOfThatGenderSitsOnThatSeatInTheRoom(): void
    {
        $plan = FloorplanTest::manor()->with(23, Furniture::Armchair);
        $holds = static fn (Clue $clue): bool => $clue->holdsFor($plan, FloorplanTest::SOLUTION, FloorplanTest::GENDERS);

        self::assertTrue($holds(new Clue(ClueKind::SomeoneOn, person: 3, gender: Gender::Female, furniture: Furniture::Armchair)));
        self::assertFalse($holds(new Clue(ClueKind::SomeoneOn, person: 3, gender: Gender::Male, furniture: Furniture::Armchair)));
        self::assertFalse($holds(new Clue(ClueKind::SomeoneOn, person: 3, gender: Gender::Female, furniture: Furniture::Sofa)));
        self::assertFalse($holds(new Clue(ClueKind::SomeoneOn, person: 4, gender: Gender::Female, furniture: Furniture::Armchair)));
    }

    public function testEveryRoomIsOccupiedOnceSomeoneStandsInEach(): void
    {
        self::assertTrue(new Clue(ClueKind::EveryRoomOccupied)->holdsFor(FloorplanTest::manor(), [0, 2, 9, 15, 23], FloorplanTest::GENDERS));
    }

    public function testTellsWhatItSaysAboutThePersonsOwnSquare(): void
    {
        $inRow = new Clue(ClueKind::InRow, person: 1, number: 2);

        self::assertSame($inRow, $inRow->square());
        self::assertEquals(new Clue(ClueKind::OnFurniture, furniture: Furniture::Sofa), new Clue(ClueKind::OnlyOn, person: 2, furniture: Furniture::Sofa)->square());
        self::assertEquals(new Clue(ClueKind::RoomHas, furniture: Furniture::Bed), new Clue(ClueKind::SomeoneOn, person: 2, gender: Gender::Male, furniture: Furniture::Bed)->square());
        self::assertEquals(new Clue(ClueKind::RoomHas, furniture: Furniture::Toilet), new Clue(ClueKind::SomeoneBeside, person: 3, furniture: Furniture::Toilet)->square());
        self::assertNull(new Clue(ClueKind::SameRoom, person: 3, other: 4)->square());
        self::assertNull(new Clue(ClueKind::Alone, person: 2)->square());
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
        self::assertSame(['kind' => 'north_by', 'person' => 2, 'other' => 1, 'number' => 1], new Clue(ClueKind::NorthBy, person: 2, other: 1, number: 1)->toArray());
        self::assertSame(['kind' => 'furniture_west', 'person' => 3, 'furniture' => 'tv', 'number' => 1], new Clue(ClueKind::FurnitureWest, person: 3, furniture: Furniture::Tv, number: 1)->toArray());
        self::assertSame(['kind' => 'alone', 'person' => 2], new Clue(ClueKind::Alone, person: 2)->toArray());
        self::assertSame(['kind' => 'seat_count', 'furniture' => 'sofa', 'number' => 1, 'bound' => 'at_least'], new Clue(ClueKind::SeatCount, furniture: Furniture::Sofa, number: 1, bound: Bound::AtLeast)->toArray());
        self::assertSame(['kind' => 'every_room_occupied'], new Clue(ClueKind::EveryRoomOccupied)->toArray());
        self::assertSame(['kind' => 'room_cap', 'number' => 2], new Clue(ClueKind::RoomCap, number: 2)->toArray());
        self::assertSame(['kind' => 'gender_seated', 'furniture' => 'bed', 'number' => 0, 'gender' => 'female'], new Clue(ClueKind::GenderSeated, furniture: Furniture::Bed, number: 0, gender: Gender::Female)->toArray());
        self::assertSame(['kind' => 'pairing', 'gender' => 'male'], new Clue(ClueKind::Pairing, gender: Gender::Male)->toArray());
        self::assertSame(['kind' => 'room_at_least', 'room' => 4, 'number' => 2], new Clue(ClueKind::RoomAtLeast, room: 4, number: 2)->toArray());
        self::assertSame(['kind' => 'north_of_furniture', 'person' => 0, 'furniture' => 'toilet'], new Clue(ClueKind::NorthOfFurniture, person: 0, furniture: Furniture::Toilet)->toArray());
        self::assertSame(['kind' => 'only_on', 'person' => 2, 'furniture' => 'sofa'], new Clue(ClueKind::OnlyOn, person: 2, furniture: Furniture::Sofa)->toArray());
        self::assertSame(
            ['kind' => 'in_room', 'person' => 2, 'room' => 2, 'then' => ['kind' => 'room_corner', 'person' => 2]],
            new Clue(ClueKind::InRoom, person: 2, room: 2, then: new Clue(ClueKind::RoomCorner, person: 2))->toArray(),
        );
        self::assertSame(
            ['kind' => 'alone_with', 'person' => 3, 'gender' => 'female', 'then' => ['kind' => 'beside_furniture', 'furniture' => 'toilet', 'companion' => true]],
            new Clue(ClueKind::AloneWith, person: 3, gender: Gender::Female, then: new Clue(ClueKind::BesideFurniture, furniture: Furniture::Toilet, companion: true))->toArray(),
        );
    }
}
