<?php

declare(strict_types=1);

namespace App\Application\Social;

final readonly class FriendView
{
    public function __construct(
        public string $id,
        public string $displayName,
        public int $level,
        public int $xp,
        public int $streak,
        public int $bestStreak,
        public int $speciesCollected,
        public string $since,
    ) {
    }
}
