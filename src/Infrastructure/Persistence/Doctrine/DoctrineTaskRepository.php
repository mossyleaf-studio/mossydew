<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Domain\Planning\Quadrant;
use App\Domain\Planning\Task;
use App\Domain\Planning\TaskRepository;
use App\Domain\Shared\Exception\NotFound;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

final readonly class DoctrineTaskRepository implements TaskRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private OwnerScope $scope,
    ) {
    }

    public function add(Task $task): void
    {
        $this->entityManager->persist($task);
    }

    public function remove(Task $task): void
    {
        $this->entityManager->remove($task);
    }

    public function get(Ulid $id): Task
    {
        return $this->scope->get(Task::class, $id, 'task');
    }

    public function getAll(array $ids): array
    {
        $found = [];
        foreach ($this->scope->findBy(Task::class, ['id' => $ids]) as $task) {
            $found[(string) $task->id()] = $task;
        }

        return array_map(
            static fn (Ulid $id): Task => $found[(string) $id] ?? throw new NotFound('task', (string) $id),
            $ids,
        );
    }

    public function openInQuadrant(Quadrant $quadrant): array
    {
        return $this->scope->findBy(Task::class, ['quadrant' => $quadrant, 'completedAt' => null], ['rank' => 'ASC', 'createdAt' => 'ASC']);
    }

    public function nextRankIn(Quadrant $quadrant): int
    {
        $max = $this->scope->restrict($this->entityManager->createQueryBuilder()->select('MAX(t.rank)')->from(Task::class, 't'), 't')
            ->andWhere('t.quadrant = :quadrant')
            ->andWhere('t.completedAt IS NULL')
            ->setParameter('quadrant', $quadrant->value)
            ->getQuery()
            ->getSingleScalarResult();

        return null === $max ? 0 : (int) $max + 1;
    }

    public function openPlannedBefore(\DateTimeImmutable $day): array
    {
        return $this->scope->restrict($this->entityManager->createQueryBuilder()->select('t')->from(Task::class, 't'), 't')
            ->andWhere('t.completedAt IS NULL')
            ->andWhere('t.plannedOn < :day')
            ->setParameter('day', $day, 'date_immutable')
            ->getQuery()
            ->getResult();
    }

    public function linked(string $prefix): array
    {
        /** @var list<Task> $tasks */
        $tasks = $this->scope->restrict($this->entityManager->createQueryBuilder()->select('t')->from(Task::class, 't'), 't')
            ->andWhere('t.externalId LIKE :prefix')
            ->setParameter('prefix', addcslashes($prefix, '%_').'%')
            ->getQuery()
            ->getResult();

        $linked = [];
        foreach ($tasks as $task) {
            $linked[(string) $task->externalId()] = $task;
        }

        return $linked;
    }
}
