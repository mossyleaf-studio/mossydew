<?php

declare(strict_types=1);

namespace App\Application\Planning\ShowInjustLink;

final readonly class InjustLinkView
{
    public function __construct(
        public ?string $tokenCreatedAt,
        public ?string $feedAt,
        public int $waiting,
    ) {
    }
}
