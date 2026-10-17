<?php

declare(strict_types=1);

namespace App\Application\Gamification;

use App\Domain\Gamification\Greenhouse\DewGain;
use App\Domain\Gamification\Greenhouse\DewPolicy;
use App\Domain\Gamification\Greenhouse\GreenhouseRepository;
use App\Domain\Identity\User;
use App\Domain\Planning\Task;

final readonly class GatherTaskDew
{
    public function __construct(
        private GreenhouseRepository $greenhouses,
        private DewPolicy $policy,
    ) {
    }

    /**
     * @param list<Task> $rewarded
     */
    public function __invoke(User $owner, array $rewarded): ?DewGain
    {
        if ([] === $rewarded) {
            return null;
        }
        $greenhouse = $this->greenhouses->lockedOf($owner);
        $gain = new DewGain(0);
        foreach ($rewarded as $task) {
            $dew = $this->policy->dewFor($task, $greenhouse);
            $task->creditDew($dew->amount);
            $gain = $gain->plus($dew);
        }
        $greenhouse->receive($gain);

        return $gain;
    }
}
