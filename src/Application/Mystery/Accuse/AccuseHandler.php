<?php

declare(strict_types=1);

namespace App\Application\Mystery\Accuse;

use App\Application\AtomicChange;
use App\Application\Gamification\AchievementCheck;
use App\Application\Identity\CurrentUser;
use App\Application\Transaction;
use App\Domain\Mystery\InvestigationRepository;
use App\Domain\Shared\Exception\NotFound;
use Psr\Clock\ClockInterface;

final readonly class AccuseHandler
{
    public function __construct(
        private CurrentUser $currentUser,
        private InvestigationRepository $investigations,
        private AtomicChange $atomicChange,
        private Transaction $transaction,
        private AchievementCheck $achievements,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(Accuse $accusation): void
    {
        $user = $this->currentUser->get();
        $this->atomicChange->apply(function () use ($user, $accusation): void {
            $investigation = $this->investigations->openLockedOf($user);
            if (null === $investigation || !$investigation->id()->equals($accusation->id)) {
                throw new NotFound('investigation', (string) $accusation->id);
            }
            $investigation->accuse($accusation->placements, $accusation->murderer, $this->clock->now());
            $this->transaction->commit();
        });
        ($this->achievements)();
    }
}
