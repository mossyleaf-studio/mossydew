<?php

declare(strict_types=1);

namespace App\Application\Planning\ReceiveInjustFeed;

use App\Application\Identity\CurrentUser;
use App\Application\Planning\CompleteTask\CompleteTaskHandler;
use App\Application\Transaction;
use App\Domain\Identity\User;
use App\Domain\Planning\Injust\InjustEvent;
use App\Domain\Planning\Injust\InjustEventType;
use App\Domain\Planning\Injust\InjustLink;
use App\Domain\Planning\Injust\InjustLinkRepository;
use App\Domain\Planning\Project;
use App\Domain\Planning\ProjectColor;
use App\Domain\Planning\ProjectRepository;
use App\Domain\Planning\Task;
use App\Domain\Planning\TaskRepository;
use App\Domain\Shared\Day;
use App\Domain\Shared\Exception\DomainException;
use Psr\Clock\ClockInterface;

final readonly class ReceiveInjustFeedHandler
{
    private const string PROJECT = 'Injust';
    private const int WINDOW_DAYS = 14;

    public function __construct(
        private InjustLinkRepository $links,
        private TaskRepository $tasks,
        private ProjectRepository $projects,
        private CurrentUser $currentUser,
        private ClockInterface $clock,
        private Transaction $transaction,
        private CompleteTaskHandler $completeTask,
    ) {
    }

    public function __invoke(ReceiveInjustFeed $command): void
    {
        $feed = $command->feed;
        $owner = $this->currentUser->get();
        $now = $this->clock->now();
        $link = $this->links->of($owner);
        if (null === $link) {
            $link = InjustLink::open($owner);
            $this->links->add($link);
        }
        $link->receive($feed->instance, $feed->seq, $now);
        $this->links->acknowledge($owner, $link->acknowledged($feed->epoch, $feed->appliedThrough));
        if ($feed->paused) {
            $this->transaction->commit();

            return;
        }

        $waiting = array_flip(array_map(static fn (InjustEvent $event): string => $event->item(), $this->links->pending($owner)));
        $linked = $this->tasks->linked(InjustLink::PREFIX);
        $adopted = $this->ungroup($linked);
        $limit = (string) Day::format($owner->today($now)->modify(\sprintf('+%d days', self::WINDOW_DAYS)));
        $project = null;
        $seen = [];
        foreach ($feed->open as $item) {
            if (!$item->dueBy($limit)) {
                continue;
            }
            $seen[$item->id] = true;
            $externalId = InjustFeed::externalId($item->id);
            $task = $linked[$externalId] ?? null;
            if (isset($waiting[$item->id])) {
                continue;
            }
            if (null === $task) {
                $project ??= $this->projects->named(self::PROJECT) ?? $this->createProject($owner, $now);
                $task = Task::create($owner, $item->title(), $now);
                $task->fileUnder($project);
                $this->tasks->add($task);
                $this->update($task, $item);
            } elseif (isset($adopted[$externalId]) || $task->externalRevision() !== $item->revision) {
                $task->rename($item->title());
                $this->update($task, $item);
            }
            if ($task->isDone()) {
                $task->reopen();
            }
        }

        $toComplete = [];
        foreach ($linked as $task) {
            $item = (string) InjustLink::itemOf($task);
            $closed = $feed->closed[$item] ?? null;
            if (isset($seen[$item]) || isset($waiting[$item]) || $task->isDone()) {
                continue;
            }
            if (InjustEventType::Done === $closed) {
                $toComplete[] = $task->id();
            } else {
                $this->tasks->remove($task);
            }
        }
        $this->transaction->commit();

        foreach ($toComplete as $id) {
            try {
                ($this->completeTask)($id);
            } catch (DomainException) {
                continue;
            }
        }
    }

    /**
     * @param array<string, Task> $linked
     *
     * @return array<string, true>
     */
    private function ungroup(array $linked): array
    {
        $adopted = [];
        foreach ($linked as $externalId => $task) {
            if (str_starts_with((string) $task->parent()?->externalId(), InjustLink::GROUP)) {
                $task->promote();
                $adopted[$externalId] = true;
            }
        }
        foreach ($this->tasks->linked(InjustLink::GROUP) as $group) {
            if (!$group->isDone() && 0 === $group->subtaskCount()) {
                $this->tasks->remove($group);
            }
        }

        return $adopted;
    }

    private function update(Task $task, InjustFeedItem $item): void
    {
        $task->linkTo(InjustFeed::externalId($item->id), $item->revision);
        $task->describe($item->block);
        if (null === $item->due) {
            $task->clearDeadline();
        } else {
            $task->dueBy(Day::of($item->due));
        }
    }

    private function createProject(User $owner, \DateTimeImmutable $now): Project
    {
        $project = Project::create($owner, self::PROJECT, ProjectColor::Berry, $now, $this->projects->nextPosition());
        $this->projects->add($project);

        return $project;
    }
}
