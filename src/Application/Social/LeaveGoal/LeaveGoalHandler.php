<?php

declare(strict_types=1);

namespace App\Application\Social\LeaveGoal;

use App\Application\Identity\CurrentUser;
use App\Application\Transaction;
use App\Domain\Social\SharedGoalRepository;
use Symfony\Component\Uid\Ulid;

final readonly class LeaveGoalHandler
{
    public function __construct(
        private SharedGoalRepository $goals,
        private CurrentUser $currentUser,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(Ulid $id): void
    {
        $me = $this->currentUser->get();
        $goal = $this->goals->get($me, $id);
        $goal->leave($me);
        if ($goal->isEmpty()) {
            $this->goals->remove($goal);
        }
        $this->transaction->commit();
    }
}
