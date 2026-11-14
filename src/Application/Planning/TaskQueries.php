<?php

declare(strict_types=1);

namespace App\Application\Planning;

use App\Domain\Planning\Project;
use App\Domain\Planning\Task;

interface TaskQueries
{
    /**
     * @return list<Task>
     */
    public function openForDay(\DateTimeImmutable $day): array;

    /**
     * @return list<Task>
     */
    public function completedBetween(\DateTimeImmutable $from, \DateTimeImmutable $until): array;

    public function lastCompletedBefore(\DateTimeImmutable $until): ?Task;

    /**
     * @return list<Task>
     */
    public function openUpcomingBetween(\DateTimeImmutable $first, \DateTimeImmutable $last): array;

    /**
     * @return list<Task>
     */
    public function openWithoutProject(): array;

    /**
     * @return list<Task>
     */
    public function ofProject(Project $project, \DateTimeImmutable $doneSince): array;

    /**
     * @return list<Task>
     */
    public function open(): array;

    /**
     * @return array<string, int>
     */
    public function openCountByProject(): array;
}
