<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application\Planning;

use App\Application\Planning\ListTodayTasks\ListTodayTasksHandler;
use App\Application\Planning\ListTodayTasks\TodayView;
use App\Application\Planning\MoveOverdueToToday\MoveOverdueToTodayHandler;
use App\Domain\Planning\PlanShortcut;
use App\Domain\Planning\Quadrant;
use App\Tests\Support\ActsAsUser;
use App\Tests\Support\FreezesClock;
use App\Tests\Support\PlansTasks;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class TodayTest extends KernelTestCase
{
    use ActsAsUser;
    use FreezesClock;
    use PlansTasks;

    protected function setUp(): void
    {
        self::freezeAt('2026-10-06 08:00 UTC');
    }

    public function testTodayGathersOpenTasksPlannedOrDueUntilToday(): void
    {
        self::actAsNewUser();
        self::createTask('Planned today', PlanShortcut::Today);
        self::createTask('Due today, planned next week', planDate: '2026-10-12', dueOn: '2026-10-06');
        self::createTask('Missed deadline', dueOn: '2026-10-01');
        self::createTask('Missed deadline, planned tomorrow', PlanShortcut::Tomorrow, dueOn: '2026-10-01');
        self::createTask('From last week', planDate: '2026-09-29');
        self::createTask('From yesterday, due tomorrow', planDate: '2026-10-05', dueOn: '2026-10-07');
        self::createTask('From yesterday, due today', planDate: '2026-10-05', dueOn: '2026-10-06');
        self::createTask('Tomorrow', PlanShortcut::Tomorrow);
        self::createTask('Someday');
        self::createTask('Due next week', dueOn: '2026-10-13');
        self::completeTask(self::createTask('Done today', PlanShortcut::Today));
        self::freezeAt('2026-10-05 08:00 UTC');
        self::completeTask(self::createTask('Done yesterday', PlanShortcut::Today));
        self::freezeAt('2026-10-06 08:00 UTC');

        $today = $this->listToday();

        self::assertSame('2026-10-06', $today->date);
        self::assertEqualsCanonicalizing(['Planned today', 'Missed deadline', 'From yesterday, due today'], self::titles($today->today));
        self::assertEqualsCanonicalizing(['From last week', 'From yesterday, due tomorrow'], self::titles($today->earlier));
        self::assertSame(['Done today'], self::titles($today->done));
    }

    public function testTodayIsOrderedByTheMatrix(): void
    {
        self::actAsNewUser();
        self::createTask('Unsorted', PlanShortcut::Today);
        self::createTask('Compost', PlanShortcut::Today, quadrant: Quadrant::Eliminate);
        self::createTask('Water', PlanShortcut::Today, quadrant: Quadrant::DoFirst);
        self::createTask('Plant', PlanShortcut::Today, quadrant: Quadrant::Schedule);
        self::createTask('Trim', PlanShortcut::Today, quadrant: Quadrant::Delegate);

        self::assertSame(['Water', 'Plant', 'Trim', 'Compost', 'Unsorted'], self::titles($this->listToday()->today));
    }

    public function testTodayStartsAtMidnightInTheUsersTimezone(): void
    {
        self::freezeAt('2026-10-06 22:30 UTC');
        self::actAsNewUser('Europe/Paris');
        self::createTask('Wednesday', planDate: '2026-10-07');
        self::createTask('Tuesday', planDate: '2026-10-06');

        $today = $this->listToday();

        self::assertSame('2026-10-07', $today->date);
        self::assertSame(['Wednesday'], self::titles($today->today));
        self::assertSame(['Tuesday'], self::titles($today->earlier));

        self::actAsNewUser('UTC');
        self::createTask('Wednesday', planDate: '2026-10-07');

        self::assertSame('2026-10-06', $this->listToday()->date);
        self::assertSame([], $this->listToday()->today);
    }

    public function testDoneTodayFollowsTheUsersTimezone(): void
    {
        self::actAsNewUser('Europe/Paris');
        self::freezeAt('2026-10-06 21:30 UTC');
        self::completeTask(self::createTask('Late on Tuesday'));
        self::freezeAt('2026-10-06 22:30 UTC');
        self::completeTask(self::createTask('Just after midnight'));
        self::freezeAt('2026-10-07 08:00 UTC');

        self::assertSame(['Just after midnight'], self::titles($this->listToday()->done), 'Completion instants are stored in the server time zone but Day::startOf() binds the day window in the user time zone.');
    }

    public function testMoveOverdueToTodayPlansEarlierTasksForToday(): void
    {
        self::actAsNewUser();
        self::createTask('Last week', planDate: '2026-09-29');
        self::createTask('Yesterday', planDate: '2026-10-05');
        self::createTask('Today', PlanShortcut::Today);
        self::createTask('Tomorrow', PlanShortcut::Tomorrow);
        self::createTask('Someday');
        self::freezeAt('2026-10-04 08:00 UTC');
        self::completeTask(self::createTask('Done long ago', PlanShortcut::Today));
        self::freezeAt('2026-10-06 08:00 UTC');

        self::assertSame(2, self::getContainer()->get(MoveOverdueToTodayHandler::class)());

        $today = $this->listToday();
        self::assertEqualsCanonicalizing(['Last week', 'Yesterday', 'Today'], self::titles($today->today));
        self::assertSame([], $today->earlier);
        self::assertSame(0, self::getContainer()->get(MoveOverdueToTodayHandler::class)());
    }

    private function listToday(): TodayView
    {
        return self::getContainer()->get(ListTodayTasksHandler::class)();
    }
}
