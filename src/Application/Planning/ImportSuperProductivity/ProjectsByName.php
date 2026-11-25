<?php

declare(strict_types=1);

namespace App\Application\Planning\ImportSuperProductivity;

use App\Domain\Identity\User;
use App\Domain\Planning\Project;
use App\Domain\Planning\ProjectColor;
use App\Domain\Planning\ProjectRepository;

final class ProjectsByName
{
    public private(set) int $created = 0;
    /** @var array<string, ?Project> */
    private array $named = [];
    private readonly int $count;
    private readonly int $position;

    public function __construct(private readonly ProjectRepository $projects, private readonly User $owner, private readonly \DateTimeImmutable $now)
    {
        $this->count = count($projects->all());
        $this->position = $projects->nextPosition();
    }

    public function named(string $projectName): Project
    {
        $name = mb_substr(trim($projectName), 0, Project::MAX_NAME_LENGTH);
        $key = mb_strtolower($name);
        if (!isset($this->named[$key])) {
            $this->named[$key] = $this->projects->named($name);
        }
        if (null === $this->named[$key]) {
            $this->named[$key] = Project::create($this->owner, $name, ProjectColor::cases()[($this->count + $this->created) % count(ProjectColor::cases())], $this->now, $this->position + $this->created);
            $this->projects->add($this->named[$key]);
            ++$this->created;
        }

        return $this->named[$key];
    }
}
