<?php

declare(strict_types=1);

namespace App\Presentation\Api\Mystery;

final readonly class NotesPayload
{
    public function __construct(
        /** @var list<?int> */
        public array $placements = [],
        /** @var list<int> */
        public array $crosses = [],
    ) {
    }
}
