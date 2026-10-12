<?php

declare(strict_types=1);

namespace App\Application\Planning\ReceiveInjustFeed;

final readonly class ReceiveInjustFeed
{
    public function __construct(public InjustFeed $feed)
    {
    }
}
