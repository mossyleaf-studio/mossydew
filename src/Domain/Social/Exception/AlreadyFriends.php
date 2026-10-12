<?php

declare(strict_types=1);

namespace App\Domain\Social\Exception;

final class AlreadyFriends extends InvalidFriendship
{
    public function __construct(string $name)
    {
        parent::__construct('friend.already_friends', ['name' => $name]);
    }
}
