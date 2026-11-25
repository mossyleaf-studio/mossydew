<?php

declare(strict_types=1);

namespace App\Application\Planning\ReceiveInjustFeed;

use App\Domain\Planning\Exception\UnreadableFeed;
use App\Domain\Planning\Injust\InjustEventType;
use App\Domain\Planning\Injust\InjustLink;
use App\Domain\Planning\Task;

final readonly class InjustFeed
{
    private const string FORMAT = 'injust-releases/task-feed';
    private const int VERSION = 1;
    private const int MAX_ITEM_ID = 120 - 7;

    /**
     * @param list<InjustFeedItem>           $open
     * @param array<string, InjustEventType> $closed
     */
    private function __construct(
        public string $instance,
        public int $seq,
        public bool $paused,
        public ?string $epoch,
        public int $appliedThrough,
        public array $open,
        public array $closed,
    ) {
    }

    /**
     * @param array<mixed> $feed
     */
    public static function read(array $feed): self
    {
        [$epoch, $appliedThrough] = self::journal($feed['journal'] ?? null);
        $instance = self::instance($feed['instance'] ?? null);
        if (self::FORMAT !== ($feed['format'] ?? null) || self::VERSION !== ($feed['version'] ?? null)
            || !is_int($feed['seq'] ?? null) || !in_array($feed['state'] ?? null, ['on', 'paused'], true)
            || !is_array($feed['open'] ?? null) || !is_array($feed['closed'] ?? null)) {
            throw new UnreadableFeed();
        }

        return new self($instance, $feed['seq'], 'paused' === $feed['state'], $epoch, $appliedThrough, array_map(self::item(...), array_values($feed['open'])), self::closed($feed['closed']));
    }

    /**
     * @return array{?string, int}
     */
    private static function journal(mixed $journal): array
    {
        $epoch = is_array($journal) ? $journal['epoch'] ?? null : null;
        if (!is_array($journal) || !is_int($journal['appliedThrough'] ?? null) || !(null === $epoch || is_string($epoch))) {
            throw new UnreadableFeed();
        }

        return [$epoch, $journal['appliedThrough']];
    }

    private static function instance(mixed $instance): string
    {
        return is_string($instance) && '' !== $instance && strlen($instance) <= 64 ? $instance : throw new UnreadableFeed();
    }

    /**
     * @param array<mixed> $entries
     *
     * @return array<string, InjustEventType>
     */
    private static function closed(array $entries): array
    {
        $closed = [];
        foreach ($entries as $entry) {
            $id = is_array($entry) ? self::id($entry['id'] ?? null) : throw new UnreadableFeed();
            $closed[$id] = match ($entry['status'] ?? null) {
                'done' => InjustEventType::Done,
                'skipped' => InjustEventType::Skipped,
                default => throw new UnreadableFeed(),
            };
        }

        return $closed;
    }

    private static function item(mixed $item): InjustFeedItem
    {
        if (!is_array($item) || !is_string($item['rev'] ?? null) || strlen($item['rev']) > 16
            || !is_string($item['title'] ?? null) || !is_string($item['block'] ?? null)
            || !is_array($item['tags'] ?? null)) {
            throw new UnreadableFeed();
        }

        return new InjustFeedItem(self::id($item['id'] ?? null), is_string($item['kind'] ?? null) ? $item['kind'] : '', $item['rev'], mb_substr($item['title'], 0, Task::MAX_TITLE_LENGTH), self::due($item['due'] ?? null), self::tags($item['tags']), $item['block']);
    }

    /**
     * @param array<mixed> $tags
     *
     * @return list<string>
     */
    private static function tags(array $tags): array
    {
        $strings = array_values(array_filter($tags, is_string(...)));

        return count($strings) === count($tags) ? $strings : throw new UnreadableFeed();
    }

    private static function due(mixed $due): ?string
    {
        return null === $due || (is_string($due) && 1 === preg_match('/^\d{4}-\d{2}-\d{2}$/', $due)) ? $due : throw new UnreadableFeed();
    }

    private static function id(mixed $id): string
    {
        return is_string($id) && '' !== $id && strlen($id) <= self::MAX_ITEM_ID ? $id : throw new UnreadableFeed();
    }

    public static function externalId(string $item): string
    {
        return InjustLink::PREFIX.$item;
    }
}
