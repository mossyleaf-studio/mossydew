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

    public function weight(): int
    {
        return match ($this) {
            self::InRow, self::InColumn => 1,
            default => 5,
        };
    }
}
