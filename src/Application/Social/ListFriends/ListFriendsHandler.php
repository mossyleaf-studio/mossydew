<?php

declare(strict_types=1);

namespace App\Application\Social\ListFriends;

use App\Application\Identity\CurrentUser;
use App\Application\Social\FriendCard;
use App\Domain\Social\Friendship;
use App\Domain\Social\FriendshipRepository;

final readonly class ListFriendsHandler
{
    public function __construct(
        private FriendshipRepository $friendships,
        private CurrentUser $currentUser,
        private FriendCard $card,
    ) {
    }

    public function __invoke(): FriendsView
    {
        $me = $this->currentUser->get();

        return new FriendsView(array_map(fn (Friendship $friendship) => ($this->card)($friendship, $me), $this->friendships->of($me)));
    }
}
