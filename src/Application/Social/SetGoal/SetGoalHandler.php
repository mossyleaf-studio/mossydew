<?php

declare(strict_types=1);

namespace App\Application\Social\SetGoal;

use App\Application\Identity\CurrentUser;
use App\Application\Planning\Today;
use App\Application\Social\GoalView;
use App\Application\Transaction;
use App\Domain\Social\FriendshipRepository;
use App\Domain\Social\SharedGoal;
use App\Domain\Social\SharedGoalRepository;
use Symfony\Component\Uid\Ulid;

final readonly class SetGoalHandler
{
    public function __construct(
        private SharedGoalRepository $goals,
        private FriendshipRepository $friendships,
        private CurrentUser $currentUser,
        private Today $today,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(SetGoal $command): GoalView
    {
        $me = $this->currentUser->get();
        $ids = array_values(array_unique(array_map(static fn (Ulid $id): string => (string) $id, $command->friendIds)));
        $friends = array_map(fn (string $id) => $this->friendships->with($me, Ulid::fromString($id))->friendOf($me), $ids);
        $goal = SharedGoal::set($me, $command->title, $command->target, $command->endsOn, $friends, $this->today->now());
        $this->goals->add($goal);
        $this->transaction->commit();

        return GoalView::of($goal, $me, $this->today->date());
    }
}
