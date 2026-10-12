<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Domain\Identity\User;
use App\Domain\Planning\Injust\InjustEvent;
use App\Domain\Planning\Injust\InjustLink;
use App\Domain\Planning\Injust\InjustLinkRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Types\UlidType;

final readonly class DoctrineInjustLinkRepository implements InjustLinkRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function add(InjustLink $link): void
    {
        $this->entityManager->persist($link);
    }

    public function of(User $owner): ?InjustLink
    {
        return $this->entityManager->getRepository(InjustLink::class)->findOneBy(['owner' => $owner]);
    }

    public function record(InjustEvent $event): void
    {
        $this->entityManager->persist($event);
    }

    public function pending(User $owner): array
    {
        return $this->entityManager->getRepository(InjustEvent::class)->findBy(['owner' => $owner], ['seq' => 'ASC']);
    }

    public function acknowledge(User $owner, int $through): void
    {
        $this->entityManager->createQueryBuilder()
            ->delete(InjustEvent::class, 'e')
            ->where('e.owner = :owner')
            ->andWhere('e.seq <= :through')
            ->setParameter('owner', $owner->id(), UlidType::NAME)
            ->setParameter('through', $through)
            ->getQuery()
            ->execute();
    }
}
