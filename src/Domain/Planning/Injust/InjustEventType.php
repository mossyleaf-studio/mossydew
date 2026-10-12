<?php

declare(strict_types=1);

namespace App\Domain\Planning\Injust;

enum InjustEventType: string
{
    case Done = 'done';
    case Reopened = 'reopened';
    case Skipped = 'skipped';
}
