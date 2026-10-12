<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Domain\Identity\AccessToken;
use App\Domain\Identity\AccessTokenRepository;
use App\Domain\Identity\User;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineAccessTokenRepository implements AccessTokenRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function add(AccessToken $token): void
    {
        $this->entityManager->persist($token);
    }

    public function remove(AccessToken $token): void
    {
        $this->entityManager->remove($token);
    }

    public function of(User $owner): ?AccessToken
    {
        return $this->entityManager->getRepository(AccessToken::class)->findOneBy(['owner' => $owner]);
    }

    public function withSecret(string $secret): ?AccessToken
    {
        return $this->entityManager->getRepository(AccessToken::class)->findOneBy(['hash' => AccessToken::hash($secret)]);
    }
}
