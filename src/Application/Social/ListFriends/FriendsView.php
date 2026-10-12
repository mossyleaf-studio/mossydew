<?php

declare(strict_types=1);

namespace App\Application\Social\ListFriends;

use App\Application\Social\FriendView;

final readonly class FriendsView
{
    /**
     * @param list<FriendView> $friends
     */
    public function __construct(public array $friends)
    {
    }
}
