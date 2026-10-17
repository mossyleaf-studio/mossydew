<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application\Planning;

use App\Application\Gamification\ShowPlayer\ShowPlayerHandler;
use App\Application\Planning\ChangeParent\ChangeParent;
use App\Application\Planning\ChangeParent\ChangeParentHandler;
use App\Application\Planning\DeleteTask\DeleteTaskHandler;
use App\Application\Planning\EditTask\EditTask;
use App\Application\Planning\EditTask\EditTaskHandler;
use App\Application\Planning\ListProjectTasks\ListProjectTasksHandler;
use App\Application\Planning\ListSubtasks\ListSubtasksHandler;
use App\Application\Planning\ReopenTask\ReopenTaskHandler;
use App\Application\Planning\ShowMatrix\ShowMatrixHandler;
use App\Application\Planning\TaskView;
use App\Domain\Gamification\Reward;
use App\Domain\Planning\Exception\SubtaskCannotHaveSubtasks;
use App\Domain\Planning\Exception\TaskHasOpenSubtasks;
use App\Domain\Planning\Quadrant;
use App\Domain\Planning\Recurrence;
use App\Domain\Planning\RecurrenceUnit;
use App\Domain\Planning\TaskRepository;
use App\Domain\Shared\Exception\NotFound;
use App\Tests\Support\ActsAsUser;
use App\Tests\Support\FreezesClock;
use App\Tests\Support\PlansTasks;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Ulid;

final class SubtasksTest extends KernelTestCase
{
    use ActsAsUser;
    use FreezesClock;
    use PlansTasks;

    protected function setUp(): void
    {
        self::freezeAt('2026-10-06 08:00 UTC');
        self::actAsNewUser();
    }

    public function testSubtasksAreListedInTheOrderTheyWereAdded(): void
    {
        $project = self::createProject('Drawings');
        $drawing = self::createTask('Fox drawing', projectId: $project->id);
        self::freezeAt('2026-10-06 08:01 UTC');
        $sketch = self::addSubtask($drawing, 'Sketch');
        self::freezeAt('2026-10-06 08:02 UTC');
        self::addSubtask($drawing, 'Colouring');
        self::clear();

        $subtasks = self::subtasksOf($drawing);

        self::assertSame(['Sketch', 'Colouring'], self::titles($subtasks));
        self::assertSame([$drawing->id, 'Fox drawing', $project->id], [$sketch->parentId, $sketch->parentTitle, $sketch->projectId]);
        self::assertSame([2, 0], [self::find($drawing)->subtaskCount, self::find($drawing)->subtasksDone]);
    }

    public function testEachSubtaskEarnsItsRewardAndTheLastOneCompletesTheParentWhichEarnsItsOwn(): void
    {
        $drawing = self::createTask('Fox drawing', quadrant: Quadrant::Schedule);
        $sketch = self::addSubtask($drawing, 'Sketch');
        $render = self::addSubtask($drawing, 'Render');
        self::clear();

        $first = self::completeTask($sketch);
        self::assertEquals(new Reward(8), $first->reward);
        self::assertNotNull($first->parent);
        self::assertSame([false, 2, 1], [$first->parent->done, $first->parent->subtaskCount, $first->parent->subtasksDone]);
        self::clear();

        $last = self::completeTask($render);
        self::assertEquals(new Reward(34), $last->reward);
        self::assertNotNull($last->parent);
        self::assertTrue($last->parent->done);
        self::assertSame(42, $last->player->xp);
    }

    public function testAParentWithOpenSubtasksCannotBeCompletedByHand(): void
    {
        $drawing = self::createTask('Fox drawing');
        self::addSubtask($drawing, 'Sketch');
        self::clear();

        $this->expectExceptionObject(new TaskHasOpenSubtasks('Fox drawing'));

        self::completeTask($drawing);
    }

    public function testReopeningASubtaskReopensTheParentAndTakesBothRewardsBack(): void
    {
        $drawing = self::createTask('Fox drawing');
        $sketch = self::addSubtask($drawing, 'Sketch');
        $first = self::completeTask($sketch);
        self::clear();

        self::getContainer()->get(ReopenTaskHandler::class)(Ulid::fromString($sketch->id));
        self::clear();
        self::assertFalse(self::find($drawing)->done);
        self::assertSame([0, 0], [self::getContainer()->get(ShowPlayerHandler::class)()->xp, self::getContainer()->get(ShowPlayerHandler::class)()->dew]);

        $again = self::completeTask($sketch);
        self::assertEquals($first->reward, $again->reward);
        self::assertTrue(self::find($drawing)->done);
    }

    public function testASubtaskCannotHaveSubtasks(): void
    {
        $sketch = self::addSubtask(self::createTask('Fox drawing'), 'Sketch');

        $this->expectExceptionObject(new SubtaskCannotHaveSubtasks('Sketch'));

        self::addSubtask($sketch, 'Pencils');
    }

