<?php

declare(strict_types=1);

namespace App\Domain\Planning\Exception;

final class UnreadableBackup extends InvalidTask
{
    public function __construct()
    {
        parent::__construct('task.unreadable_backup');
    }
}
