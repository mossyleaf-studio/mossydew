<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application\Planning;

use App\Application\Gamification\PlantMoss\PlantMoss;
use App\Application\Gamification\PlantMoss\PlantMossHandler;
use App\Application\Gamification\ShowPlayer\ShowPlayerHandler;
use App\Application\Planning\ReopenTask\ReopenTaskHandler;
use App\Domain\Gamification\Greenhouse\DewGain;
use App\Domain\Gamification\Greenhouse\GreenhouseRepository;
use App\Domain\Gamification\Herbarium\SpeciesCatalog;
use App\Domain\Gamification\Herbarium\Specimen;
use App\Domain\Gamification\PlayerRepository;
use App\Domain\Gamification\Reward;
use App\Domain\Identity\User;
use App\Domain\Planning\Quadrant;
use App\Tests\Support\ActsAsUser;
use App\Tests\Support\FreezesClock;
use App\Tests\Support\PlansTasks;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Uid\Ulid;

final class CompleteTaskTest extends KernelTestCase
{
    use ActsAsUser;
    use FreezesClock;
    use PlansTasks;

    private User $user;

    protected function setUp(): void
    {
        self::freezeAt('2026-10-06 08:00 UTC');
        $this->user = self::actAsNewUser();
    }

    public function testCompletingATaskEarnsItsReward(): void
    {
        $completion = self::completeTask(self::createTask('Plan the holidays', quadrant: Quadrant::Schedule));

        self::assertTrue($completion->task->done);
        self::assertEquals(new Reward(26), $completion->reward);
        self::assertNull($completion->leveledUpTo);
        self::assertSame([], $completion->newSpecies);
        self::assertSame([26, 1, 1, 0], [$completion->player->xp, $completion->player->level, $completion->player->streak, $completion->player->speciesCollected]);
    }

    public function testReopeningTakesTheRewardBackAndCompletingAgainEarnsItAgain(): void
    {
        $task = self::createTask('Vet', quadrant: Quadrant::DoFirst);
        self::completeTask($task);
        self::getContainer()->get(ReopenTaskHandler::class)(Ulid::fromString($task->id));
        self::assertSame([0, 0], [self::getContainer()->get(ShowPlayerHandler::class)()->xp, self::getContainer()->get(ShowPlayerHandler::class)()->dew]);

        $again = self::completeTask($task);

        self::assertEquals(new Reward(21), $again->reward);
        self::assertSame([21, 6], [$again->player->xp, $again->player->dew]);
    }

    public function testATaskRewardedBeforeRewardsWereKeptKeepsItsRewardWhenReopened(): void
    {
        $task = self::createTask('Vet', quadrant: Quadrant::DoFirst);
        self::completeTask($task);
        self::getContainer()->get(EntityManagerInterface::class)->getConnection()->executeStatement('UPDATE task SET reward_xp = NULL, reward_dew = NULL');
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        self::getContainer()->get(ReopenTaskHandler::class)(Ulid::fromString($task->id));

        $again = self::completeTask($task);

        self::assertNull($again->reward);
        self::assertSame([21, 6], [$again->player->xp, $again->player->dew]);
    }

    public function testReopeningBelowALevelAndCrossingItAgainCollectsNothingMore(): void
    {
        self::getContainer()->get(PlayerRepository::class)->of($this->user)->earn(new Reward(140));
        self::getContainer()->get(EntityManagerInterface::class)->flush();
        $task = self::createTask('Vet', quadrant: Quadrant::Schedule);
        self::assertSame(2, self::completeTask($task)->leveledUpTo);
        self::getContainer()->get(ReopenTaskHandler::class)(Ulid::fromString($task->id));
        self::assertSame(1, self::getContainer()->get(ShowPlayerHandler::class)()->level);

        $again = self::completeTask($task);

        self::assertNull($again->leveledUpTo);
        self::assertSame([], $again->newSpecies);
        self::assertSame([2, 1], [$again->player->level, $again->player->speciesCollected]);
    }

    public function testConsecutiveDaysBuildAStreakThatMultipliesXp(): void
    {
        foreach (['2026-10-04', '2026-10-05', '2026-10-06'] as $day) {
            self::freezeAt($day.' 08:00 UTC');
            $completion = self::completeTask(self::createTask('Water the fern '.$day, quadrant: Quadrant::DoFirst));
        }

        self::assertEquals(new Reward(23), $completion->reward);
        self::assertSame([3, 3], [$completion->player->streak, $completion->player->bestStreak]);
    }

    public function testLevellingUpIsReported(): void
    {
        self::getContainer()->get(PlayerRepository::class)->of($this->user)->earn(new Reward(140));
        self::getContainer()->get(EntityManagerInterface::class)->flush();

        $completion = self::completeTask(self::createTask('Vet', quadrant: Quadrant::Schedule));

        self::assertSame(2, $completion->leveledUpTo);
        self::assertSame([2, 150, 300], [$completion->player->level, $completion->player->levelStartXp, $completion->player->nextLevelXp]);
    }

