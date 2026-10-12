<?php

declare(strict_types=1);

namespace App\Application\Planning\ImportSuperProductivity;

final readonly class ImportReport
{
    public function __construct(
        public int $imported,
        public int $skipped,
        public int $projects,
    ) {
    }
}
