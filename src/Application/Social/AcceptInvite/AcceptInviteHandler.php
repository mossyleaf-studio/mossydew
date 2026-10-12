<?php

declare(strict_types=1);

namespace App\Application\Social\AcceptInvite;

use App\Application\Identity\CurrentUser;
use App\Application\Social\FriendCard;
use App\Application\Social\FriendView;
use App\Application\Transaction;
use App\Domain\Social\Exception\AlreadyFriends;
use App\Domain\Social\FriendInviteRepository;
use App\Domain\Social\Friendship;
use App\Domain\Social\FriendshipRepository;
use Psr\Clock\ClockInterface;

final readonly class AcceptInviteHandler
{
    public function __construct(
        private FriendInviteRepository $invites,
        private FriendshipRepository $friendships,
        private CurrentUser $currentUser,
        private ClockInterface $clock,
        private Transaction $transaction,
        private FriendCard $card,
    ) {
    }

    public function __invoke(string $token): FriendView
    {
        $me = $this->currentUser->get();
        $now = $this->clock->now();
        $invite = $this->invites->withSecret($token);
        $invite->check($me, $now);
        if (null !== $this->friendships->between($me, $invite->inviter())) {
            throw new AlreadyFriends($invite->inviter()->displayName());
        }
        $invite->accept($me, $now);
        $friendship = Friendship::between($me, $invite->inviter(), $now);
        $this->friendships->add($friendship);
        $this->transaction->commit();

        return ($this->card)($friendship, $me);
    }
}
