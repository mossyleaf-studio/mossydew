<?php

declare(strict_types=1);

namespace App\Application\Mystery\Accuse;

use Symfony\Component\Uid\Ulid;

final readonly class Accuse
{
    /**
     * @param list<int> $placements
     */
    public function __construct(
        public Ulid $id,
        public array $placements,
        public int $murderer,
    ) {
    }
}
