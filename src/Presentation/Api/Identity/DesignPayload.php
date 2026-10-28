<?php

declare(strict_types=1);

namespace App\Presentation\Api\Identity;

use App\Domain\Identity\Design;

final readonly class DesignPayload
{
    public function __construct(
        public Design $design = Design::Classic,
    ) {
    }
}
