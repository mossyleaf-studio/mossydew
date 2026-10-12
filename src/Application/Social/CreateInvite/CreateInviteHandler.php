<?php

declare(strict_types=1);

namespace App\Application\Social\CreateInvite;

use App\Application\Identity\CurrentUser;
use App\Application\Transaction;
use App\Domain\Social\FriendInvite;
use App\Domain\Social\FriendInviteRepository;
use Psr\Clock\ClockInterface;
use Random\Randomizer;

final readonly class CreateInviteHandler
{
    public function __construct(
        private FriendInviteRepository $invites,
        private CurrentUser $currentUser,
        private Randomizer $randomizer,
        private ClockInterface $clock,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(): InviteView
    {
        $secret = bin2hex($this->randomizer->getBytes(24));
        $invite = FriendInvite::send($this->currentUser->get(), $secret, $this->clock->now());
        $this->invites->add($invite);
        $this->transaction->commit();

        return new InviteView($secret, $invite->expiresAt()->format(\DATE_ATOM));
    }
}
