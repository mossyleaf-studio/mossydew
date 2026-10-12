<?php

declare(strict_types=1);

namespace App\Application\Planning\DeleteTask;

use App\Application\Planning\Injust\InjustJournal;
use App\Application\Transaction;
use App\Domain\Planning\Injust\InjustEventType;
use App\Domain\Planning\TaskRepository;
use Symfony\Component\Uid\Ulid;

final readonly class DeleteTaskHandler
{
    public function __construct(
        private TaskRepository $tasks,
        private Transaction $transaction,
        private InjustJournal $injust,
    ) {
    }

    public function __invoke(Ulid $id): void
    {
        $task = $this->tasks->get($id);
        if (!$task->isDone()) {
            $this->injust->record($task, InjustEventType::Skipped);
        }
        $this->tasks->remove($task);
        $this->transaction->commit();
    }
}
