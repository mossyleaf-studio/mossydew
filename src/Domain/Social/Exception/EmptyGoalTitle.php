<?php

declare(strict_types=1);

namespace App\Domain\Social\Exception;

final class EmptyGoalTitle extends InvalidGoal
{
    public function __construct()
    {
        parent::__construct('goal.empty_title');
    }
}
