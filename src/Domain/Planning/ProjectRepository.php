<?php

declare(strict_types=1);

namespace App\Domain\Planning;

use Symfony\Component\Uid\Ulid;

interface ProjectRepository
{
    public function add(Project $project): void;

    public function remove(Project $project): void;

    public function get(Ulid $id): Project;

    public function named(string $name): ?Project;

    /**
     * @return list<Project>
     */
    public function all(): array;

    public function nextPosition(): int;
}
