<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Gamification\Achievement;

use App\Domain\Gamification\Achievement\AchievementRule;
use App\Domain\Gamification\Achievement\PlayerStats;
use App\Domain\Gamification\Achievement\Rule\CommonGround;
use App\Domain\Gamification\Achievement\Rule\FieldTrip;
use App\Domain\Gamification\Achievement\Rule\FirstDrop;
use App\Domain\Gamification\Achievement\Rule\FiveHundredTasks;
use App\Domain\Gamification\Achievement\Rule\GrowingGlasshouse;
use App\Domain\Gamification\Achievement\Rule\HundredTasks;
use App\Domain\Gamification\Achievement\Rule\LevelFive;
use App\Domain\Gamification\Achievement\Rule\LevelTen;
use App\Domain\Gamification\Achievement\Rule\MatrixSorter;
use App\Domain\Gamification\Achievement\Rule\MonthStreak;
use App\Domain\Gamification\Achievement\Rule\MorningDew;
use App\Domain\Gamification\Achievement\Rule\PatientGardener;
use App\Domain\Gamification\Achievement\Rule\TenTasks;
use App\Domain\Gamification\Achievement\Rule\TenWaterings;
use App\Domain\Gamification\Achievement\Rule\ThreeDayStreak;
use App\Domain\Gamification\Achievement\Rule\WeekStreak;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AchievementRuleTest extends TestCase
{
    /**
     * @param \Closure(int): PlayerStats $stats
     */
    #[DataProvider('rules')]
    public function testUnlocksAtItsThreshold(AchievementRule $rule, string $id, \Closure $stats, int $threshold): void
    {
        self::assertSame($id, $rule->id());
        self::assertFalse($rule->isMetBy($stats($threshold - 1)));
        self::assertTrue($rule->isMetBy($stats($threshold)));
        self::assertTrue($rule->isMetBy($stats($threshold + 1)));
    }

    /** @return iterable<array{AchievementRule, string, \Closure(int): PlayerStats, int}> */
    public static function rules(): iterable
    {
        $completed = static fn (int $count): PlayerStats => new PlayerStats(tasksCompleted: $count);

        yield 'first drop' => [new FirstDrop(), 'first_drop', $completed, 1];
        yield 'ten tasks' => [new TenTasks(), 'ten_tasks', $completed, 10];
        yield 'hundred tasks' => [new HundredTasks(), 'hundred_tasks', $completed, 100];
        yield 'five hundred tasks' => [new FiveHundredTasks(), 'five_hundred_tasks', $completed, 500];
        yield 'ten waterings' => [new TenWaterings(), 'water_10', static fn (int $count): PlayerStats => new PlayerStats(doFirstCompleted: $count), 10];
        yield 'patient gardener' => [new PatientGardener(), 'plant_25', static fn (int $count): PlayerStats => new PlayerStats(scheduleCompleted: $count), 25];
        yield 'matrix sorter' => [new MatrixSorter(), 'matrix_sorter', static fn (int $count): PlayerStats => new PlayerStats(tasksClassified: $count), 20];
        yield 'three day streak' => [new ThreeDayStreak(), 'streak_3', static fn (int $days): PlayerStats => new PlayerStats(bestStreak: $days), 3];
        yield 'common ground' => [new CommonGround(), 'common_ground', static fn (int $count): PlayerStats => new PlayerStats(goalsReached: $count), 1];
        yield 'week streak' => [new WeekStreak(), 'streak_7', static fn (int $days): PlayerStats => new PlayerStats(bestStreak: $days), 7];
        yield 'month streak' => [new MonthStreak(), 'streak_30', static fn (int $days): PlayerStats => new PlayerStats(bestStreak: $days), 30];
        yield 'level five' => [new LevelFive(), 'level_5', static fn (int $level): PlayerStats => new PlayerStats(level: $level), 5];
        yield 'level ten' => [new LevelTen(), 'level_10', static fn (int $level): PlayerStats => new PlayerStats(level: $level), 10];
        yield 'field trip' => [new FieldTrip(), 'field_trip', static fn (int $count): PlayerStats => new PlayerStats(expeditions: $count), 1];
        yield 'morning dew' => [new MorningDew(), 'dew_1000', static fn (int $dew): PlayerStats => new PlayerStats(dewGathered: $dew), 1_000];
        yield 'growing glasshouse' => [new GrowingGlasshouse(), 'glasshouse_5', static fn (int $level): PlayerStats => new PlayerStats(glasshouseLevel: $level), 5];
    }

    public function testCompletedTasksDoNotCountForOtherRules(): void
    {
        $stats = new PlayerStats(tasksCompleted: 1_000);

        self::assertFalse(new TenWaterings()->isMetBy($stats));
        self::assertFalse(new WeekStreak()->isMetBy($stats));
        self::assertFalse(new LevelFive()->isMetBy($stats));
        self::assertFalse(new MorningDew()->isMetBy($stats));
    }

    public function testANewGreenhouseUnlocksNothing(): void
    {
        $stats = new PlayerStats();

        self::assertFalse(new FieldTrip()->isMetBy($stats));
        self::assertFalse(new MorningDew()->isMetBy($stats));
        self::assertFalse(new GrowingGlasshouse()->isMetBy($stats));
    }
}
