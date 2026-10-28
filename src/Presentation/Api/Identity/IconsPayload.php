<?php

declare(strict_types=1);

namespace App\Presentation\Api\Identity;

use App\Domain\Identity\IconSet;

final readonly class IconsPayload
{
    public function __construct(
        public IconSet $icons = IconSet::Lucide,
    ) {
    }
}
