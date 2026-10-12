<?php

declare(strict_types=1);

namespace App\Application\Social\CreateInvite;

final readonly class InviteView
{
    public function __construct(
        public string $token,
        public string $expiresAt,
    ) {
    }
}
