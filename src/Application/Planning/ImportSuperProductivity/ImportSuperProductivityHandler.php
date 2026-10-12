<?php

declare(strict_types=1);

namespace App\Application\Planning\ImportSuperProductivity;

use App\Application\Identity\CurrentUser;
use App\Application\Planning\Today;
use App\Application\Transaction;
use App\Domain\Planning\Project;
use App\Domain\Planning\ProjectColor;
use App\Domain\Planning\ProjectRepository;
use App\Domain\Planning\Quadrant;
use App\Domain\Planning\Task;
use App\Domain\Planning\TaskRepository;
use App\Domain\Shared\Day;

final readonly class ImportSuperProductivityHandler
{
    private const string PREFIX = 'sp:';

    public function __construct(
        private TaskRepository $tasks,
        private ProjectRepository $projects,
        private CurrentUser $currentUser,
        private Today $today,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(ImportSuperProductivity $command): ImportReport
    {
        $owner = $this->currentUser->get();
        $zone = $owner->zone();
        $today = $this->today->date();
        $now = $this->today->now();
        $linked = $this->tasks->linked(self::PREFIX);
        $projects = [];
        $projectCount = count($this->projects->all());
        $created = 0;
        $ranks = [];
        $imported = [];
        $skipped = 0;

        $sorted = $command->backup->tasks;
        usort($sorted, static fn (SuperProductivityTask $a, SuperProductivityTask $b): int => [null !== $a->parentId, $a->order] <=> [null !== $b->parentId, $b->order]);

        foreach ($sorted as $source) {
            if (isset($linked[self::PREFIX.$source->id])) {
                ++$skipped;
                continue;
            }

            $task = Task::create($owner, $source->title, $source->createdAt ?? $now);
            $task->linkTo(self::PREFIX.$source->id);
            $task->describe($source->notes);

            $parent = null === $source->parentId ? null : ($imported[$source->parentId] ?? $linked[self::PREFIX.$source->parentId] ?? null);
            if (null !== $parent && null === $parent->parent()) {
                $task->nestUnder($parent);
            } elseif (null !== $source->projectName) {
                $name = mb_substr(trim($source->projectName), 0, Project::MAX_NAME_LENGTH);
                $key = mb_strtolower($name);
                if (!isset($projects[$key])) {
                    $projects[$key] = $this->projects->named($name);
                }
                if (null === $projects[$key]) {
                    $projects[$key] = Project::create($owner, $name, ProjectColor::cases()[($projectCount + $created) % count(ProjectColor::cases())], $now);
                    $this->projects->add($projects[$key]);
                    ++$created;
                }
                $task->fileUnder($projects[$key]);
            }

            $planned = match (true) {
                null !== $source->plannedDay => Day::of($source->plannedDay),
                null !== $source->plannedAt => Day::at($source->plannedAt, $zone),
                $source->plannedToday => $today,
                default => null,
            };
            if (null !== $planned) {
                $task->planFor($planned);
            }
            $deadline = match (true) {
                null !== $source->deadlineDay => Day::of($source->deadlineDay),
                null !== $source->deadlineAt => Day::at($source->deadlineAt, $zone),
                default => null,
            };
            if (null !== $deadline) {
                $task->dueBy($deadline);
            }

            if ($source->urgent || $source->important) {
                $quadrant = Quadrant::of($source->urgent, $source->important);
                $ranks[$quadrant->value] ??= $this->tasks->nextRankIn($quadrant);
                $task->classify($quadrant, $ranks[$quadrant->value]++);
            }

            $this->tasks->add($task);
            $imported[$source->id] = $task;
        }

        $this->transaction->commit();

        return new ImportReport(count($imported), $skipped, $created);
    }
}
