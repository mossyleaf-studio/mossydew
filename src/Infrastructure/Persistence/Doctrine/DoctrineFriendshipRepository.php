<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Domain\Identity\User;
use App\Domain\Shared\Exception\NotFound;
use App\Domain\Social\Friendship;
use App\Domain\Social\FriendshipRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

final readonly class DoctrineFriendshipRepository implements FriendshipRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function add(Friendship $friendship): void
    {
        $this->entityManager->persist($friendship);
    }

    public function remove(Friendship $friendship): void
    {
        $this->entityManager->remove($friendship);
    }

    public function between(User $one, User $other): ?Friendship
    {
        [$first, $second] = Friendship::ordered($one, $other);

        return $this->entityManager->getRepository(Friendship::class)->findOneBy(['first' => $first, 'second' => $second]);
    }

    public function of(User $user): array
    {
        /** @var list<Friendship> $friendships */
        $friendships = $this->entityManager->createQueryBuilder()
            ->select('f')
            ->from(Friendship::class, 'f')
            ->where('f.first = :user OR f.second = :user')
            ->setParameter('user', $user->id(), UlidType::NAME)
            ->orderBy('f.since', 'ASC')
            ->getQuery()
            ->getResult();

        return $friendships;
    }

    public function with(User $user, Ulid $friendId): Friendship
    {
        foreach ($this->of($user) as $friendship) {
            if ($friendship->friendOf($user)->id()->equals($friendId)) {
                return $friendship;
            }
        }

        throw new NotFound('friend', (string) $friendId);
    }
}
