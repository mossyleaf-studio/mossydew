<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Domain\Planning\Project;
use App\Domain\Planning\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

final readonly class DoctrineProjectRepository implements ProjectRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private OwnerScope $scope,
    ) {
    }

    public function add(Project $project): void
    {
        $this->entityManager->persist($project);
    }

    public function remove(Project $project): void
    {
        $this->entityManager->remove($project);
    }

    public function get(Ulid $id): Project
    {
        return $this->scope->get(Project::class, $id, 'project');
    }

    public function named(string $name): ?Project
    {
        $result = $this->scope->restrict($this->entityManager->createQueryBuilder()->select('p')->from(Project::class, 'p'), 'p')
            ->andWhere('LOWER(p.name) = LOWER(:name)')
            ->setParameter('name', trim($name))
            ->getQuery()
            ->getOneOrNullResult();

        return $result instanceof Project ? $result : null;
    }

    public function all(): array
    {
        return $this->scope->findBy(Project::class, orderBy: ['position' => 'ASC', 'name' => 'ASC']);
    }

    public function nextPosition(): int
    {
        $last = $this->scope->restrict($this->entityManager->createQueryBuilder()->select('MAX(p.position)')->from(Project::class, 'p'), 'p')
            ->getQuery()
            ->getSingleScalarResult();

        return null === $last ? 0 : (int) $last + 1;
    }
}
