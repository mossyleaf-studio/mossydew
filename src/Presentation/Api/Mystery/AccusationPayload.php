<?php

declare(strict_types=1);

namespace App\Presentation\Api\Mystery;

final readonly class AccusationPayload
{
    public function __construct(
        /** @var list<int> */
        public array $placements = [],
        public int $murderer = -1,
    ) {
    }
}
