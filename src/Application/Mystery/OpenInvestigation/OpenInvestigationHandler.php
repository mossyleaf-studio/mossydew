<?php

declare(strict_types=1);

namespace App\Application\Mystery\OpenInvestigation;

use App\Application\AtomicChange;
use App\Application\Identity\CurrentUser;
use App\Application\Mystery\ShowInvestigation\InvestigationView;
use App\Application\Transaction;
use App\Domain\Mystery\CaseGenerator;
use App\Domain\Mystery\Exception\CaseStillOpen;
use App\Domain\Mystery\Investigation;
use App\Domain\Mystery\InvestigationRepository;
use Psr\Clock\ClockInterface;

final readonly class OpenInvestigationHandler
{
    public function __construct(
        private CurrentUser $currentUser,
        private InvestigationRepository $investigations,
        private CaseGenerator $generator,
        private AtomicChange $atomicChange,
        private Transaction $transaction,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(): InvestigationView
    {
        $user = $this->currentUser->get();
        $investigation = $this->atomicChange->apply(function () use ($user): Investigation {
            if (null !== $this->investigations->openLockedOf($user)) {
                throw new CaseStillOpen();
            }
            $investigation = Investigation::open($user, $this->generator->generate(), $this->clock->now());
            $this->investigations->add($investigation);
            $this->transaction->commit();

            return $investigation;
        });

        return InvestigationView::of($investigation, $this->investigations->solvedCount($user));
    }
}
