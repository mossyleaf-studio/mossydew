<?php

declare(strict_types=1);

namespace App\Domain\Mystery;

enum Furniture: string
{
    case Armchair = 'armchair';
    case Sofa = 'sofa';
    case Bed = 'bed';
    case Plant = 'plant';
    case Books = 'books';
    case Bath = 'bath';
    case Toilet = 'toilet';
    case Tv = 'tv';
    case Piano = 'piano';
    case Lamp = 'lamp';
    case Stove = 'stove';
    case Washer = 'washer';

    public function canHoldSomeone(): bool
    {
        return match ($this) {
            self::Armchair, self::Sofa, self::Bed => true,
            default => false,
        };
    }
}
