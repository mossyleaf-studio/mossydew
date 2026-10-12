<?php

declare(strict_types=1);

namespace App\Domain\Social\Exception;

final class GoalNeedsFriends extends InvalidGoal
{
    public function __construct()
    {
        parent::__construct('goal.needs_friends');
    }
}
