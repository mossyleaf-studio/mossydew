<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application\Planning;

use App\Application\Planning\ListInboxTasks\ListInboxTasksHandler;
use App\Application\Planning\ListProjectTasks\ListProjectTasksHandler;
use App\Application\Planning\ListUpcomingTasks\ListUpcomingTasksHandler;
use App\Application\Planning\ShowMatrix\ShowMatrixHandler;
use App\Domain\Planning\PlanShortcut;
use App\Domain\Planning\Quadrant;
use App\Domain\Shared\Day;
use App\Tests\Support\ActsAsUser;
use App\Tests\Support\FreezesClock;
use App\Tests\Support\PlansTasks;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Ulid;

final class ListsTest extends KernelTestCase
{
    use ActsAsUser;
    use FreezesClock;
    use PlansTasks;

    protected function setUp(): void
    {
        self::freezeAt('2026-10-06 08:00 UTC');
        self::actAsNewUser();
    }

    public function testUpcomingShowsOpenTasksPlannedOverSevenDaysFromToday(): void
    {
        self::createTask('Yesterday', planDate: '2026-10-05');
        self::createTask('Today', PlanShortcut::Today);
        self::createTask('Monday', planDate: '2026-10-12');
        self::createTask('In a week', planDate: '2026-10-13');
        self::completeTask(self::createTask('Done tomorrow', PlanShortcut::Tomorrow));

        $upcoming = self::getContainer()->get(ListUpcomingTasksHandler::class)();

        self::assertSame('2026-10-06', $upcoming->today);
        self::assertSame(['2026-10-06', '2026-10-07', '2026-10-08', '2026-10-09', '2026-10-10', '2026-10-11', '2026-10-12'], $upcoming->days);
        self::assertEqualsCanonicalizing(['Today', 'Monday'], self::titles($upcoming->tasks));
    }

    public function testUpcomingShowsATaskWithoutPlannedDayOnItsDeadline(): void
    {
        self::createTask('Due Thursday', dueOn: '2026-10-08');
        self::createTask('Due today', dueOn: '2026-10-06');
        self::createTask('Due yesterday', dueOn: '2026-10-05');
        self::createTask('Due in a week', dueOn: '2026-10-13');
        self::createTask('Planned Friday, due Thursday', planDate: '2026-10-09', dueOn: '2026-10-08');
        self::createTask('Planned yesterday, due Thursday', planDate: '2026-10-05', dueOn: '2026-10-08');
        self::createTask('Planned in a week, due Thursday', planDate: '2026-10-13', dueOn: '2026-10-08');
        self::completeTask(self::createTask('Done, due Thursday', dueOn: '2026-10-08'));

        $upcoming = self::getContainer()->get(ListUpcomingTasksHandler::class)();

        self::assertEqualsCanonicalizing(['Due Thursday', 'Due today', 'Planned Friday, due Thursday'], self::titles($upcoming->tasks));
    }

    public function testUpcomingFromAChosenDay(): void
    {
        self::createTask('Monday', planDate: '2026-10-12');
        self::createTask('In a week', planDate: '2026-10-13');
        self::createTask('In three weeks', planDate: '2026-10-27');

        $upcoming = self::getContainer()->get(ListUpcomingTasksHandler::class)(Day::of('2026-10-13'));

        self::assertSame('2026-10-06', $upcoming->today);
        self::assertSame('2026-10-13', $upcoming->days[0]);
        self::assertSame('2026-10-19', $upcoming->days[6]);
        self::assertSame(['In a week'], self::titles($upcoming->tasks));
    }

    public function testInboxIsOpenTasksWithoutProject(): void
    {
        $home = self::createProject('Home');
        self::createTask('Thought');
        self::createTask('Planned thought', PlanShortcut::Today);
        self::createTask('Laundry', projectId: $home->id);
        self::completeTask(self::createTask('Done thought'));

        self::assertEqualsCanonicalizing(['Thought', 'Planned thought'], self::titles(self::getContainer()->get(ListInboxTasksHandler::class)()));
    }

    public function testProjectShowsOpenTasksAndThoseDoneInTheLastTwoWeeks(): void
    {
        $home = self::createProject('Home');
        $work = self::createProject('Work');
        self::freezeAt('2026-09-20 08:00 UTC');
        self::completeTask(self::createTask('Done three weeks ago', projectId: $home->id));
        self::freezeAt('2026-09-23 08:00 UTC');
        self::completeTask(self::createTask('Done thirteen days ago', projectId: $home->id));
        self::freezeAt('2026-10-06 08:00 UTC');
        self::createTask('Someday', projectId: $home->id);
        self::createTask('Next month', planDate: '2026-11-10', projectId: $home->id);
        self::createTask('Report', projectId: $work->id);

        $tasks = self::getContainer()->get(ListProjectTasksHandler::class)(Ulid::fromString($home->id));

        self::assertSame(['Someday', 'Next month', 'Done thirteen days ago'], self::titles($tasks));
    }

    public function testMatrixOrdersByQuadrantThenRankThenDeadlineThenCreation(): void
    {
        self::createTask('Unsorted early deadline', dueOn: '2026-10-07');
        self::createTask('Unsorted no deadline');
        self::createTask('Unsorted later deadline', dueOn: '2026-10-20');
        self::createTask('Compost', quadrant: Quadrant::Eliminate);
        self::createTask('Plant first', quadrant: Quadrant::Schedule);
        self::createTask('Plant second', quadrant: Quadrant::Schedule);
        self::createTask('Water', quadrant: Quadrant::DoFirst);
        self::createTask('Trim', quadrant: Quadrant::Delegate);
        self::freezeAt('2026-10-06 09:00 UTC');
        self::createTask('Unsorted created later');
        self::completeTask(self::createTask('Done water', quadrant: Quadrant::DoFirst));

        self::assertSame(
            ['Water', 'Plant first', 'Plant second', 'Trim', 'Compost', 'Unsorted early deadline', 'Unsorted later deadline', 'Unsorted no deadline', 'Unsorted created later'],
            self::titles(self::getContainer()->get(ShowMatrixHandler::class)()),
        );
    }
}
