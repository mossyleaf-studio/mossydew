<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Domain\Identity\User;
use App\Domain\Shared\Exception\NotFound;
use App\Domain\Social\GoalMember;
use App\Domain\Social\SharedGoal;
use App\Domain\Social\SharedGoalRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

final readonly class DoctrineSharedGoalRepository implements SharedGoalRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function add(SharedGoal $goal): void
    {
        $this->entityManager->persist($goal);
    }

    public function remove(SharedGoal $goal): void
    {
        $this->entityManager->remove($goal);
    }

    public function get(User $member, Ulid $id): SharedGoal
    {
        /** @var SharedGoal|null $goal */
        $goal = $this->joined($member)->andWhere('g.id = :id')->setParameter('id', $id, UlidType::NAME)->getQuery()->getOneOrNullResult();

        return $goal ?? throw new NotFound('goal', (string) $id);
    }

    public function of(User $member): array
    {
        /** @var list<SharedGoal> $goals */
        $goals = $this->joined($member)->orderBy('g.reachedAt', 'DESC')->addOrderBy('g.endsOn', 'ASC')->getQuery()->getResult();

        return $goals;
    }

    public function lockedOpenOf(User $member): array
    {
        /** @var list<SharedGoal> $goals */
        $goals = $this->joined($member)->andWhere('g.reachedAt IS NULL')->orderBy('g.id', 'ASC')->getQuery()->setLockMode(LockMode::PESSIMISTIC_WRITE)->setHint(Query::HINT_REFRESH, true)->getResult();

        return $goals;
    }

    public function reachedBy(User $member): int
    {
        return (int) $this->joined($member)->select('COUNT(g.id)')->andWhere('g.reachedAt IS NOT NULL')->getQuery()->getSingleScalarResult();
    }

    private function joined(User $member): QueryBuilder
    {
        return $this->entityManager->createQueryBuilder()
            ->select('g')
            ->from(SharedGoal::class, 'g')
            ->andWhere(\sprintf('EXISTS (SELECT m.id FROM %s m WHERE m.goal = g AND m.user = :member)', GoalMember::class))
            ->setParameter('member', $member->id(), UlidType::NAME);
    }
}
