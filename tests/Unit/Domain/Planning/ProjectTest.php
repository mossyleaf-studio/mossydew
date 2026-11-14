<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Planning;

use App\Domain\Identity\User;
use App\Domain\Planning\Exception\EmptyProjectName;
use App\Domain\Planning\Project;
use App\Domain\Planning\ProjectColor;
use PHPUnit\Framework\TestCase;

final class ProjectTest extends TestCase
{
    public function testNameIsTrimmedAndCut(): void
    {
        $project = $this->project('  Home  ');
        self::assertSame('Home', $project->name());

        $project->rename(str_repeat('a', Project::MAX_NAME_LENGTH + 5));
        self::assertSame(Project::MAX_NAME_LENGTH, mb_strlen($project->name()));
    }

    public function testNameIsRequired(): void
    {
        $this->expectExceptionObject(new EmptyProjectName());

        $this->project('  ');
    }

    public function testRecolor(): void
    {
        $project = $this->project('Home');

        $project->recolor(ProjectColor::Lichen);

        self::assertSame(ProjectColor::Lichen, $project->color());
    }

    public function testTakesTheGivenPlaceAndMoves(): void
    {
        $project = $this->project('Home', 3);
        self::assertSame(3, $project->position());

        $project->moveTo(0);

        self::assertSame(0, $project->position());
    }

    private function project(string $name, int $position = 0): Project
    {
        $now = new \DateTimeImmutable('2026-10-06 09:00');

        return Project::create(User::join('account', 'louis@example.com', 'Louis', 'Europe/Paris', $now), $name, ProjectColor::Berry, $now, $position);
    }
}
