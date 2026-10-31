<?php

declare(strict_types=1);

namespace App\Domain\Mystery;

enum ClueKind: string
{
    case InRoom = 'in_room';
    case OnFurniture = 'on_furniture';
    case BesideFurniture = 'beside_furniture';
    case InRow = 'in_row';
    case InColumn = 'in_column';
    case SameRoom = 'same_room';
    case NorthOf = 'north_of';
    case WestOf = 'west_of';
    case EmptyRoom = 'empty_room';
    case RoomHolds = 'room_holds';
    case AloneWithMurderer = 'alone_with_murderer';
    case NorthBy = 'north_by';
    case WestBy = 'west_by';
    case FurnitureNorth = 'furniture_north';
    case FurnitureSouth = 'furniture_south';
    case FurnitureEast = 'furniture_east';
    case FurnitureWest = 'furniture_west';
    case Alone = 'alone';
    case AloneWith = 'alone_with';
    case GridCorner = 'grid_corner';
    case RoomCorner = 'room_corner';
    case OnlyOn = 'only_on';
    case RoomHas = 'room_has';
    case NotBeside = 'not_beside';
    case SomeoneOn = 'someone_on';
    case SomeoneBeside = 'someone_beside';
    case SameDiagonal = 'same_diagonal';
    case RoomAtLeast = 'room_at_least';
    case NorthOfFurniture = 'north_of_furniture';
    case SouthOfFurniture = 'south_of_furniture';
    case EastOfFurniture = 'east_of_furniture';
    case WestOfFurniture = 'west_of_furniture';
    case SeatCount = 'seat_count';
    case EveryRoomOccupied = 'every_room_occupied';
    case RoomCap = 'room_cap';
    case GenderSeated = 'gender_seated';
    case Pairing = 'pairing';

    public function weight(): int
    {
        return match ($this) {
            self::AloneWith => 8,
            self::Alone, self::SomeoneOn => 6,
            self::OnlyOn, self::GridCorner => 5,
            self::FurnitureNorth, self::FurnitureSouth, self::FurnitureEast, self::FurnitureWest, self::RoomCorner => 4,
            self::NorthBy, self::WestBy, self::OnFurniture, self::InRoom, self::SameDiagonal => 3,
            self::InRow, self::InColumn, self::BesideFurniture, self::SomeoneBeside, self::NotBeside, self::RoomHas => 2,
            self::NorthOfFurniture, self::SouthOfFurniture, self::EastOfFurniture, self::WestOfFurniture => 2,
            self::SameRoom, self::NorthOf, self::WestOf, self::EmptyRoom, self::RoomHolds, self::RoomAtLeast => 1,
            self::AloneWithMurderer, self::SeatCount, self::EveryRoomOccupied, self::RoomCap, self::GenderSeated, self::Pairing => 0,
        };
    }

    public function isGiven(): bool
    {
        return self::AloneWithMurderer === $this;
    }

    public function describesASquare(): bool
    {
        return match ($this) {
            self::InRoom, self::OnFurniture, self::BesideFurniture, self::InRow, self::InColumn, self::GridCorner, self::RoomCorner, self::RoomHas, self::NotBeside,
            self::NorthOfFurniture, self::SouthOfFurniture, self::EastOfFurniture, self::WestOfFurniture,
            self::FurnitureNorth, self::FurnitureSouth, self::FurnitureEast, self::FurnitureWest => true,
            default => false,
        };
    }

    public function isRule(): bool
    {
        return \in_array($this, [self::SeatCount, self::EveryRoomOccupied, self::RoomCap, self::EmptyRoom, self::GenderSeated, self::Pairing], true);
    }

    public function describesARoom(): bool
    {
        return \in_array($this, [self::EmptyRoom, self::RoomHolds, self::RoomAtLeast], true);
    }

    public function pointsAtFurniture(): bool
    {
        return \in_array($this, [self::FurnitureNorth, self::FurnitureSouth, self::FurnitureEast, self::FurnitureWest], true);
    }

    /**
     * @return array{int, int}
     */
    public function offset(): array
    {
        return match ($this) {
            self::FurnitureNorth => [-1, 0],
            self::FurnitureSouth => [1, 0],
            self::FurnitureEast => [0, 1],
            self::FurnitureWest => [0, -1],
            default => throw new \LogicException(\sprintf('A %s clue points nowhere.', $this->value)),
        };
    }
}
