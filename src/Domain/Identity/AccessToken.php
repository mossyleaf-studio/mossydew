<?php

declare(strict_types=1);

namespace App\Domain\Identity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\Table(name: 'access_token')]
class AccessToken
{
    public const string PREFIX = 'mdw_';

    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    private Ulid $id;

    #[ORM\OneToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, unique: true, onDelete: 'CASCADE')]
    private User $owner;

    #[ORM\Column(length: 64, unique: true)]
    private string $hash;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    private function __construct(User $owner, string $secret, \DateTimeImmutable $now)
    {
        $this->id = new Ulid();
        $this->owner = $owner;
        $this->hash = self::hash($secret);
        $this->createdAt = $now;
    }

    public static function issue(User $owner, string $secret, \DateTimeImmutable $now): self
    {
        return new self($owner, $secret, $now);
    }

    public function rotate(string $secret, \DateTimeImmutable $now): void
    {
        $this->hash = self::hash($secret);
        $this->createdAt = $now;
    }

    public static function hash(string $secret): string
    {
        return hash('sha256', $secret);
    }

    public function owner(): User
    {
        return $this->owner;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
