<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Planning;

use App\Application\Planning\ReceiveInjustFeed\InjustFeedItem;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class InjustFeedItemTest extends TestCase
{
    /**
     * @param list<string> $tags
     */
    #[DataProvider('items')]
    public function testATitleNamesTheReleaseOfAStepOrANeededByStage(string $kind, ?string $due, array $tags, string $title): void
    {
        self::assertSame($title, new InjustFeedItem('id', $kind, 'r1', 'Cover revealed', $due, $tags, '')->title());
    }

    /**
     * @return iterable<string, array{string, ?string, list<string>, string}>
     */
    public static function items(): iterable
    {
        yield 'step' => ['step', '2026-10-09', ['Untitled EP'], 'Cover revealed · Untitled EP'];
        yield 'step without tag' => ['step', null, [' '], 'Cover revealed'];
        yield 'dated stage' => ['stage', '2026-10-12', ['Untitled EP'], 'Cover revealed · Untitled EP'];
        yield 'undated stage' => ['stage', null, ['Studio'], 'Cover revealed'];
        yield 'post' => ['post', '2026-10-08', ['Posts'], 'Cover revealed'];
    }
}
