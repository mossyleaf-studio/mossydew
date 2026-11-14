<?php

declare(strict_types=1);

namespace App\Application\Planning\ReceiveInjustFeed;

final readonly class InjustFeedItem
{
    private const string STEP = 'step';
    private const string STAGE = 'stage';

    /**
     * @param list<string> $tags
     */
    public function __construct(
        public string $id,
        private string $kind,
        public string $revision,
        private string $title,
        public ?string $due,
        private array $tags,
        public string $block,
    ) {
    }

    public function dueBy(string $day): bool
    {
        return null === $this->due || $this->due <= $day;
    }

    public function title(): string
    {
        $release = trim($this->tags[0] ?? '');
        $ofRelease = self::STEP === $this->kind || (self::STAGE === $this->kind && null !== $this->due);

        return $ofRelease && '' !== $release ? $this->title.' · '.$release : $this->title;
    }
}
