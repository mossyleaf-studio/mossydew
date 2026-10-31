<?php

declare(strict_types=1);

namespace App\Application\Mystery\TakeNotes;

use App\Application\AtomicChange;
use App\Application\Identity\CurrentUser;
use App\Application\Transaction;
use App\Domain\Mystery\InvestigationRepository;
use App\Domain\Shared\Exception\NotFound;

final readonly class TakeNotesHandler
{
    public function __construct(
        private CurrentUser $currentUser,
        private InvestigationRepository $investigations,
        private AtomicChange $atomicChange,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(TakeNotes $notes): void
    {
        $user = $this->currentUser->get();
        $this->atomicChange->apply(function () use ($user, $notes): void {
            $investigation = $this->investigations->openLockedOf($user);
            if (null === $investigation || !$investigation->id()->equals($notes->id)) {
                throw new NotFound('investigation', (string) $notes->id);
            }
            $investigation->takeNotes($notes->placements, $notes->crosses);
            $this->transaction->commit();
        });
    }
}
