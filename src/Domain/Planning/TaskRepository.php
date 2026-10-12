<?php

declare(strict_types=1);

namespace App\Domain\Planning;

use Symfony\Component\Uid\Ulid;

interface TaskRepository
{
    public function add(Task $task): void;

    public function remove(Task $task): void;

    public function get(Ulid $id): Task;

    /**
     * @param list<Ulid> $ids
     *
     * @return list<Task>
     */
    public function getAll(array $ids): array;

    /**
     * @return list<Task>
     */
    public function openInQuadrant(Quadrant $quadrant): array;

    public function nextRankIn(Quadrant $quadrant): int;

    /**
     * @return list<Task>
     */
    public function openPlannedBefore(\DateTimeImmutable $day): array;

    /**
     * @return array<string, Task>
     */
    public function linked(string $prefix): array;
}
