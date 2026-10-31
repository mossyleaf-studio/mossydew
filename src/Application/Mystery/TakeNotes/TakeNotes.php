<?php

declare(strict_types=1);

namespace App\Application\Mystery\TakeNotes;

use Symfony\Component\Uid\Ulid;

final readonly class TakeNotes
{
    /**
     * @param list<?int> $placements
     * @param list<int>  $crosses
     */
    public function __construct(
        public Ulid $id,
        public array $placements,
        public array $crosses,
    ) {
    }
}
