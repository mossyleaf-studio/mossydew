<?php

declare(strict_types=1);

namespace App\Domain\Social;

use App\Domain\Identity\User;
use Symfony\Component\Uid\Ulid;

interface FriendshipRepository
{
    public function add(Friendship $friendship): void;

    public function remove(Friendship $friendship): void;

    public function between(User $one, User $other): ?Friendship;

    /**
     * @return list<Friendship>
     */
    public function of(User $user): array;

    public function with(User $user, Ulid $friendId): Friendship;
}
