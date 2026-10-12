<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Gamification;

use App\Application\Gamification\PlayerStatsLedger;
use App\Domain\Gamification\Achievement\PlayerStats;
use App\Domain\Gamification\Player;
use App\Domain\Planning\Quadrant;
use Doctrine\DBAL\Connection;

final readonly class DoctrinePlayerStatsLedger implements PlayerStatsLedger
{
    public function __construct(private Connection $connection)
    {
    }

    public function statsOf(Player $player): PlayerStats
    {
        $owner = $player->owner()->id()->toRfc4122();
        $tasks = $this->connection->fetchAssociative(
            'SELECT
                COUNT(*) FILTER (WHERE rewarded_at IS NOT NULL) AS completed,
                COUNT(*) FILTER (WHERE rewarded_at IS NOT NULL AND quadrant = :doFirst) AS do_first,
                COUNT(*) FILTER (WHERE rewarded_at IS NOT NULL AND quadrant = :schedule) AS schedule,
                COUNT(*) FILTER (WHERE quadrant IS NOT NULL) AS classified
             FROM task WHERE owner_id = :owner',
            ['owner' => $owner, 'doFirst' => Quadrant::DoFirst->value, 'schedule' => Quadrant::Schedule->value],
        );
        $greenhouse = $this->connection->fetchAssociative(
            'SELECT dew_gathered, expeditions, facilities_glasshouse AS glasshouse FROM greenhouse WHERE owner_id = :owner',
            ['owner' => $owner],
        );

        $goals = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM shared_goal g JOIN goal_member m ON m.goal_id = g.id WHERE m.user_id = :owner AND g.reached_at IS NOT NULL',
            ['owner' => $owner],
        );

        return new PlayerStats(
            tasksCompleted: self::int($tasks['completed'] ?? 0),
            doFirstCompleted: self::int($tasks['do_first'] ?? 0),
            scheduleCompleted: self::int($tasks['schedule'] ?? 0),
            tasksClassified: self::int($tasks['classified'] ?? 0),
            bestStreak: $player->streak()->best,
            level: $player->level(),
            dewGathered: self::int($greenhouse['dew_gathered'] ?? 0),
            expeditions: self::int($greenhouse['expeditions'] ?? 0),
            glasshouseLevel: self::int($greenhouse['glasshouse'] ?? 1),
            goalsReached: self::int($goals),
        );
    }

    private static function int(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
