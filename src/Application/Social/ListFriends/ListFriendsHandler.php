<?php

declare(strict_types=1);

namespace App\Application\Social\ListFriends;

use App\Application\Identity\CurrentUser;
use App\Application\Planning\Today;
use App\Application\Social\FriendCard;
use App\Application\Social\GoalView;
use App\Domain\Social\Friendship;
use App\Domain\Social\FriendshipRepository;
use App\Domain\Social\SharedGoal;
use App\Domain\Social\SharedGoalRepository;

final readonly class ListFriendsHandler
{
    public function __construct(
        private FriendshipRepository $friendships,
        private CurrentUser $currentUser,
        private FriendCard $card,
        private SharedGoalRepository $goals,
        private Today $today,
    ) {
    }

    public function __invoke(): FriendsView
    {
        $me = $this->currentUser->get();

        $today = $this->today->date();

        return new FriendsView(
            array_map(fn (Friendship $friendship) => ($this->card)($friendship, $me), $this->friendships->of($me)),
            array_map(static fn (SharedGoal $goal): GoalView => GoalView::of($goal, $me, $today), $this->goals->of($me)),
        );
    }
}
