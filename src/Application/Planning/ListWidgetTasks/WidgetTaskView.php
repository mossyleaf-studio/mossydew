<?php

declare(strict_types=1);

namespace App\Application\Planning\ListWidgetTasks;

use App\Domain\Planning\Task;

final readonly class WidgetTaskView
{
    private function __construct(
        public string $id,
        public string $title,
        public ?string $quadrant,
        public bool $done,
        public bool $nested,
        public bool $locked,
    ) {
    }

    public static function of(Task $task, bool $nested): self
    {
        return new self(
            (string) $task->id(),
            $task->title(),
            $task->quadrant()?->value,
            $task->isDone(),
            $nested,
            !$task->isDone() && $task->openSubtaskCount() > 0,
        );
    }
}
