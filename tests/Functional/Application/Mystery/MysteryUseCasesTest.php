<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application\Mystery;

use App\Application\Identity\ChooseMysteries\ChooseMysteriesHandler;
use App\Application\Mystery\Accuse\Accuse;
use App\Application\Mystery\Accuse\AccuseHandler;
use App\Application\Mystery\OpenInvestigation\OpenInvestigationHandler;
use App\Application\Mystery\ShowInvestigation\CaseView;
use App\Application\Mystery\ShowInvestigation\InvestigationView;
use App\Application\Mystery\ShowInvestigation\ShowInvestigationHandler;
use App\Application\Mystery\TakeNotes\TakeNotes;
use App\Application\Mystery\TakeNotes\TakeNotesHandler;
use App\Application\Planning\ReopenTask\ReopenTaskHandler;
use App\Domain\Gamification\Achievement\UnlockedAchievement;
use App\Domain\Gamification\Achievement\UnlockedAchievementRepository;
use App\Domain\Identity\User;
use App\Domain\Identity\UserRepository;
use App\Domain\Mystery\Blueprint;
use App\Domain\Mystery\CaseGenerator;
use App\Domain\Mystery\Clue;
use App\Domain\Mystery\Exception\CaseStillOpen;
use App\Domain\Mystery\Exception\InvalidNotes;
use App\Domain\Mystery\Exception\WrongAccusation;
use App\Domain\Mystery\Investigation;
use App\Domain\Mystery\InvestigationRepository;
use App\Domain\Shared\Exception\NotFound;
use App\Tests\Support\ActsAsUser;
use App\Tests\Support\FreezesClock;
use App\Tests\Support\PlansTasks;
use Doctrine\ORM\EntityManagerInterface;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Uid\Ulid;

