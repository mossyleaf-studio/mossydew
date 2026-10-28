<?php

declare(strict_types=1);

namespace App\Domain\Identity;

enum Design: string
{
    case Classic = 'classic';
    case Cozy = 'cozy';
}
