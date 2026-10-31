<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Mystery;

use App\Domain\Mystery\Floorplan;
use App\Domain\Mystery\Furniture;
use App\Domain\Mystery\RoomKind;
use PHPUnit\Framework\TestCase;

final class FloorplanTest extends TestCase
{
    public const array SOLUTION = [0, 11, 9, 17, 23];

    public static function manor(): Floorplan
    {
        return new Floorplan(5, [
            0, 0, 1, 1, 1,
            0, 0, 1, 2, 2,
            3, 0, 2, 2, 2,
            3, 3, 4, 4, 2,
            3, 3, 4, 4, 4,
        ], [RoomKind::Kitchen, RoomKind::Library, RoomKind::LivingRoom, RoomKind::Bedroom, RoomKind::Bathroom], [
            null, Furniture::Stove, null, Furniture::Books, null,
            Furniture::Armchair, null, null, null, Furniture::Sofa,
            null, null, Furniture::Tv, null, null,
            Furniture::Bed, null, null, Furniture::Bath, null,
            null, null, null, null, Furniture::Toilet,
        ]);
    }

    public function testFindsTheRoomOfACell(): void
    {
        self::assertSame(0, self::manor()->roomOf(11));
        self::assertSame(2, self::manor()->roomOf(19));
    }

    public function testOnlyFurnitureNobodyCanSitOnBlocksItsCell(): void
    {
        self::assertTrue(self::manor()->blocks(1));
        self::assertTrue(self::manor()->blocks(24));
        self::assertFalse(self::manor()->blocks(9));
        self::assertFalse(self::manor()->blocks(15));
        self::assertFalse(self::manor()->blocks(0));
    }

    public function testBesideMeansAnOrthogonalNeighbourHoldsThePiece(): void
    {
        $manor = self::manor();

        self::assertTrue($manor->isBeside(23, Furniture::Toilet));
        self::assertTrue($manor->isBeside(4, Furniture::Sofa));
        self::assertTrue($manor->isBeside(10, Furniture::Armchair));
        self::assertFalse($manor->isBeside(24, Furniture::Toilet));
        self::assertFalse($manor->isBeside(13, Furniture::Sofa));
        self::assertFalse($manor->isBeside(4, Furniture::Armchair));
        self::assertFalse($manor->isBeside(10, Furniture::Sofa));
    }

    public function testTheMurdererIsTheOtherPersonInTheVictimsRoom(): void
    {
        self::assertSame(1, self::manor()->murdererIn(self::SOLUTION, 0));
        self::assertSame(4, self::manor()->murdererIn(self::SOLUTION, 3));
    }

    public function testThereIsNoMurdererUnlessTheVictimsRoomHoldsExactlyTwoPeople(): void
    {
        self::assertNull(self::manor()->murdererIn(self::SOLUTION, 2));
        self::assertNull(self::manor()->murdererIn([0, 5, 6, 17, 23], 0));
    }

    public function testSurvivesARoundTripThroughAnArray(): void
    {
        $data = self::manor()->toArray();

        self::assertSame(5, $data['size']);
        self::assertSame(['kitchen', 'library', 'living_room', 'bedroom', 'bathroom'], $data['kinds']);
        self::assertSame([null, 'stove', null, 'books', null], \array_slice($data['furniture'], 0, 5));
        self::assertEquals(self::manor(), Floorplan::fromArray($data));
    }
}
