<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application\Gamification;

use App\Application\Gamification\CollectSpecies;
use App\Application\Gamification\ListAchievements\AchievementView;
use App\Application\Gamification\ListAchievements\ListAchievementsHandler;
use App\Application\Gamification\ListHerbarium\ListHerbariumHandler;
use App\Application\Gamification\MarkAchievementsSeen\MarkAchievementsSeenHandler;
use App\Application\Gamification\PlantMoss\PlantMoss;
use App\Application\Gamification\PlantMoss\PlantMossHandler;
use App\Application\Gamification\PlayerView;
use App\Application\Gamification\ShowPlayer\ShowPlayerHandler;
use App\Domain\Gamification\Herbarium\SpeciesCatalog;
use App\Domain\Gamification\Herbarium\Specimen;
use App\Domain\Identity\User;
use App\Domain\Planning\Quadrant;
use App\Tests\Support\ActsAsUser;
use App\Tests\Support\FreezesClock;
use App\Tests\Support\PlansTasks;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Clock;

final class PlayerUseCasesTest extends KernelTestCase
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

    public function testANewPlayer(): void
    {
        $player = $this->player();

        self::assertSame(['Louis', 0, 1, 0, 150, 0, 0, 0, \count(SpeciesCatalog::all()), 0, []], [$player->displayName, $player->xp, $player->level, $player->levelStartXp, $player->nextLevelXp, $player->streak, $player->bestStreak, $player->speciesCollected, $player->speciesTotal, $player->dew, $player->newAchievements]);
    }

    public function testStreakFadesAfterAMissedDay(): void
    {
        self::completeTask(self::createTask('Vet'));

        self::freezeAt('2026-10-07 08:00 UTC');
        self::assertSame(1, $this->player()->streak);

        self::freezeAt('2026-10-08 08:00 UTC');
        self::assertSame([0, 1], [$this->player()->streak, $this->player()->bestStreak]);
    }

    public function testNewAchievementsAreCelebratedOnce(): void
    {
        self::completeTask(self::createTask('Vet'));
        self::assertSame(['first_drop'], $this->player()->newAchievements);

        self::getContainer()->get(MarkAchievementsSeenHandler::class)();

        self::assertSame([], $this->player()->newAchievements);
    }

    public function testListAchievements(): void
    {
        self::completeTask(self::createTask('Vet'));

        $achievements = self::getContainer()->get(ListAchievementsHandler::class)();

        $unlocked = array_values(array_filter($achievements, static fn (AchievementView $achievement): bool => null !== $achievement->unlockedAt));
        self::assertCount(18, $achievements);
        self::assertSame(['first_drop'], array_map(static fn (AchievementView $achievement): string => $achievement->id, $unlocked));
        self::assertEquals(new \DateTimeImmutable('2026-10-06 08:00 UTC'), new \DateTimeImmutable((string) $unlocked[0]->unlockedAt));
    }

    public function testTheHerbariumListsCollectedSpeciesOutOfTheCatalog(): void
    {
        self::getContainer()->get(CollectSpecies::class)($this->user, 2);
        self::getContainer()->get(EntityManagerInterface::class)->flush();

        $herbarium = self::getContainer()->get(ListHerbariumHandler::class)();

        self::assertSame(\count(SpeciesCatalog::all()), $herbarium->total);
        self::assertCount(2, $herbarium->specimens);
        self::assertNotSame($herbarium->specimens[0]->species, $herbarium->specimens[1]->species);
        self::assertSame('2026-10-06T08:00:00+00:00', $herbarium->specimens[0]->collectedAt);
        self::assertSame([1, 2], [$herbarium->specimens[0]->number, $herbarium->specimens[1]->number]);
        self::assertSame(SpeciesCatalog::get($herbarium->specimens[0]->species)->rarity->value, $herbarium->specimens[0]->rarity);
        self::assertSame(\count(SpeciesCatalog::all()) - 2, array_sum($herbarium->remaining));
        self::assertSame(2, $this->player()->speciesCollected);
    }

    public function testThePlayerShowsTheirDew(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist(Specimen::collect($this->user, SpeciesCatalog::get('buxbaumia-aphylla'), Clock::get()->now()));
        $entityManager->flush();
        self::getContainer()->get(PlantMossHandler::class)(new PlantMoss(1, 'buxbaumia-aphylla'));
        self::completeTask(self::createTask('Vet', quadrant: Quadrant::Schedule));

        self::freezeAt('2026-10-07 20:00 UTC');
        self::assertSame(28, $this->player()->dew);
    }

    private function player(): PlayerView
    {
        return self::getContainer()->get(ShowPlayerHandler::class)();
    }
}
