<?php

declare(strict_types=1);

namespace App\Application\Planning\ReorderQuadrant;

use App\Domain\Planning\Quadrant;
use Symfony\Component\Uid\Ulid;

final readonly class ReorderQuadrant
{
    /**
     * @param list<Ulid> $taskIds
     */
    public function __construct(
        public Quadrant $quadrant,
        public array $taskIds,
        public bool $withSubtasks = false,
    ) {
    }
}
