<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Social;

use App\Domain\Identity\User;
use App\Domain\Shared\Day;
use App\Domain\Social\Exception\EmptyGoalTitle;
use App\Domain\Social\Exception\GoalEndsInThePast;
use App\Domain\Social\Exception\GoalNeedsFriends;
use App\Domain\Social\Exception\InvalidGoalTarget;
use App\Domain\Social\SharedGoal;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SharedGoalTest extends TestCase
{
    private const string NOW = '2026-10-06 08:00 UTC';

    public function testMembersAddUpUntilTheTargetIsReachedOnce(): void
    {
        [$louis, $mira, $noa] = [self::user('louis'), self::user('mira'), self::user('noa')];
        $goal = SharedGoal::set($louis, '  Clear   the backlog ', 3, Day::of('2026-10-31'), [$mira], self::now());
        self::assertSame('Clear the backlog', $goal->title());

        self::assertFalse($goal->contribute($noa, 5, Day::of('2026-10-06'), self::now()), 'only members count');
        self::assertFalse($goal->contribute($louis, 2, Day::of('2026-10-06'), self::now()));
        self::assertTrue($goal->contribute($mira, 1, Day::of('2026-10-31'), self::now()));
        self::assertSame([3, 2, 1], [$goal->total(), $goal->memberOf($louis)?->contributed(), $goal->memberOf($mira)?->contributed()]);
        self::assertFalse($goal->contribute($mira, 1, Day::of('2026-10-31'), self::now()), 'a reached goal stops counting');
    }

    public function testAMemberWithdrawsTasksFromAnActiveGoalOnly(): void
    {
        [$louis, $mira] = [self::user('louis'), self::user('mira')];
        $goal = SharedGoal::set($louis, 'Sprint', 5, Day::of('2026-10-31'), [$mira], self::now());
        $goal->contribute($louis, 2, Day::of('2026-10-06'), self::now());

        $goal->withdraw($louis, 1, Day::of('2026-11-01'));
        self::assertSame(2, $goal->total(), 'an ended goal keeps its count');
        $goal->withdraw($louis, 3, Day::of('2026-10-06'));
        self::assertSame(0, $goal->total());
    }

    public function testAGoalPastItsEndStopsCounting(): void
    {
        $louis = self::user('louis');
        $goal = SharedGoal::set($louis, 'Sprint', 5, Day::of('2026-10-06'), [self::user('mira')], self::now());

        self::assertFalse($goal->contribute($louis, 1, Day::of('2026-10-07'), self::now()));
        self::assertFalse($goal->isActiveOn(Day::of('2026-10-07')));
    }

    public function testTheLastMemberLeavingEmptiesTheGoal(): void
    {
        [$louis, $mira] = [self::user('louis'), self::user('mira')];
        $goal = SharedGoal::set($louis, 'Sprint', 5, Day::of('2026-10-31'), [$mira], self::now());

        $goal->leave($louis);
        self::assertFalse($goal->isEmpty());
        $goal->leave($mira);
        self::assertTrue($goal->isEmpty());
    }

    /**
     * @param class-string<\Throwable> $exception
     */
    #[DataProvider('invalidGoals')]
    public function testRefusesAnInvalidGoal(string $title, int $target, string $endsOn, bool $withFriend, string $exception): void
    {
        $this->expectException($exception);

        SharedGoal::set(self::user('louis'), $title, $target, Day::of($endsOn), $withFriend ? [self::user('mira')] : [], self::now());
    }

    /** @return iterable<array{string, int, string, bool, class-string<\Throwable>}> */
    public static function invalidGoals(): iterable
    {
        yield 'blank title' => [' ', 5, '2026-10-31', true, EmptyGoalTitle::class];
        yield 'no task' => ['Sprint', 0, '2026-10-31', true, InvalidGoalTarget::class];
        yield 'too many tasks' => ['Sprint', 1001, '2026-10-31', true, InvalidGoalTarget::class];
        yield 'alone' => ['Sprint', 5, '2026-10-31', false, GoalNeedsFriends::class];
        yield 'ended yesterday' => ['Sprint', 5, '2026-10-05', true, GoalEndsInThePast::class];
    }

    private static function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable(self::NOW);
    }

    private static function user(string $name): User
    {
        return User::join($name, "$name@mossydew.test", ucfirst($name), 'Europe/Paris', self::now());
    }
}
