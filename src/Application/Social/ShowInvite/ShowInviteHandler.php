<?php

declare(strict_types=1);

namespace App\Application\Social\ShowInvite;

use App\Application\Identity\CurrentUser;
use App\Domain\Social\Exception\AlreadyFriends;
use App\Domain\Social\FriendInviteRepository;
use App\Domain\Social\FriendshipRepository;
use Psr\Clock\ClockInterface;

final readonly class ShowInviteHandler
{
    public function __construct(
        private FriendInviteRepository $invites,
        private FriendshipRepository $friendships,
        private CurrentUser $currentUser,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(string $token): InvitationView
    {
        $me = $this->currentUser->get();
        $invite = $this->invites->withSecret($token);
        $invite->check($me, $this->clock->now());
        if (null !== $this->friendships->between($me, $invite->inviter())) {
            throw new AlreadyFriends($invite->inviter()->displayName());
        }

        return new InvitationView($invite->inviter()->displayName(), $invite->expiresAt()->format(\DATE_ATOM));
    }
}
