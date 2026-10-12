<?php

declare(strict_types=1);

namespace App\Application\Social\ListFriends;

use App\Application\Social\FriendView;
use App\Application\Social\GoalView;

final readonly class FriendsView
{
    /**
     * @param list<FriendView> $friends
     * @param list<GoalView>   $goals
     */
    public function __construct(
        public array $friends,
        public array $goals,
    ) {
    }
}
