<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Domain\Identity\User;
use App\Domain\Mystery\Investigation;
use App\Domain\Mystery\InvestigationRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Symfony\Bridge\Doctrine\Types\UlidType;

final readonly class DoctrineInvestigationRepository implements InvestigationRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function add(Investigation $investigation): void
    {
        $this->entityManager->persist($investigation);
    }

    public function latestOf(User $owner): ?Investigation
    {
        $investigations = $this->entityManager->getRepository(Investigation::class);

        return $investigations->findOneBy(['owner' => $owner, 'solvedAt' => null])
            ?? $investigations->findOneBy(['owner' => $owner], ['solvedAt' => 'DESC']);
    }

    public function openLockedOf(User $owner): ?Investigation
    {
        $investigation = $this->entityManager->createQueryBuilder()
            ->select('investigation')
            ->from(Investigation::class, 'investigation')
            ->where('investigation.owner = :owner')
            ->andWhere('investigation.solvedAt IS NULL')
            ->setParameter('owner', $owner->id(), UlidType::NAME)
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->setHint(Query::HINT_REFRESH, true)
            ->getOneOrNullResult();

        return $investigation instanceof Investigation ? $investigation : null;
    }

    public function solvedCount(User $owner): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(investigation.id)')
            ->from(Investigation::class, 'investigation')
            ->where('investigation.owner = :owner')
            ->andWhere('investigation.solvedAt IS NOT NULL')
            ->setParameter('owner', $owner->id(), UlidType::NAME)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
