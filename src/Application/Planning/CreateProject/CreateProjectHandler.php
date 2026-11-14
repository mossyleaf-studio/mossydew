<?php

declare(strict_types=1);

namespace App\Application\Planning\CreateProject;

use App\Application\Identity\CurrentUser;
use App\Application\Planning\ProjectView;
use App\Application\Transaction;
use App\Domain\Planning\Exception\DuplicateProjectName;
use App\Domain\Planning\Project;
use App\Domain\Planning\ProjectRepository;
use Psr\Clock\ClockInterface;

final readonly class CreateProjectHandler
{
    public function __construct(
        private ProjectRepository $projects,
        private CurrentUser $currentUser,
        private Transaction $transaction,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(CreateProject $command): ProjectView
    {
        if (null !== $this->projects->named($command->name)) {
            throw new DuplicateProjectName(trim($command->name));
        }

        $project = Project::create($this->currentUser->get(), $command->name, $command->color, $this->clock->now(), $this->projects->nextPosition());
        $this->projects->add($project);
        $this->transaction->commit();

        return ProjectView::of($project);
    }
}
