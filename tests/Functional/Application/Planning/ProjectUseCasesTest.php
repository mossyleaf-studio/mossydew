<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application\Planning;

use App\Application\Planning\DeleteProject\DeleteProjectHandler;
use App\Application\Planning\EditProject\EditProject;
use App\Application\Planning\EditProject\EditProjectHandler;
use App\Application\Planning\ListInboxTasks\ListInboxTasksHandler;
use App\Application\Planning\ListProjects\ListProjectsHandler;
use App\Application\Planning\ProjectView;
use App\Application\Planning\ReorderProjects\ReorderProjects;
use App\Application\Planning\ReorderProjects\ReorderProjectsHandler;
use App\Domain\Planning\Exception\DuplicateProjectName;
use App\Domain\Planning\Exception\EmptyProjectName;
use App\Domain\Planning\ProjectColor;
use App\Domain\Shared\Exception\NotFound;
use App\Tests\Support\ActsAsUser;
use App\Tests\Support\FreezesClock;
use App\Tests\Support\PlansTasks;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Ulid;

final class ProjectUseCasesTest extends KernelTestCase
{
    use ActsAsUser;
    use FreezesClock;
    use PlansTasks;

    protected function setUp(): void
    {
        self::freezeAt('2026-10-06 08:00 UTC');
        self::actAsNewUser();
    }

    public function testCreateProject(): void
    {
        $project = self::createProject('  Home ', ProjectColor::Berry);

        self::assertSame(['Home', 'berry', 0], [$project->name, $project->color, $project->openTasks]);
        self::assertTrue(Ulid::isValid($project->id));
    }

    public function testNamesAreUniquePerUserIgnoringCase(): void
    {
        self::createProject('Home');

        $this->expectExceptionObject(new DuplicateProjectName('HOME'));

        self::createProject(' HOME ');
    }

    public function testAnotherUserMayReuseAName(): void
    {
        self::createProject('Home');
        self::actAsNewUser();

        self::assertSame('Home', self::createProject('Home')->name);
    }

    public function testNameIsRequired(): void
    {
        $this->expectExceptionObject(new EmptyProjectName());

        self::createProject(' ');
    }

    public function testEditProject(): void
    {
        $project = self::createProject('Home');

        $edited = $this->edit($project, 'HOME sweet home', ProjectColor::Lichen);
        self::assertSame(['HOME sweet home', 'lichen'], [$edited->name, $edited->color]);

        $recased = $this->edit($project, 'home sweet home', ProjectColor::Lichen);
        self::assertSame('home sweet home', $recased->name);
    }

    public function testCannotRenameToAnotherProjectsName(): void
    {
        self::createProject('Home');
        $work = self::createProject('Work');

        $this->expectExceptionObject(new DuplicateProjectName('home'));

        $this->edit($work, 'home', ProjectColor::Fjord);
    }

    public function testListsProjectsInTheOwnersOrderWithTheirOpenTaskCount(): void
    {
        $work = self::createProject('Work');
        $home = self::createProject('Home');
        self::createTask('Laundry', projectId: $home->id);
        self::createTask('Dishes', projectId: $home->id);
        self::completeTask(self::createTask('Vacuum', projectId: $home->id));
        self::createTask('Inbox thought');

        $projects = self::getContainer()->get(ListProjectsHandler::class)();

        self::assertSame([['Work', 0], ['Home', 2]], array_map(static fn (ProjectView $project): array => [$project->name, $project->openTasks], $projects), 'DoctrineTaskQueries::openCountByProject() keys counts by the RFC 4122 form of the project id, ListProjectsHandler looks them up by its base 32 form.');
        self::assertSame($work->id, $projects[0]->id);
    }

    public function testReorderingProjectsChangesTheList(): void
    {
        $home = self::createProject('Home');
        $work = self::createProject('Work');
        $music = self::createProject('Music');

        $this->reorder($music, $home, $work);

        self::assertSame(['Music', 'Home', 'Work'], $this->names());
    }

    public function testProjectsLeftOutKeepTheirOrderAfterTheListedOnes(): void
    {
        self::createProject('Home');
        self::createProject('Work');
        $music = self::createProject('Music');
        $garden = self::createProject('Garden');

        $this->reorder($garden, $music);

        self::assertSame(['Garden', 'Music', 'Home', 'Work'], $this->names());
    }

    public function testAnotherUsersProjectCannotBeReordered(): void
    {
        $secret = self::createProject('Secret');
        self::actAsNewUser();
        $home = self::createProject('Home');

        $this->expectException(NotFound::class);

        $this->reorder($home, $secret);
    }

    public function testANewProjectGoesLast(): void
    {
        $home = self::createProject('Home');
        $work = self::createProject('Work');
        $this->reorder($work, $home);
        $garden = self::createProject('Garden');
        self::getContainer()->get(DeleteProjectHandler::class)(Ulid::fromString($garden->id));
        self::createProject('Attic');

        self::assertSame(['Work', 'Home', 'Attic'], $this->names());
    }

    private function reorder(ProjectView ...$projects): void
    {
        self::getContainer()->get(ReorderProjectsHandler::class)(new ReorderProjects(array_values(array_map(static fn (ProjectView $project): Ulid => Ulid::fromString($project->id), $projects))));
        self::getContainer()->get(EntityManagerInterface::class)->clear();
    }

    /**
     * @return list<string>
     */
    private function names(): array
    {
        return array_map(static fn (ProjectView $project): string => $project->name, self::getContainer()->get(ListProjectsHandler::class)());
    }

    public function testDeletingAProjectMovesItsTasksToTheInbox(): void
    {
        $home = self::createProject('Home');
        self::createTask('Laundry', projectId: $home->id);
        self::createTask('Inbox thought');

        self::getContainer()->get(DeleteProjectHandler::class)(Ulid::fromString($home->id));
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        self::assertSame([], self::getContainer()->get(ListProjectsHandler::class)());
        self::assertEqualsCanonicalizing(['Laundry', 'Inbox thought'], self::titles(self::getContainer()->get(ListInboxTasksHandler::class)()));
    }

    private function edit(ProjectView $project, string $name, ProjectColor $color): ProjectView
    {
        return self::getContainer()->get(EditProjectHandler::class)(new EditProject(Ulid::fromString($project->id), $name, $color));
    }
}
