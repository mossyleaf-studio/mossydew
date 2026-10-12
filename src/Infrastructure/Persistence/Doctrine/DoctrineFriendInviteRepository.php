<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Domain\Shared\Exception\NotFound;
use App\Domain\Social\FriendInvite;
use App\Domain\Social\FriendInviteRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineFriendInviteRepository implements FriendInviteRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function add(FriendInvite $invite): void
    {
        $this->entityManager->persist($invite);
    }

    public function withSecret(string $secret): FriendInvite
    {
        return $this->entityManager->getRepository(FriendInvite::class)->findOneBy(['hash' => FriendInvite::hash($secret)])
            ?? throw new NotFound('invite', '');
    }
}
