<?php

declare(strict_types=1);

namespace App\Application\Planning\ShowInjustJournal;

final readonly class InjustJournalView
{
    public string $format;
    public int $version;
    public string $plugin;
    public string $sp;

    /**
     * @param array{instance: string, seq: int, at: string}|null                              $feedSeen
     * @param list<array{seq: int, instance: string, item: string, type: string, at: string}> $events
     */
    public function __construct(
        public string $epoch,
        public string $writtenAt,
        public ?array $feedSeen,
        public array $events,
    ) {
        $this->format = 'injust-releases/sp-journal';
        $this->version = 1;
        $this->plugin = 'mossydew';
        $this->sp = 'mossydew';
    }
}
