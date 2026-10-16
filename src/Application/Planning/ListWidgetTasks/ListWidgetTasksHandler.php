<?php

declare(strict_types=1);

namespace App\Application\Planning\ListWidgetTasks;

use App\Application\Identity\CurrentUser;
use App\Application\Planning\TaskQueries;
use App\Application\Planning\Today;
use App\Domain\Planning\Task;
use App\Domain\Shared\Day;

final readonly class ListWidgetTasksHandler
{
    public function __construct(
        private TaskQueries $tasks,
        private Today $today,
        private CurrentUser $currentUser,
    ) {
    }

    public function __invoke(): WidgetView
    {
        $today = $this->today->date();
        $user = $this->currentUser->get();
        $theme = $user->theme();

        return new WidgetView(
            (string) Day::format($today),
            null === $theme ? null : ['background' => $theme->background, 'accent' => $theme->accent, 'dark' => $theme->isDark()],
            [
                ...self::nested($this->tasks->openForDay($today)),
                ...self::nested($this->tasks->completedBetween(Day::startOf($today, $user->zone()), Day::startOf($today->modify('+1 day'), $user->zone()))),
            ],
        );
    }

    /**
     * @param list<Task> $tasks
     *
     * @return list<WidgetTaskView>
     */
    private static function nested(array $tasks): array
    {
        $listed = array_flip(array_map(static fn (Task $task): string => (string) $task->id(), $tasks));
        $views = [];
        foreach ($tasks as $task) {
            $parent = $task->parent();
            if (null !== $parent && isset($listed[(string) $parent->id()])) {
                continue;
            }
            $views[] = WidgetTaskView::of($task, false);
            foreach ($task->subtasks() as $subtask) {
                if (isset($listed[(string) $subtask->id()])) {
                    $views[] = WidgetTaskView::of($subtask, true);
                }
            }
        }

        return $views;
    }
}
