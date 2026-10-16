<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Domain\Identity\DeviceToken;
use App\Domain\Identity\DeviceTokenRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineDeviceTokenRepository implements DeviceTokenRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function add(DeviceToken $token): void
    {
        $this->entityManager->persist($token);
    }

    public function remove(DeviceToken $token): void
    {
        $this->entityManager->remove($token);
    }

    public function withSecret(string $secret): ?DeviceToken
    {
        return $this->entityManager->getRepository(DeviceToken::class)->findOneBy(['hash' => DeviceToken::hash($secret)]);
    }
}
