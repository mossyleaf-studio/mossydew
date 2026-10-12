<?php

declare(strict_types=1);

namespace App\Application\Social\RemoveFriend;

use App\Application\Identity\CurrentUser;
use App\Application\Transaction;
use App\Domain\Social\FriendshipRepository;
use Symfony\Component\Uid\Ulid;

final readonly class RemoveFriendHandler
{
    public function __construct(
        private FriendshipRepository $friendships,
        private CurrentUser $currentUser,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(Ulid $friendId): void
    {
        $this->friendships->remove($this->friendships->with($this->currentUser->get(), $friendId));
        $this->transaction->commit();
    }
}
