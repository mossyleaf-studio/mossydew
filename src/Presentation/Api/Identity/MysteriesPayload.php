<?php

declare(strict_types=1);

namespace App\Presentation\Api\Identity;

final readonly class MysteriesPayload
{
    public function __construct(
        public bool $enabled = false,
    ) {
    }
}
