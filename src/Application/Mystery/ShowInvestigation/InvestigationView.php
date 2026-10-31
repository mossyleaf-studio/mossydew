<?php

declare(strict_types=1);

namespace App\Application\Mystery\ShowInvestigation;

use App\Domain\Mystery\Investigation;

final readonly class InvestigationView
{
    private function __construct(
        public ?CaseView $investigation,
        public int $solved,
    ) {
    }

    public static function of(?Investigation $investigation, int $solved): self
    {
        return new self(null === $investigation ? null : CaseView::of($investigation), $solved);
    }
}
