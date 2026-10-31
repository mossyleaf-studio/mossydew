<?php

declare(strict_types=1);

namespace App\Domain\Mystery;

enum RoomKind: string
{
    case Kitchen = 'kitchen';
    case LivingRoom = 'living_room';
    case Bedroom = 'bedroom';
    case Bathroom = 'bathroom';
    case Library = 'library';
    case MusicRoom = 'music_room';
    case Study = 'study';
    case DiningRoom = 'dining_room';
    case Hall = 'hall';
    case Conservatory = 'conservatory';
    case GuestRoom = 'guest_room';
    case Nursery = 'nursery';
    case Attic = 'attic';
    case Laundry = 'laundry';

    /**
     * @return non-empty-list<Furniture>
     */
    public function furniture(): array
    {
        return match ($this) {
            self::Kitchen => [Furniture::Stove, Furniture::Armchair, Furniture::Plant, Furniture::Lamp],
            self::LivingRoom => [Furniture::Sofa, Furniture::Armchair, Furniture::Tv, Furniture::Lamp, Furniture::Plant],
            self::Bedroom => [Furniture::Bed, Furniture::Armchair, Furniture::Lamp, Furniture::Books],
            self::Bathroom => [Furniture::Bath, Furniture::Toilet, Furniture::Washer, Furniture::Plant],
            self::Library => [Furniture::Books, Furniture::Armchair, Furniture::Sofa, Furniture::Lamp],
            self::MusicRoom => [Furniture::Piano, Furniture::Armchair, Furniture::Sofa, Furniture::Lamp],
            self::Study => [Furniture::Books, Furniture::Armchair, Furniture::Lamp, Furniture::Plant],
            self::DiningRoom => [Furniture::Armchair, Furniture::Plant, Furniture::Lamp, Furniture::Piano],
            self::Hall => [Furniture::Plant, Furniture::Sofa, Furniture::Armchair, Furniture::Lamp],
            self::Conservatory => [Furniture::Plant, Furniture::Armchair, Furniture::Sofa, Furniture::Books],
            self::GuestRoom => [Furniture::Bed, Furniture::Armchair, Furniture::Tv, Furniture::Lamp],
            self::Nursery => [Furniture::Bed, Furniture::Armchair, Furniture::Books, Furniture::Plant],
            self::Attic => [Furniture::Books, Furniture::Bed, Furniture::Lamp, Furniture::Armchair],
            self::Laundry => [Furniture::Washer, Furniture::Armchair, Furniture::Plant, Furniture::Lamp],
        };
    }
}
