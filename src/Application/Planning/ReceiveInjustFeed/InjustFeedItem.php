<?php

declare(strict_types=1);

namespace App\Application\Planning\ReceiveInjustFeed;

final readonly class InjustFeedItem
{
    /**
     * @param list<string> $tags
     */
    public function __construct(
        public string $id,
        public string $revision,
        public string $title,
        public ?string $due,
        public array $tags,
        public string $block,
    ) {
    }

    public function group(): ?string
    {
        $tag = trim($this->tags[0] ?? '');

        return '' === $tag ? null : $tag;
    }
}
