<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Planning;

use App\Application\Planning\ImportSuperProductivity\SuperProductivityBackup;
use App\Application\Planning\ImportSuperProductivity\SuperProductivityTask;
use App\Domain\Planning\Exception\UnreadableBackup;
use App\Tests\Support\SuperProductivityBackups;
use PHPUnit\Framework\TestCase;

final class SuperProductivityBackupTest extends TestCase
{
    public function testReadsOpenTasksWithTheirProjectAndMatrixTags(): void
    {
        $tasks = self::byId(SuperProductivityBackup::read(SuperProductivityBackups::sample()));

        self::assertSame(['vet', 'mix', 'mix-vocals', 'tax', 'loose', 'call'], array_keys($tasks));
        self::assertNull($tasks['vet']->projectName);
        self::assertTrue($tasks['vet']->plannedToday);
        self::assertSame(['Musique', true, true, '2026-10-08', '2026-10-10', 'Bus compression'], [$tasks['mix']->projectName, $tasks['mix']->urgent, $tasks['mix']->important, $tasks['mix']->plannedDay, $tasks['mix']->deadlineDay, $tasks['mix']->notes]);
        self::assertSame(['Musique', 'mix'], [$tasks['mix-vocals']->projectName, $tasks['mix-vocals']->parentId]);
        self::assertSame([false, true, 1], [$tasks['tax']->urgent, $tasks['tax']->important, $tasks['tax']->order]);
        self::assertSame(0, $tasks['call']->order);
        self::assertNull($tasks['loose']->plannedDay);
    }

    public function testReadsOlderBackupsWrappedInData(): void
    {
        self::assertCount(6, SuperProductivityBackup::read(['data' => SuperProductivityBackups::sample()])->tasks);
    }

    public function testRefusesAFileWithoutTasks(): void
    {
        $this->expectException(UnreadableBackup::class);

        SuperProductivityBackup::read(['project' => []]);
    }

    /**
     * @return array<string, SuperProductivityTask>
     */
    private static function byId(SuperProductivityBackup $backup): array
    {
        return array_column(array_map(static fn (SuperProductivityTask $task): array => ['id' => $task->id, 'task' => $task], $backup->tasks), 'task', 'id');
    }
}
