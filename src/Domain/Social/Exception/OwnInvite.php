<?php

declare(strict_types=1);

namespace App\Domain\Social\Exception;

final class OwnInvite extends InvalidFriendship
{
    public function __construct()
    {
        parent::__construct('friend.own_invite');
    }
}