    public function testMovingTheParentToAProjectMovesItsSubtasks(): void
    {
        $drawing = self::createTask('Fox drawing');
        self::addSubtask($drawing, 'Sketch');
        $project = self::createProject('Drawings');
        self::clear();

        self::getContainer()->get(EditTaskHandler::class)(new EditTask(Ulid::fromString($drawing->id), $drawing->title, null, Ulid::fromString($project->id), null, null));
        self::clear();

        self::assertSame(['Fox drawing', 'Sketch'], self::titles(self::getContainer()->get(ListProjectTasksHandler::class)(Ulid::fromString($project->id))));
    }

    public function testDeletingTheParentDeletesItsSubtasks(): void
    {
        $drawing = self::createTask('Fox drawing');
        $sketch = self::addSubtask($drawing, 'Sketch');
        self::clear();

        self::getContainer()->get(DeleteTaskHandler::class)(Ulid::fromString($drawing->id));
        self::clear();

        self::assertSame([], self::getContainer()->get(ShowMatrixHandler::class)());
        $this->expectException(NotFound::class);
        self::subtasksOf($sketch);
    }

    public function testDeletingASubtaskKeepsTheParent(): void
    {
        $drawing = self::createTask('Fox drawing');
        $sketch = self::addSubtask($drawing, 'Sketch');
        self::addSubtask($drawing, 'Render');
        self::clear();

        self::getContainer()->get(DeleteTaskHandler::class)(Ulid::fromString($sketch->id));
        self::clear();

        self::assertSame(['Render'], self::titles(self::subtasksOf($drawing)));
    }

    public function testCompletingARepeatingParentCreatesTheNextOccurrenceWithItsSubtasks(): void
    {
        $review = self::createTask('Weekly review', planDate: '2026-10-06');
        $inbox = self::addSubtask($review, 'Empty the inbox');
        self::getContainer()->get(EditTaskHandler::class)(new EditTask(Ulid::fromString($review->id), $review->title, null, null, null, new Recurrence(1, RecurrenceUnit::Week)));
        self::clear();

        self::completeTask($inbox);
        self::clear();

        $next = array_values(array_filter(self::getContainer()->get(ShowMatrixHandler::class)(), static fn (TaskView $task): bool => 'Weekly review' === $task->title));
        self::assertCount(1, $next);
        self::assertSame(['2026-10-13', 1, 0], [$next[0]->plannedOn, $next[0]->subtaskCount, $next[0]->subtasksDone]);
        self::assertSame(['Empty the inbox'], self::titles(self::subtasksOf($next[0])));
    }

    public function testAnExistingTaskIsNestedThenPromotedBack(): void
    {
        $project = self::createProject('Drawings');
        $drawing = self::createTask('Fox drawing', projectId: $project->id);
        $sketch = self::createTask('Sketch', planDate: '2026-10-07');
        self::clear();

        $nested = self::changeParent($sketch, $drawing->id);
        self::clear();
        self::assertSame([$drawing->id, $project->id, '2026-10-07'], [$nested->parentId, $nested->projectId, $nested->plannedOn]);
        self::assertSame(['Sketch'], self::titles(self::subtasksOf($drawing)));

        $promoted = self::changeParent($sketch, null);
        self::clear();
        self::assertSame([null, $project->id], [$promoted->parentId, $promoted->projectId]);
        self::assertSame([], self::subtasksOf($drawing));
    }

    public function testATaskCannotBeNestedUnderAnotherUsersTask(): void
    {
        $drawing = self::createTask('Fox drawing');
        self::actAsNewUser();
        $sketch = self::createTask('Sketch');

        $this->expectException(NotFound::class);

        self::changeParent($sketch, $drawing->id);
    }

    public function testAnotherUsersSubtasksAreNotFound(): void
    {
        $drawing = self::createTask('Fox drawing');
        self::actAsNewUser();

        $this->expectException(NotFound::class);

        self::addSubtask($drawing, 'Sketch');
    }

    /**
     * @return list<TaskView>
     */
    private static function subtasksOf(TaskView $task): array
    {
        return self::getContainer()->get(ListSubtasksHandler::class)(Ulid::fromString($task->id));
    }

    private static function changeParent(TaskView $task, ?string $parentId): TaskView
    {
        return self::getContainer()->get(ChangeParentHandler::class)(new ChangeParent(Ulid::fromString($task->id), null === $parentId ? null : Ulid::fromString($parentId)));
    }

    private static function find(TaskView $task): TaskView
    {
        return TaskView::of(self::getContainer()->get(TaskRepository::class)->get(Ulid::fromString($task->id)));
    }

    private static function clear(): void
    {
        self::getContainer()->get(EntityManagerInterface::class)->clear();
    }
}
