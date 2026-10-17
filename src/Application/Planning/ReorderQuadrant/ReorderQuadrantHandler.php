<?php

declare(strict_types=1);

namespace App\Application\Planning\ReorderQuadrant;

use App\Application\Gamification\AchievementCheck;
use App\Application\Transaction;
use App\Domain\Planning\Exception\TaskNotOpen;
use App\Domain\Planning\Task;
use App\Domain\Planning\TaskRepository;

final readonly class ReorderQuadrantHandler
{
    public function __construct(
        private TaskRepository $tasks,
        private Transaction $transaction,
        private AchievementCheck $achievements,
    ) {
    }

    public function __invoke(ReorderQuadrant $command): void
    {
        $ordered = $this->tasks->getAll($command->taskIds);
        foreach ($ordered as $task) {
            if ($task->isDone()) {
                throw new TaskNotOpen($task->title());
            }
        }
        $orderedIds = array_map(static fn (Task $task): string => (string) $task->id(), $ordered);
        $others = array_filter(
            $this->tasks->openInQuadrant($command->quadrant),
            static fn (Task $task): bool => !\in_array((string) $task->id(), $orderedIds, true),
        );
        $followers = [];
        foreach ($command->withSubtasks ? $ordered : [] as $task) {
            foreach ($task->quadrant() === $command->quadrant ? [] : $task->openSubtasksOutside($command->quadrant) as $subtask) {
                if (!\in_array((string) $subtask->id(), $orderedIds, true)) {
                    $followers[(string) $subtask->id()] = $subtask;
                }
            }
        }

        foreach ([...$ordered, ...$others, ...array_values($followers)] as $rank => $task) {
            $task->classify($command->quadrant, $rank);
        }
        $this->transaction->commit();
        ($this->achievements)();
    }
}
