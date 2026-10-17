<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application\Planning;

use App\Application\Planning\ClassifyTask\ClassifyTask;
use App\Application\Planning\ClassifyTask\ClassifyTaskHandler;
use App\Application\Planning\ReorderQuadrant\ReorderQuadrant;
use App\Application\Planning\ReorderQuadrant\ReorderQuadrantHandler;
use App\Application\Planning\ShowMatrix\ShowMatrixHandler;
use App\Application\Planning\TaskView;
use App\Domain\Planning\Exception\TaskNotOpen;
use App\Domain\Planning\Quadrant;
use App\Tests\Support\ActsAsUser;
use App\Tests\Support\FreezesClock;
use App\Tests\Support\PlansTasks;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Ulid;

final class MatrixTest extends KernelTestCase
{
    use ActsAsUser;
    use FreezesClock;
    use PlansTasks;

    protected function setUp(): void
    {
        self::freezeAt('2026-10-06 08:00 UTC');
        self::actAsNewUser();
    }

    public function testClassifyingPutsTheTaskLastInItsQuadrant(): void
    {
        self::createTask('Plant', quadrant: Quadrant::Schedule);
        $task = self::createTask('Thought');

        $classified = $this->classify($task, Quadrant::Schedule);
        self::assertSame(['schedule', 1], [$classified->quadrant, $classified->rank]);

        $again = $this->classify($task, Quadrant::Schedule);
        self::assertSame(1, $again->rank);

        $unsorted = $this->classify($task, null);
        self::assertSame([null, 0], [$unsorted->quadrant, $unsorted->rank]);
    }

    public function testClassifyingAParentSortsItsUnsortedSubtasksAfterIt(): void
    {
        self::createTask('Plant', quadrant: Quadrant::Schedule);
        $parent = self::createTask('Fox drawing');
        self::addSubtask($parent, 'Sketch');
        $ink = self::addSubtask($parent, 'Ink');
        $this->classify($ink, Quadrant::Eliminate);

        $this->classify($parent, Quadrant::Schedule);

        $matrix = self::getContainer()->get(ShowMatrixHandler::class)();
        self::assertEqualsCanonicalizing(
            [['Plant', 'schedule', 0], ['Fox drawing', 'schedule', 1], ['Sketch', 'schedule', 2], ['Ink', 'eliminate', 0]],
            array_map(static fn (TaskView $task): array => [$task->title, $task->quadrant, $task->rank], $matrix),
        );
    }

    public function testReorderingMovesTasksInAndRanksTheOthersAfter(): void
    {
        self::createTask('A', quadrant: Quadrant::DoFirst);
        self::createTask('B', quadrant: Quadrant::DoFirst);
        $c = self::createTask('C', quadrant: Quadrant::DoFirst);
        $moved = self::createTask('Moved', quadrant: Quadrant::Eliminate);
        $unsorted = self::createTask('Unsorted');

        $this->reorder(Quadrant::DoFirst, $c, $moved, $unsorted);

        $matrix = self::getContainer()->get(ShowMatrixHandler::class)();
        self::assertSame(
            [['C', 'do_first', 0], ['Moved', 'do_first', 1], ['Unsorted', 'do_first', 2], ['A', 'do_first', 3], ['B', 'do_first', 4]],
            array_map(static fn (TaskView $task): array => [$task->title, $task->quadrant, $task->rank], $matrix),
        );
    }

    public function testDoneTasksCannotBeReordered(): void
    {
        $open = self::createTask('Open', quadrant: Quadrant::DoFirst);
        $done = self::createTask('Done', quadrant: Quadrant::DoFirst);
        self::completeTask($done);

        $this->expectExceptionObject(new TaskNotOpen('Done'));

        $this->reorder(Quadrant::DoFirst, $done, $open);
    }

    private function classify(TaskView $task, ?Quadrant $quadrant): TaskView
    {
        return self::getContainer()->get(ClassifyTaskHandler::class)(new ClassifyTask(Ulid::fromString($task->id), $quadrant));
    }

    private function reorder(Quadrant $quadrant, TaskView ...$tasks): void
    {
        self::getContainer()->get(ReorderQuadrantHandler::class)(new ReorderQuadrant($quadrant, array_values(array_map(static fn (TaskView $task): Ulid => Ulid::fromString($task->id), $tasks))));
    }
}
