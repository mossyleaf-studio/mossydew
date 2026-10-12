<?php

declare(strict_types=1);

namespace App\Application\Planning\Injust;

use App\Domain\Planning\Injust\InjustEventType;
use App\Domain\Planning\Injust\InjustLink;
use App\Domain\Planning\Injust\InjustLinkRepository;
use App\Domain\Planning\Task;
use Psr\Clock\ClockInterface;

final readonly class InjustJournal
{
    public function __construct(
        private InjustLinkRepository $links,
        private ClockInterface $clock,
    ) {
    }

    public function record(Task $task, InjustEventType $type): void
    {
        $item = InjustLink::itemOf($task);
        $link = null === $item ? null : $this->links->of($task->owner());
        if (null !== $item && null !== $link) {
            $this->links->record($link->record($item, $type, $this->clock->now()));
        }
    }
}
