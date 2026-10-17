<?php

declare(strict_types=1);

namespace App\Application\Planning\ClassifyTask;

use App\Application\Gamification\AchievementCheck;
use App\Application\Planning\TaskView;
use App\Application\Transaction;
use App\Domain\Planning\Quadrant;
use App\Domain\Planning\Task;
use App\Domain\Planning\TaskRepository;

final readonly class ClassifyTaskHandler
{
    public function __construct(
        private TaskRepository $tasks,
        private Transaction $transaction,
        private AchievementCheck $achievements,
    ) {
    }

    public function __invoke(ClassifyTask $command): TaskView
    {
        $task = $this->tasks->get($command->id);
        if (null === $command->quadrant) {
            $task->unclassify();
        } else {
            $this->classify($task, $command->quadrant);
        }
        $this->transaction->commit();
        ($this->achievements)();

        return TaskView::of($task);
    }

    private function classify(Task $task, Quadrant $quadrant): void
    {
        $rank = $this->tasks->nextRankIn($quadrant);
        if ($task->quadrant() !== $quadrant) {
            $task->classify($quadrant, $rank++);
        }
        foreach ($task->subtasks() as $subtask) {
            if (null === $subtask->quadrant()) {
                $subtask->classify($quadrant, $rank++);
            }
        }
    }
}
