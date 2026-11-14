<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Planning;

use App\Application\Planning\TaskQueries;
use App\Domain\Planning\Project;
use App\Domain\Planning\Quadrant;
use App\Domain\Planning\Task;
use App\Infrastructure\Persistence\Doctrine\OwnerScope;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

final readonly class DoctrineTaskQueries implements TaskQueries
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private OwnerScope $scope,
    ) {
    }

    public function openForDay(\DateTimeImmutable $day): array
    {
        return $this->list($this->ordered()
            ->andWhere('t.completedAt IS NULL')
            ->andWhere('t.plannedOn <= :day OR t.dueOn <= :day')
            ->setParameter('day', $day, 'date_immutable'));
    }

    public function completedBetween(\DateTimeImmutable $from, \DateTimeImmutable $until): array
    {
        return $this->list($this->scoped()
            ->andWhere('t.completedAt >= :from AND t.completedAt < :until')
            ->setParameter('from', $from, 'datetime_immutable')
            ->setParameter('until', $until, 'datetime_immutable')
            ->orderBy('t.completedAt', 'DESC')
            ->addOrderBy('t.id', 'DESC'));
    }

    public function lastCompletedBefore(\DateTimeImmutable $until): ?Task
    {
        return $this->list($this->scoped()
            ->andWhere('t.completedAt < :until')
            ->setParameter('until', $until, 'datetime_immutable')
            ->orderBy('t.completedAt', 'DESC')
            ->setMaxResults(1))[0] ?? null;
    }

    public function openUpcomingBetween(\DateTimeImmutable $first, \DateTimeImmutable $last): array
    {
        return $this->list($this->ordered()
            ->andWhere('t.completedAt IS NULL')
            ->andWhere('t.plannedOn BETWEEN :first AND :last OR (t.plannedOn IS NULL AND t.dueOn BETWEEN :first AND :last)')
            ->setParameter('first', $first, 'date_immutable')
            ->setParameter('last', $last, 'date_immutable'));
    }

    public function openWithoutProject(): array
    {
        return $this->list($this->ordered()
            ->andWhere('t.completedAt IS NULL')
            ->andWhere('t.project IS NULL'));
    }

    public function ofProject(Project $project, \DateTimeImmutable $doneSince): array
    {
        return $this->list($this->ordered()
            ->andWhere('t.project = :project')
            ->andWhere('t.completedAt IS NULL OR t.completedAt >= :doneSince')
            ->setParameter('project', $project->id(), UlidType::NAME)
            ->setParameter('doneSince', $doneSince, 'datetime_immutable'));
    }

    public function open(): array
    {
        return $this->list($this->ordered()->andWhere('t.completedAt IS NULL'));
    }

    public function openCountByProject(): array
    {
        $rows = $this->scoped()
            ->select('IDENTITY(t.project) AS project, COUNT(t.id) AS open')
            ->andWhere('t.completedAt IS NULL')
            ->andWhere('t.project IS NOT NULL')
            ->groupBy('t.project')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            if (\is_array($row) && isset($row['project'], $row['open']) && \is_scalar($row['project']) && is_numeric($row['open'])) {
                $counts[(string) Ulid::fromString((string) $row['project'])] = (int) $row['open'];
            }
        }

        return $counts;
    }

    private function scoped(): QueryBuilder
    {
        return $this->scope->restrict($this->entityManager->createQueryBuilder()->select('t')->from(Task::class, 't'), 't');
    }

    private function ordered(): QueryBuilder
    {
        $priority = implode(' ', array_map(
            static fn (Quadrant $quadrant): string => \sprintf("WHEN '%s' THEN %d", $quadrant->value, $quadrant->priority()),
            Quadrant::cases(),
        ));

        return $this->scoped()
            ->addSelect('CASE WHEN t.completedAt IS NULL THEN 0 ELSE 1 END AS HIDDEN doneOrder')
            ->addSelect(\sprintf('CASE t.quadrant %s ELSE %d END AS HIDDEN quadrantOrder', $priority, Quadrant::UNSORTED_PRIORITY))
            ->orderBy('doneOrder', 'ASC')
            ->addOrderBy('quadrantOrder', 'ASC')
            ->addOrderBy('t.rank', 'ASC')
            ->addOrderBy('t.dueOn', 'ASC')
            ->addOrderBy('t.createdAt', 'ASC')
            ->addOrderBy('t.id', 'ASC');
    }

    /**
     * @return list<Task>
     */
    private function list(QueryBuilder $query): array
    {
        $tasks = $query->getQuery()->getResult();
        $tasks = \is_array($tasks) ? array_values(array_filter($tasks, static fn (mixed $task): bool => $task instanceof Task)) : [];
        $this->loadFamilies($tasks);

        return $tasks;
    }

    /**
     * @param list<Task> $tasks
     */
    private function loadFamilies(array $tasks): void
    {
        if ([] === $tasks) {
            return;
        }
        $this->entityManager->createQueryBuilder()
            ->select('t', 'parent', 'subtask')
            ->from(Task::class, 't')
            ->leftJoin('t.parent', 'parent')
            ->leftJoin('t.subtasks', 'subtask')
            ->where('t.id IN (:ids)')
            ->setParameter('ids', array_map(static fn (Task $task): string => $task->id()->toRfc4122(), $tasks))
            ->getQuery()
            ->getResult();
    }
}
