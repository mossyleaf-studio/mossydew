<?php

declare(strict_types=1);

namespace App\Application\Social;

use App\Application\Gamification\CollectSpecies;
use App\Application\Transaction;
use App\Domain\Gamification\Greenhouse\DewGain;
use App\Domain\Gamification\Greenhouse\GreenhouseRepository;
use App\Domain\Identity\User;
use App\Domain\Social\SharedGoalRepository;
use Psr\Clock\ClockInterface;

final readonly class AdvanceGoals
{
    public function __construct(
        private SharedGoalRepository $goals,
        private GreenhouseRepository $greenhouses,
        private CollectSpecies $collectSpecies,
        private ClockInterface $clock,
        private Transaction $transaction,
    ) {
    }

    /**
     * @return list<string>
     */
    public function __invoke(User $user, int $tasks): array
    {
        if ($tasks < 1) {
            return [];
        }
        $now = $this->clock->now();
        $reached = [];
        foreach ($this->goals->lockedOpenOf($user) as $goal) {
            if (!$goal->contribute($user, $tasks, $user->today($now), $now)) {
                continue;
            }
            $reached[] = $goal->title();
            foreach ($goal->members() as $member) {
                ($this->collectSpecies)($member->user(), 1);
                $this->greenhouses->lockedOf($member->user())->receive(new DewGain($goal->target()));
            }
        }
        if ([] !== $reached) {
            $this->transaction->commit();
        }

        return $reached;
    }
}