    public function testCrossingALevelCollectsOneNewSpecies(): void
    {
        self::getContainer()->get(PlayerRepository::class)->of($this->user)->earn(new Reward(140));
        self::getContainer()->get(EntityManagerInterface::class)->flush();

        $completion = self::completeTask(self::createTask('Vet', quadrant: Quadrant::Schedule));

        self::assertCount(1, $completion->newSpecies);
        self::assertSame(1, $completion->player->speciesCollected);
        self::assertSame(SpeciesCatalog::get($completion->newSpecies[0]->slug)->rarity->value, $completion->newSpecies[0]->rarity);
    }

    public function testLevelsReachedWithoutCrossingThemHereCollectNothing(): void
    {
        self::getContainer()->get(PlayerRepository::class)->of($this->user)->earn(new Reward(400));
        self::getContainer()->get(EntityManagerInterface::class)->flush();

        $completion = self::completeTask(self::createTask('Vet', quadrant: Quadrant::Schedule));

        self::assertNull($completion->leveledUpTo);
        self::assertSame([], $completion->newSpecies);
        self::assertSame(0, $completion->player->speciesCollected);
    }

    public function testFirstCompletionUnlocksFirstDrop(): void
    {
        $completion = self::completeTask(self::createTask('Vet'));

        self::assertSame(['first_drop'], $completion->player->newAchievements);
        self::assertSame(['first_drop'], self::getContainer()->get(ShowPlayerHandler::class)()->newAchievements);
    }

    #[DataProvider('dewByQuadrant')]
    public function testCompletingATaskBringsDewByQuadrant(?Quadrant $quadrant, int $dew): void
    {
        $completion = self::completeTask(self::createTask('Vet', quadrant: $quadrant));

        self::assertEquals(new DewGain($dew), $completion->dew);
        self::assertSame($dew, $completion->player->dew);
    }

    /** @return iterable<array{?Quadrant, int}> */
    public static function dewByQuadrant(): iterable
    {
        yield 'plant' => [Quadrant::Schedule, 12];
        yield 'water' => [Quadrant::DoFirst, 6];
        yield 'trim' => [Quadrant::Delegate, 3];
        yield 'compost' => [Quadrant::Eliminate, 1];
        yield 'unsorted' => [null, 1];
    }

    public function testABiggerGreenhouseGrowsEveryTaskRewardButCompost(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        foreach (['polytrichum-commune', 'sphagnum-palustre'] as $slug) {
            $entityManager->persist(Specimen::collect($this->user, SpeciesCatalog::get($slug), Clock::get()->now()));
        }
        $entityManager->flush();
        $plant = self::getContainer()->get(PlantMossHandler::class);
        $plant(new PlantMoss(1, 'polytrichum-commune'));
        $plant(new PlantMoss(2, 'sphagnum-palustre'));

        self::assertSame(
            [26, 13, 10, 1],
            array_map(
                static fn (Quadrant $quadrant): ?int => self::completeTask(self::createTask($quadrant->value, quadrant: $quadrant))->dew?->amount,
                [Quadrant::Schedule, Quadrant::DoFirst, Quadrant::Delegate, Quadrant::Eliminate],
            ),
        );
    }

    public function testDewSpentBeforeReopeningLeavesTheBalanceShort(): void
    {
        $task = self::createTask('Vet', quadrant: Quadrant::DoFirst);
        self::completeTask($task);
        self::getContainer()->get(GreenhouseRepository::class)->of($this->user)->giveBack(4);
        self::getContainer()->get(EntityManagerInterface::class)->flush();

        self::getContainer()->get(ReopenTaskHandler::class)(Ulid::fromString($task->id));

        $greenhouse = self::getContainer()->get(GreenhouseRepository::class)->of($this->user);
        self::assertSame([-4, -4], [$greenhouse->dew(), $greenhouse->dewGathered()]);
    }

    public function testASubtaskAndTheParentItCompletesEachBringDew(): void
    {
        $parent = self::createTask('Fox drawing', quadrant: Quadrant::Schedule);
        $subtask = self::addSubtask($parent, 'Sketch');

        $completion = self::completeTask($subtask);

        self::assertEquals(new DewGain(13), $completion->dew);
        self::assertSame(13, $completion->player->dew);
    }

    public function testPottedMossesWaterPlantAndWaterTasksAndMistTrimTasks(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist(Specimen::collect($this->user, SpeciesCatalog::get('polytrichum-commune'), Clock::get()->now()));
        $entityManager->flush();
        self::getContainer()->get(PlantMossHandler::class)(new PlantMoss(1, 'polytrichum-commune'));

        self::assertEquals(new DewGain(12, 4), self::completeTask(self::createTask('Plant', quadrant: Quadrant::Schedule))->dew);
        self::assertEquals(new DewGain(6, 2), self::completeTask(self::createTask('Water', quadrant: Quadrant::DoFirst))->dew);
        self::assertEquals(new DewGain(3, mist: 2), self::completeTask(self::createTask('Trim', quadrant: Quadrant::Delegate))->dew);
        self::assertEquals(new DewGain(1), self::completeTask(self::createTask('Compost', quadrant: Quadrant::Eliminate))->dew);
        self::assertEquals(new DewGain(1), self::completeTask(self::createTask('Unsorted'))->dew);

        $greenhouse = self::getContainer()->get(GreenhouseRepository::class)->of($this->user);
        self::assertSame([31, 31], [$greenhouse->dew(), $greenhouse->dewGathered()]);
    }
}
