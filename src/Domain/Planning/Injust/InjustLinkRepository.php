<?php

declare(strict_types=1);

namespace App\Domain\Planning\Injust;

use App\Domain\Identity\User;

interface InjustLinkRepository
{
    public function add(InjustLink $link): void;

    public function of(User $owner): ?InjustLink;

    public function record(InjustEvent $event): void;

    /**
     * @return list<InjustEvent>
     */
    public function pending(User $owner): array;

    public function acknowledge(User $owner, int $through): void;
}
