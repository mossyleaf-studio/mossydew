<?php

declare(strict_types=1);

namespace App\Application\Planning\ReopenTask;

use App\Application\AtomicChange;
use App\Application\Identity\CurrentUser;
use App\Application\Planning\Injust\InjustJournal;
use App\Application\Planning\TaskView;
use App\Application\Planning\Today;
use App\Application\Transaction;
use App\Domain\Gamification\Greenhouse\GreenhouseRepository;
use App\Domain\Gamification\PlayerRepository;
use App\Domain\Gamification\Reward;
use App\Domain\Planning\Injust\InjustEventType;
use App\Domain\Planning\TaskRepository;
use App\Domain\Social\SharedGoalRepository;
use Symfony\Component\Uid\Ulid;

final readonly class ReopenTaskHandler
{
    public function __construct(
        private TaskRepository $tasks,
        private PlayerRepository $players,
        private GreenhouseRepository $greenhouses,
        private SharedGoalRepository $goals,
        private CurrentUser $currentUser,
        private Today $today,
        private Transaction $transaction,
        private AtomicChange $atomicChange,
        private InjustJournal $injust,
    ) {
    }

    public function __invoke(Ulid $id): TaskView
    {
        $task = $this->tasks->get($id);
        $xp = 0;
        $dew = 0;
        $forfeited = 0;
        foreach ($task->reopen() as $reopened) {
            $earned = $reopened->rewardXp();
            if (null === $earned) {
                continue;
            }
            $xp += $earned;
            $dew += $reopened->rewardDew() ?? 0;
            ++$forfeited;
            $reopened->forfeitReward();
        }
        $this->injust->record($task, InjustEventType::Reopened);

        $user = $this->currentUser->get();
        if ($forfeited > 0) {
            $this->players->of($user)->forfeit(new Reward($xp));
        }
        $this->atomicChange->apply(function () use ($user, $dew, $forfeited): void {
            if ($forfeited > 0) {
                $this->greenhouses->lockedOf($user)->giveBack($dew);
                foreach ($this->goals->lockedOpenOf($user) as $goal) {
                    $goal->withdraw($user, $forfeited, $this->today->date());
                }
            }
            $this->transaction->commit();
        });

        return TaskView::of($task);
    }
}
