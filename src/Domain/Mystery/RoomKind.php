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

    private const array FURNITURE = [
        'kitchen' => [Furniture::Stove, Furniture::Armchair, Furniture::Plant, Furniture::Lamp],
        'living_room' => [Furniture::Sofa, Furniture::Armchair, Furniture::Tv, Furniture::Lamp, Furniture::Plant],
        'bedroom' => [Furniture::Bed, Furniture::Armchair, Furniture::Lamp, Furniture::Books],
        'bathroom' => [Furniture::Bath, Furniture::Toilet, Furniture::Washer, Furniture::Plant],
        'library' => [Furniture::Books, Furniture::Armchair, Furniture::Sofa, Furniture::Lamp],
        'music_room' => [Furniture::Piano, Furniture::Armchair, Furniture::Sofa, Furniture::Lamp],
        'study' => [Furniture::Books, Furniture::Armchair, Furniture::Lamp, Furniture::Plant],
        'dining_room' => [Furniture::Armchair, Furniture::Plant, Furniture::Lamp, Furniture::Piano],
        'hall' => [Furniture::Plant, Furniture::Sofa, Furniture::Armchair, Furniture::Lamp],
        'conservatory' => [Furniture::Plant, Furniture::Armchair, Furniture::Sofa, Furniture::Books],
        'guest_room' => [Furniture::Bed, Furniture::Armchair, Furniture::Tv, Furniture::Lamp],
        'nursery' => [Furniture::Bed, Furniture::Armchair, Furniture::Books, Furniture::Plant],
        'attic' => [Furniture::Books, Furniture::Bed, Furniture::Lamp, Furniture::Armchair],
        'laundry' => [Furniture::Washer, Furniture::Armchair, Furniture::Plant, Furniture::Lamp],
    ];

    /**
     * @return non-empty-list<Furniture>
     */
    public function furniture(): array
    {
        return self::FURNITURE[$this->value];
    }
}
