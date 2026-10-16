<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application\Planning;

use App\Application\Identity\ChooseTheme\ChooseThemeHandler;
use App\Application\Planning\ClassifyTask\ClassifyTask;
use App\Application\Planning\ClassifyTask\ClassifyTaskHandler;
use App\Application\Planning\ListWidgetTasks\ListWidgetTasksHandler;
use App\Application\Planning\ListWidgetTasks\WidgetTaskView;
use App\Application\Planning\ListWidgetTasks\WidgetView;
use App\Domain\Identity\Theme;
use App\Domain\Planning\PlanShortcut;
use App\Domain\Planning\Quadrant;
use App\Tests\Support\ActsAsUser;
use App\Tests\Support\FreezesClock;
use App\Tests\Support\PlansTasks;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Ulid;

final class WidgetTest extends KernelTestCase
{
    use ActsAsUser;
    use FreezesClock;
    use PlansTasks;

    protected function setUp(): void
    {
        self::freezeAt('2026-10-06 08:00 UTC');
    }

    public function testTheWidgetListsTodayInMatrixOrderThenTheTasksDoneToday(): void
    {
        self::actAsNewUser();
        self::createTask('Not mine', PlanShortcut::Today);
        self::actAsNewUser();
        self::createTask('Unsorted', PlanShortcut::Today);
        self::createTask('Compost', PlanShortcut::Today, quadrant: Quadrant::Eliminate);
        self::createTask('Water', PlanShortcut::Today, quadrant: Quadrant::DoFirst);
        self::createTask('From earlier', planDate: '2026-10-01', quadrant: Quadrant::Schedule);
        self::createTask('Missed deadline', dueOn: '2026-10-01', quadrant: Quadrant::Delegate);
        self::createTask('Tomorrow', PlanShortcut::Tomorrow);
        self::createTask('Someday');
        self::completeTask(self::createTask('Done today', PlanShortcut::Today));
        self::freezeAt('2026-10-05 08:00 UTC');
        self::completeTask(self::createTask('Done yesterday', PlanShortcut::Today));
        self::freezeAt('2026-10-06 08:00 UTC');

        $widget = $this->widget();

        self::assertSame('2026-10-06', $widget->date);
        self::assertSame(['Water', 'From earlier', 'Missed deadline', 'Compost', 'Unsorted', 'Done today'], self::widgetTitles($widget));
        self::assertSame([false, false, false, false, false, true], array_map(static fn (WidgetTaskView $task): bool => $task->done, $widget->tasks));
    }

    public function testSubtasksComeRightUnderTheirParentInsideEachPart(): void
    {
        self::actAsNewUser();
        self::freezeAt('2026-10-06 07:00 UTC');
        $drawing = self::createTask('Drawing', PlanShortcut::Today, quadrant: Quadrant::Schedule);
        self::createTask('Chore', PlanShortcut::Today);
        self::addSubtask($drawing, 'Sketch');
        $colouring = self::addSubtask($drawing, 'Colouring');
        self::completeTask(self::addSubtask($drawing, 'Render'));
        self::getContainer()->get(ClassifyTaskHandler::class)(new ClassifyTask(Ulid::fromString($colouring->id), Quadrant::DoFirst));
        self::freezeAt('2026-10-06 07:30 UTC');
        self::completeTask(self::addSubtask(self::createTask('Errands', PlanShortcut::Today), 'Milk'));
        self::freezeAt('2026-10-06 08:00 UTC');

        self::assertSame([
            ['Drawing', false, true],
            ['Sketch', true, false],
            ['Colouring', true, false],
            ['Chore', false, false],
            ['Errands', false, false],
            ['Milk', true, false],
            ['Render', false, false],
        ], array_map(static fn (WidgetTaskView $task): array => [$task->title, $task->nested, $task->locked], $this->widget()->tasks));
    }

    public function testDoneTodayFollowsTheUsersTimezone(): void
    {
        self::actAsNewUser('Europe/Paris');
        self::freezeAt('2026-10-06 21:30 UTC');
        self::completeTask(self::createTask('Late on Tuesday'));
        self::freezeAt('2026-10-06 22:30 UTC');
        self::completeTask(self::createTask('Just after midnight'));
        self::freezeAt('2026-10-07 08:00 UTC');

        $widget = $this->widget();

        self::assertSame(['2026-10-07', ['Just after midnight']], [$widget->date, self::widgetTitles($widget)]);
    }

    public function testTheWidgetWearsTheUsersTheme(): void
    {
        self::actAsNewUser();
        self::assertNull($this->widget()->theme);

        self::getContainer()->get(ChooseThemeHandler::class)(Theme::of('#1D201B', '#93bb6c'));

        self::assertSame(['background' => '#1d201b', 'accent' => '#93bb6c', 'dark' => true], $this->widget()->theme);
    }

    private function widget(): WidgetView
    {
        return self::getContainer()->get(ListWidgetTasksHandler::class)();
    }

    /**
     * @return list<string>
     */
    private static function widgetTitles(WidgetView $widget): array
    {
        return array_map(static fn (WidgetTaskView $task): string => $task->title, $widget->tasks);
    }
}
