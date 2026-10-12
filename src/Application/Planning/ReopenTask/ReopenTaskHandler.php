<?php

declare(strict_types=1);

namespace App\Application\Planning\ReopenTask;

use App\Application\Planning\Injust\InjustJournal;
use App\Application\Planning\TaskView;
use App\Application\Transaction;
use App\Domain\Planning\Injust\InjustEventType;
use App\Domain\Planning\TaskRepository;
use Symfony\Component\Uid\Ulid;

final readonly class ReopenTaskHandler
{
    public function __construct(
        private TaskRepository $tasks,
        private Transaction $transaction,
        private InjustJournal $injust,
    ) {
    }

    public function __invoke(Ulid $id): TaskView
    {
        $task = $this->tasks->get($id);
        $task->reopen();
        $this->injust->record($task, InjustEventType::Reopened);
        $this->transaction->commit();

        return TaskView::of($task);
    }
}
