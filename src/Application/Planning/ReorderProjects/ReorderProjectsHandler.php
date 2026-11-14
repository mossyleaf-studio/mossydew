<?php

declare(strict_types=1);

namespace App\Application\Planning\ReorderProjects;

use App\Application\Transaction;
use App\Domain\Planning\Project;
use App\Domain\Planning\ProjectRepository;

final readonly class ReorderProjectsHandler
{
    public function __construct(
        private ProjectRepository $projects,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(ReorderProjects $command): void
    {
        $ordered = [];
        foreach ($command->projectIds as $id) {
            $ordered[(string) $id] ??= $this->projects->get($id);
        }
        $others = array_filter(
            $this->projects->all(),
            static fn (Project $project): bool => !isset($ordered[(string) $project->id()]),
        );

        foreach ([...array_values($ordered), ...$others] as $position => $project) {
            $project->moveTo($position);
        }
        $this->transaction->commit();
    }
}
