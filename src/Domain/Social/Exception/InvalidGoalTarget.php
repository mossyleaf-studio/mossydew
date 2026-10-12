<?php

declare(strict_types=1);

namespace App\Domain\Social\Exception;

final class InvalidGoalTarget extends InvalidGoal
{
    public function __construct()
    {
        parent::__construct('goal.invalid_target');
    }
}
