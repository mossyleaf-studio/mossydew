<?php

declare(strict_types=1);

namespace App\Domain\Social;

use App\Domain\Identity\User;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\Table(name: 'friendship')]
#[ORM\UniqueConstraint(name: 'friendship_pair', columns: ['first_id', 'second_id'])]
class Friendship
{
    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    private Ulid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $first;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $second;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $since;

    private function __construct(User $first, User $second, \DateTimeImmutable $now)
    {
        $this->id = new Ulid();
        $this->first = $first;
        $this->second = $second;
        $this->since = $now;
    }

    public static function between(User $one, User $other, \DateTimeImmutable $now): self
    {
        [$first, $second] = self::ordered($one, $other);

        return new self($first, $second, $now);
    }

    /**
     * @return array{User, User}
     */
    public static function ordered(User $one, User $other): array
    {
        return (string) $one->id() < (string) $other->id() ? [$one, $other] : [$other, $one];
    }

    public function friendOf(User $user): User
    {
        return $this->first->id()->equals($user->id()) ? $this->second : $this->first;
    }

    public function since(): \DateTimeImmutable
    {
        return $this->since;
    }
}
