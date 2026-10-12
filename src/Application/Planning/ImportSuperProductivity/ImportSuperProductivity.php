<?php

declare(strict_types=1);

namespace App\Application\Planning\ImportSuperProductivity;

final readonly class ImportSuperProductivity
{
    public function __construct(public SuperProductivityBackup $backup)
    {
    }
}
