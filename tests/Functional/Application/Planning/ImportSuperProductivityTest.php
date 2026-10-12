<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application\Planning;

use App\Application\Planning\ImportSuperProductivity\ImportReport;
use App\Application\Planning\ImportSuperProductivity\ImportSuperProductivity;
use App\Application\Planning\ImportSuperProductivity\ImportSuperProductivityHandler;
use App\Application\Planning\ImportSuperProductivity\SuperProductivityBackup;
use App\Domain\Planning\ProjectRepository;
use App\Domain\Planning\Quadrant;
use App\Domain\Planning\Task;
use App\Domain\Planning\TaskRepository;
use App\Domain\Shared\Day;
use App\Tests\Support\ActsAsUser;
use App\Tests\Support\FreezesClock;
use App\Tests\Support\PlansTasks;
use App\Tests\Support\SuperProductivityBackups;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ImportSuperProductivityTest extends KernelTestCase
{
    use ActsAsUser;
    use FreezesClock;
    use PlansTasks;

    protected function setUp(): void
    {
        self::freezeAt('2026-10-06 08:00 UTC');
        self::actAsNewUser('Europe/Paris');
    }

    public function testImportsOpenTasksIntoProjectsAndQuadrants(): void
    {
        $music = self::createProject('musique');

        self::assertEquals(new ImportReport(6, 0, 1), self::import());

        $tasks = self::imported();
        self::assertSame(['sp:call', 'sp:loose', 'sp:mix', 'sp:mix-vocals', 'sp:tax', 'sp:vet'], array_keys($tasks));
        self::assertNull($tasks['sp:vet']->project());
        self::assertSame('2026-10-06', Day::format($tasks['sp:vet']->plannedOn()));
        self::assertSame('2026-10-05', $tasks['sp:vet']->createdAt()->format('Y-m-d'));
        self::assertSame($music->id, (string) $tasks['sp:mix']->project()?->id());
        self::assertSame([Quadrant::DoFirst, '2026-10-08', '2026-10-10', 'Bus compression'], [$tasks['sp:mix']->quadrant(), Day::format($tasks['sp:mix']->plannedOn()), Day::format($tasks['sp:mix']->dueOn()), $tasks['sp:mix']->notes()]);
        self::assertSame($tasks['sp:mix'], $tasks['sp:mix-vocals']->parent());
        self::assertSame($music->id, (string) $tasks['sp:mix-vocals']->project()?->id());
        self::assertSame('Travail', $tasks['sp:tax']->project()?->name());
        self::assertSame('2026-10-07', Day::format($tasks['sp:tax']->plannedOn()));
        self::assertSame([Quadrant::Schedule, Quadrant::Schedule], [$tasks['sp:call']->quadrant(), $tasks['sp:tax']->quadrant()]);
        self::assertLessThan($tasks['sp:tax']->rank(), $tasks['sp:call']->rank());
        self::assertNull($tasks['sp:loose']->quadrant());
        self::assertNull($tasks['sp:loose']->plannedOn());
    }

    public function testImportingTheSameBackupAgainAddsNothing(): void
    {
        self::import();
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        self::assertEquals(new ImportReport(0, 6, 0), self::import());
        self::assertCount(6, self::imported());
        self::assertCount(2, self::getContainer()->get(ProjectRepository::class)->all());
    }

    private static function import(): ImportReport
    {
        return self::getContainer()->get(ImportSuperProductivityHandler::class)(new ImportSuperProductivity(SuperProductivityBackup::read(SuperProductivityBackups::sample())));
    }

    /**
     * @return array<string, Task>
     */
    private static function imported(): array
    {
        $tasks = self::getContainer()->get(TaskRepository::class)->linked('sp:');
        ksort($tasks);

        return $tasks;
    }
}
