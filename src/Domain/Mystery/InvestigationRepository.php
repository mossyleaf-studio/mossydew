<?php

declare(strict_types=1);

namespace App\Domain\Mystery;

use App\Domain\Identity\User;

interface InvestigationRepository
{
    public function add(Investigation $investigation): void;

    public function latestOf(User $owner): ?Investigation;

    public function openLockedOf(User $owner): ?Investigation;

    public function solvedCount(User $owner): int;
}
