<?php

declare(strict_types=1);

namespace App\Domain\Social\Exception;

final class InviteAlreadyUsed extends InvalidFriendship
{
    public function __construct()
    {
        parent::__construct('friend.invite_used');
    }
}
