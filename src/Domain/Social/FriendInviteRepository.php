<?php

declare(strict_types=1);

namespace App\Domain\Social;

interface FriendInviteRepository
{
    public function add(FriendInvite $invite): void;

    public function withSecret(string $secret): FriendInvite;
}
