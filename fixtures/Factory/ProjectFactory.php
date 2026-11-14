<?php

declare(strict_types=1);

namespace App\Fixtures\Factory;

use App\Domain\Planning\Project;
use App\Domain\Planning\ProjectColor;
use Zenstruck\Foundry\Object\Instantiator;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/** @extends PersistentObjectFactory<Project> */
final class ProjectFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Project::class;
    }

    protected function defaults(): array
    {
        return [
            'owner' => UserFactory::new(),
            'name' => ucfirst(self::faker()->unique()->word()),
            'color' => self::faker()->randomElement(ProjectColor::cases()),
            'now' => new \DateTimeImmutable(),
            'position' => 0,
        ];
    }

    protected function initialize(): static
    {
        return $this->instantiateWith(Instantiator::namedConstructor('create')->disableHydration());
    }
}
