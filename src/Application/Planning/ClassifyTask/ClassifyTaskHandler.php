<?php

declare(strict_types=1);

namespace App\Application\Planning\ClassifyTask;

use App\Application\Gamification\AchievementCheck;
use App\Application\Planning\TaskView;
use App\Application\Transaction;
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
        $quadrant = $command->quadrant;
        $moved = $task->quadrant() === $quadrant ? [] : [$task, ...($command->withSubtasks ? $task->openSubtasksOutside($quadrant) : [])];
        $rank = null === $quadrant ? 0 : $this->tasks->nextRankIn($quadrant);
        foreach ($moved as $each) {
            if (null === $quadrant) {
                $each->unclassify();
            } else {
                $each->classify($quadrant, $rank++);
            }
        }
        $this->transaction->commit();
        ($this->achievements)();

        return TaskView::of($task);
    }
}
