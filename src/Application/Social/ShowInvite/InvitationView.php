<?php

declare(strict_types=1);

namespace App\Application\Social\ShowInvite;

final readonly class InvitationView
{
    public function __construct(
        public string $inviter,
        public string $expiresAt,
    ) {
    }
}
