<?php

declare(strict_types=1);

namespace App\Application\Planning\ClassifyTask;

use App\Domain\Planning\Quadrant;
use Symfony\Component\Uid\Ulid;

final readonly class ClassifyTask
{
    public function __construct(
        public Ulid $id,
        public ?Quadrant $quadrant,
        public bool $withSubtasks = false,
    ) {
    }
}
