<?php

declare(strict_types=1);

namespace App\Presentation\Api\Planning;

use App\Domain\Planning\Quadrant;

final readonly class ClassifyPayload
{
    public function __construct(
        public ?Quadrant $quadrant = null,
        public bool $withSubtasks = false,
    ) {
    }
}
