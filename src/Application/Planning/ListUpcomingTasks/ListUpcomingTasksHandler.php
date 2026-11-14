<?php

declare(strict_types=1);

namespace App\Application\Planning\ListUpcomingTasks;

use App\Application\Planning\TaskQueries;
use App\Application\Planning\TaskView;
use App\Application\Planning\Today;
use App\Domain\Shared\Day;

final readonly class ListUpcomingTasksHandler
{
    public const int DAYS = 7;

    public function __construct(
        private TaskQueries $tasks,
        private Today $today,
    ) {
    }

    public function __invoke(?\DateTimeImmutable $from = null): UpcomingView
    {
        $today = $this->today->date();
        $first = null === $from ? $today : Day::normalize($from);
        $last = $first->modify(\sprintf('+%d days', self::DAYS - 1));
        $days = [];
        for ($day = $first; $day <= $last; $day = $day->modify('+1 day')) {
            $days[] = (string) Day::format($day);
        }

        return new UpcomingView((string) Day::format($today), $days, TaskView::list($this->tasks->openUpcomingBetween($first, $last)));
    }
}