final class MysteryUseCasesTest extends KernelTestCase
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

    public function testThereIsNoCaseUntilOneIsOpened(): void
    {
        $view = $this->show();

        self::assertNull($view->investigation);
        self::assertSame(0, $view->solved);
    }

    public function testOpeningACaseHidesEveryClueAndSavesTheBoard(): void
    {
        $opened = $this->open();
        self::clear();

        $case = $this->case();

        self::assertEquals($opened, $this->show());
        self::assertGreaterThanOrEqual(6, $case->size);
        self::assertLessThanOrEqual(14, $case->size);
        self::assertSame([$case->size * $case->size, $case->size * $case->size, $case->size], [\count($case->rooms), \count($case->furniture), \count($case->people)]);
        self::assertSame([[], false, null, null], [$case->clues, $case->solved, $case->solution, $case->murderer]);
        self::assertGreaterThan(0, $case->hiddenClues);
        self::assertSame(['placements' => array_fill(0, $case->size, null), 'crosses' => []], $case->notes);
        self::assertContains($case->victim, array_keys($case->people));
        self::assertSame(0, $this->show()->solved);
    }

    public function testACaseCannotBeOpenedWhileAnotherIsOpen(): void
    {
        $this->open();

        $this->expectExceptionObject(new CaseStillOpen());

        $this->open();
    }

    public function testACompletedTaskRevealsTheNextClueWhenMysteriesAreOn(): void
    {
        $this->playMysteries(true);
        $this->open();
        $hidden = $this->case()->hiddenClues;

        $first = self::completeTask(self::createTask('Water the fern'));
        $afterOne = $this->case();
        $second = self::completeTask(self::createTask('Repot the aloe'));
        self::clear();
        $afterTwo = $this->case();

        self::assertSame([1, 1], [$first->cluesRevealed, $second->cluesRevealed]);
        self::assertCount(1, $afterOne->clues);
        self::assertCount(2, $afterTwo->clues);
        self::assertSame($afterOne->clues[0], $afterTwo->clues[0]);
        self::assertSame([$hidden - 1, $hidden - 2], [$afterOne->hiddenClues, $afterTwo->hiddenClues]);
        self::assertEquals(array_map(static fn (Clue $clue): array => $clue->toArray(), $this->entity()->revealedClues()), $afterTwo->clues);
    }

    public function testNothingIsRevealedWhileMysteriesAreOffAndTheCaseIsKept(): void
    {
        $this->open();
        self::assertSame(0, self::completeTask(self::createTask('Water the fern'))->cluesRevealed);

        $this->playMysteries(true);
        self::assertSame(1, self::completeTask(self::createTask('Repot the aloe'))->cluesRevealed);

        $this->playMysteries(false);
        self::assertSame(0, self::completeTask(self::createTask('Dust the shelves'))->cluesRevealed);

        $case = $this->case();
        self::assertFalse($case->solved);
        self::assertCount(1, $case->clues);
    }

    public function testNothingIsRevealedWithoutAnOpenCase(): void
    {
        $this->playMysteries(true);

        self::assertSame(0, self::completeTask(self::createTask('Water the fern'))->cluesRevealed);
        self::assertNull($this->show()->investigation);

        $this->open();
        $this->solve();

        self::assertSame(0, self::completeTask(self::createTask('Repot the aloe'))->cluesRevealed);
        self::assertSame([], $this->case()->clues);
    }

    public function testReopeningATaskHidesNothingAndCompletingItAgainRevealsNothingMore(): void
    {
        $this->playMysteries(true);
        $this->open();
        $task = self::createTask('Water the fern');
        self::assertSame(1, self::completeTask($task)->cluesRevealed);

        self::getContainer()->get(ReopenTaskHandler::class)(Ulid::fromString($task->id));
        self::assertCount(1, $this->case()->clues);

        self::assertSame(0, self::completeTask($task)->cluesRevealed);
        self::assertCount(1, $this->case()->clues);
        self::assertSame(1, self::completeTask(self::createTask('Repot the aloe'))->cluesRevealed);
    }

    public function testASubtaskThatCompletesItsParentRevealsTwoClues(): void
    {
        $this->playMysteries(true);
        $this->open();
        $cleaning = self::createTask('Spring cleaning');
        $windows = self::addSubtask($cleaning, 'Windows');
        self::clear();

        $completion = self::completeTask($windows);

        self::assertSame(2, $completion->cluesRevealed);
        self::assertCount(2, $this->case()->clues);
    }

    public function testNothingIsRevealedPastTheLastClue(): void
    {
        $this->playMysteries(true);
        $this->openWithClues(2);

        $revealed = array_map(static fn (string $title): int => self::completeTask(self::createTask($title))->cluesRevealed, ['Water the fern', 'Repot the aloe', 'Dust the shelves']);

        self::assertSame([1, 1, 0], $revealed);
        $case = $this->case();
        self::assertSame([2, 0], [\count($case->clues), $case->hiddenClues]);
        self::assertSame(array_map(static fn (Clue $clue): array => $clue->toArray(), \array_slice(self::blueprint()->clues, 0, 2)), $case->clues);
    }

    public function testNotesAreSavedWithTheCase(): void
    {
        $this->open();
        $placements = [null, 3, ...array_fill(0, $this->case()->size - 2, null)];

        self::getContainer()->get(TakeNotesHandler::class)(new TakeNotes($this->entity()->id(), $placements, [0, 7]));
        self::clear();

        self::assertSame(['placements' => $placements, 'crosses' => [0, 7]], $this->case()->notes);
    }

    public function testNotesThatDoNotFitTheBoardAreRefused(): void
    {
        $this->open();

        $this->expectExceptionObject(new InvalidNotes());

        self::getContainer()->get(TakeNotesHandler::class)(new TakeNotes($this->entity()->id(), [null], []));
    }

    public function testNotesNeedAnOpenCase(): void
    {
        $case = new Ulid();

        $this->assertUnreachable($case, static fn () => self::getContainer()->get(TakeNotesHandler::class)(new TakeNotes($case, [], [])));
    }

    public function testNotesAimedAtAnEarlierCaseChangeNothing(): void
    {
        $this->open();
        $earlier = $this->entity()->id();
        $this->solve();
        $this->open();
        $size = $this->case()->size;

        $this->assertUnreachable($earlier, static fn () => self::getContainer()->get(TakeNotesHandler::class)(new TakeNotes($earlier, array_fill(0, $size, null), [3])));
        self::clear();

        self::assertSame(['placements' => array_fill(0, $size, null), 'crosses' => []], $this->case()->notes);
    }

    public function testARightAccusationSolvesTheCaseAndUnlocksFirstCase(): void
    {
        $this->open();
        $entity = $this->entity();
        $solution = $entity->solution();
        $murderer = $entity->murderer();

        self::getContainer()->get(AccuseHandler::class)(new Accuse($entity->id(), $solution, $murderer));
        self::clear();

        $view = $this->show();
        $case = $this->case();
        self::assertSame(1, $view->solved);
        self::assertSame([true, $solution, $murderer], [$case->solved, $case->solution, $case->murderer]);
        self::assertSame($solution, $case->notes['placements']);
        self::assertSame(['first_case'], $this->unlocked());
    }

    public function testAWrongAccusationChangesNothing(): void
    {
        $this->open();
        $entity = $this->entity();
        $solution = $entity->solution();
        $swapped = [$solution[1], $solution[0], ...\array_slice($solution, 2)];

        try {
            self::getContainer()->get(AccuseHandler::class)(new Accuse($entity->id(), $swapped, $entity->murderer()));
            self::fail('The accusation should have been refused.');
        } catch (WrongAccusation $wrong) {
            self::assertSame('mystery.wrong_accusation', $wrong->getMessage());
        }
        self::clear();

        $case = $this->case();
        self::assertSame([false, null, null, 0], [$case->solved, $case->solution, $case->murderer, $this->show()->solved]);
        self::assertSame([], $this->unlocked());
    }

    public function testAccusingNeedsAnOpenCase(): void
    {
        $case = new Ulid();

        $this->assertUnreachable($case, static fn () => self::getContainer()->get(AccuseHandler::class)(new Accuse($case, [], 0)));
    }

    public function testAnAccusationAimedAtAnEarlierCaseChangesNothing(): void
    {
        $this->open();
        $earlier = $this->entity();
        $this->solve();
        $this->open();

        $this->assertUnreachable($earlier->id(), static fn () => self::getContainer()->get(AccuseHandler::class)(new Accuse($earlier->id(), $earlier->solution(), $earlier->murderer())));
        self::clear();

        $case = $this->case();
        self::assertSame([false, null, null, 1, ['first_case']], [$case->solved, $case->solution, $case->murderer, $this->show()->solved, $this->unlocked()]);
    }

    public function testASolvedCaseCannotBeAccusedAgain(): void
    {
        $this->open();
        $solved = $this->entity();
        $this->solve();

        $this->assertUnreachable($solved->id(), static fn () => self::getContainer()->get(AccuseHandler::class)(new Accuse($solved->id(), $solved->solution(), $solved->murderer())));
    }

    public function testASolvedCaseTakesNoMoreNotes(): void
    {
        $this->open();
        $solved = $this->entity()->id();
        $this->solve();

        $this->assertUnreachable($solved, static fn () => self::getContainer()->get(TakeNotesHandler::class)(new TakeNotes($solved, [], [])));
    }

    public function testANewCaseOpensOnceTheLastOneIsSolved(): void
    {
        $first = $this->open();
        $this->solve();

        $second = $this->open();

        $view = $this->show();
        self::assertNotSame($first->investigation?->id, $second->investigation?->id);
        self::assertSame([$second->investigation?->id, false, 1], [$view->investigation?->id, $view->investigation->solved ?? null, $view->solved]);
        self::assertSame(1, $second->solved);
    }

    public function testTheTenthSolvedCaseUnlocksTenCases(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $blueprint = self::blueprint();
        for ($solved = 0; $solved < 8; ++$solved) {
            $case = Investigation::open($this->owner(), $blueprint, Clock::get()->now());
            $case->accuse($case->solution(), $case->murderer(), Clock::get()->now());
            $entityManager->persist($case);
        }
        $entityManager->flush();
        self::assertSame([], $this->unlocked());

        $this->open();
        $this->solve();
        self::assertSame(['first_case'], $this->unlocked());

        $this->open();
        $this->solve();
        self::assertEqualsCanonicalizing(['first_case', 'ten_cases'], $this->unlocked());
        self::assertSame(10, $this->show()->solved);
    }

    public function testEachPlayerHasTheirOwnCases(): void
    {
        $this->playMysteries(true);
        $this->open();
        $this->solve();
        $this->open();
        self::completeTask(self::createTask('Water the fern'));
        $mine = $this->case();

        self::actAsNewUser();
        $this->playMysteries(true);

        self::assertNull($this->show()->investigation);
        self::assertSame(0, $this->show()->solved);
        self::assertSame(0, self::completeTask(self::createTask('Repot the aloe'))->cluesRevealed);
        $another = Ulid::fromString($mine->id);
        $takeNotes = static fn () => self::getContainer()->get(TakeNotesHandler::class)(new TakeNotes($another, [], []));
        $this->assertUnreachable($another, $takeNotes);
        $this->open();
        $this->assertUnreachable($another, $takeNotes);

        self::actAs($this->user);
        self::assertEquals($mine, $this->case());
    }

    public function testEveryChangeOfTheCaseWaitsForTheOneBeforeIt(): void
    {
        $this->playMysteries(true);
        $task = self::createTask('Water the fern');

        self::assertTrue($this->locksTheCase(fn () => $this->open()));
        self::assertTrue($this->locksTheCase(fn () => self::getContainer()->get(TakeNotesHandler::class)(new TakeNotes($this->entity()->id(), array_fill(0, $this->case()->size, null), []))));
        self::assertTrue($this->locksTheCase(static fn () => self::completeTask($task)));
        self::assertTrue($this->locksTheCase(fn () => $this->solve()));
        self::assertFalse($this->locksTheCase(fn () => $this->show()));
    }

    private function locksTheCase(\Closure $change): bool
    {
        $queries = self::getContainer()->get('doctrine.debug_data_holder');
        $queries->reset();
        $change();
        $sql = array_column(array_merge([], ...array_values($queries->getData())), 'sql');

        return [] !== preg_grep('/\bFROM investigation\b.*\bFOR UPDATE\b/s', array_filter($sql, is_string(...)));
    }

    private function show(): InvestigationView
    {
        return self::getContainer()->get(ShowInvestigationHandler::class)();
    }

    private function case(): CaseView
    {
        $case = $this->show()->investigation;
        self::assertNotNull($case);

        return $case;
    }

    private function open(): InvestigationView
    {
        return self::getContainer()->get(OpenInvestigationHandler::class)();
    }

    private function solve(): void
    {
        $entity = $this->entity();
        self::getContainer()->get(AccuseHandler::class)(new Accuse($entity->id(), $entity->solution(), $entity->murderer()));
    }

    private function assertUnreachable(Ulid $case, \Closure $change): void
    {
        try {
            $change();
            self::fail('A case that is not the open one was reachable.');
        } catch (NotFound $notFound) {
            self::assertSame(['investigation', (string) $case], [$notFound->parameters()['subject'], $notFound->parameters()['id']]);
        }
    }

    private function entity(): Investigation
    {
        $entity = self::getContainer()->get(InvestigationRepository::class)->latestOf($this->user);
        self::assertNotNull($entity);

        return $entity;
    }

    private function openWithClues(int $count): void
    {
        $blueprint = self::blueprint();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist(Investigation::open(
            $this->owner(),
            new Blueprint($blueprint->floorplan, $blueprint->people, $blueprint->victim, $blueprint->solution, \array_slice($blueprint->clues, 0, $count)),
            Clock::get()->now(),
        ));
        $entityManager->flush();
    }

    private function playMysteries(bool $plays): void
    {
        self::getContainer()->get(ChooseMysteriesHandler::class)($plays);
    }

    private function owner(): User
    {
        return self::getContainer()->get(UserRepository::class)->get($this->user->id());
    }

    private static function blueprint(): Blueprint
    {
        return new CaseGenerator(new Randomizer(new Mt19937(5)))->generate();
    }

    private static function clear(): void
    {
        self::getContainer()->get(EntityManagerInterface::class)->clear();
    }

    /**
     * @return list<string>
     */
    private function unlocked(): array
    {
        return array_map(
            static fn (UnlockedAchievement $achievement): string => $achievement->achievement(),
            self::getContainer()->get(UnlockedAchievementRepository::class)->of($this->user),
        );
    }
}
