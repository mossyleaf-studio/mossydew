<?php

declare(strict_types=1);

namespace App\Application\Planning\ShowInjustJournal;

use App\Application\Identity\CurrentUser;
use App\Domain\Planning\Injust\InjustEvent;
use App\Domain\Planning\Injust\InjustLink;
use App\Domain\Planning\Injust\InjustLinkRepository;
use Psr\Clock\ClockInterface;

final readonly class ShowInjustJournalHandler
{
    private const string INSTANT = 'Y-m-d\TH:i:s.v\Z';

    public function __construct(
        private InjustLinkRepository $links,
        private CurrentUser $currentUser,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(): InjustJournalView
    {
        $owner = $this->currentUser->get();
        $link = $this->links->of($owner);
        $instance = $link?->instance();
        $feedSeq = $link?->feedSeq();
        $feedAt = $link?->feedAt();

        return new InjustJournalView(
            InjustLink::epochOf($owner),
            self::instant($this->clock->now()),
            null === $instance || null === $feedSeq || null === $feedAt ? null : ['instance' => $instance, 'seq' => $feedSeq, 'at' => self::instant($feedAt)],
            null === $instance ? [] : array_map(static fn (InjustEvent $event): array => [
                'seq' => $event->seq(),
                'instance' => $instance,
                'item' => $event->item(),
                'type' => $event->type()->value,
                'at' => self::instant($event->at()),
            ], $this->links->pending($owner)),
        );
    }

    private static function instant(\DateTimeImmutable $instant): string
    {
        return $instant->setTimezone(new \DateTimeZone('UTC'))->format(self::INSTANT);
    }
}
