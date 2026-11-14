<?php

declare(strict_types=1);

namespace App\Application\Planning\ReorderProjects;

use Symfony\Component\Uid\Ulid;

final readonly class ReorderProjects
{
    /**
     * @param list<Ulid> $projectIds
     */
    public function __construct(
        public array $projectIds,
    ) {
    }
}
