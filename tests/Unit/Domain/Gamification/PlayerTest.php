<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Gamification;

use App\Domain\Gamification\Player;
use App\Domain\Gamification\Reward;
use App\Domain\Identity\User;
use App\Domain\Shared\Day;
use PHPUnit\Framework\TestCase;

final class PlayerTest extends TestCase
{
    public function testStartsAtLevelOneWithNothing(): void
    {
        $player = $this->player();

        self::assertSame([0, 1, 0], [$player->xp(), $player->level(), $player->streak()->current]);
    }

    public function testEarnReturnsTheNewLevelOnlyWhenLevellingUp(): void
    {
        $player = $this->player();

        self::assertNull($player->earn(new Reward(60)));
        self::assertSame(2, $player->earn(new Reward(90)));
        self::assertNull($player->earn(new Reward(10)));
        self::assertSame(5, $player->earn(new Reward(500)));
        self::assertSame([660, 5], [$player->xp(), $player->level()]);
    }

    public function testForfeitingDropsTheLevelButCrossingItAgainIsNoNewLevel(): void
    {
        $player = $this->player();
        self::assertSame(2, $player->earn(new Reward(160)));

        $player->forfeit(new Reward(20));
        self::assertSame([140, 1, 2], [$player->xp(), $player->level(), $player->highestLevel()]);
        self::assertNull($player->earn(new Reward(20)));
        self::assertSame(3, $player->earn(new Reward(150)));
        $player->forfeit(new Reward(1000));
        self::assertSame(0, $player->xp());
    }

    public function testRecordsActivityInTheStreak(): void
    {
        $player = $this->player();

        $player->recordActivity(Day::of('2026-10-05'));
        $player->recordActivity(Day::of('2026-10-06'));

        self::assertSame(2, $player->streak()->current);
    }

    private function player(): Player
    {
        return Player::start(User::join('account', 'louis@example.com', 'Louis', 'Europe/Paris', new \DateTimeImmutable('2026-10-06 09:00')));
    }
}
