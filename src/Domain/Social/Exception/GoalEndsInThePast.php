<?php

declare(strict_types=1);

namespace App\Domain\Social\Exception;

final class GoalEndsInThePast extends InvalidGoal
{
    public function __construct()
    {
        parent::__construct('goal.ends_in_the_past');
    }
}
