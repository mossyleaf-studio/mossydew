<?php

declare(strict_types=1);

namespace App\Domain\Social;

use App\Domain\Identity\User;
use Symfony\Component\Uid\Ulid;

interface SharedGoalRepository
{
    public function add(SharedGoal $goal): void;

    public function remove(SharedGoal $goal): void;

    public function get(User $member, Ulid $id): SharedGoal;

    /**
     * @return list<SharedGoal>
     */
    public function of(User $member): array;

    /**
     * @return list<SharedGoal>
     */
    public function lockedOpenOf(User $member): array;

    public function reachedBy(User $member): int;
}
