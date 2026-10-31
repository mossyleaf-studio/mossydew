<?php

declare(strict_types=1);

namespace App\Application\Mystery\ShowInvestigation;

use App\Application\Identity\CurrentUser;
use App\Domain\Mystery\InvestigationRepository;

final readonly class ShowInvestigationHandler
{
    public function __construct(
        private CurrentUser $currentUser,
        private InvestigationRepository $investigations,
    ) {
    }

    public function __invoke(): InvestigationView
    {
        $user = $this->currentUser->get();

        return InvestigationView::of($this->investigations->latestOf($user), $this->investigations->solvedCount($user));
    }
}
