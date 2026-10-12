<?php

declare(strict_types=1);

namespace App\Domain\Social;

use App\Domain\Identity\User;
use App\Domain\Social\Exception\InviteAlreadyUsed;
use App\Domain\Social\Exception\InviteExpired;
use App\Domain\Social\Exception\OwnInvite;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\Table(name: 'friend_invite')]
class FriendInvite
{
    public const string LIFETIME = '+7 days';

    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    private Ulid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $inviter;

    #[ORM\Column(length: 64, unique: true)]
    private string $hash;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $expiresAt;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?User $acceptedBy = null;

    private function __construct(User $inviter, string $secret, \DateTimeImmutable $now)
    {
        $this->id = new Ulid();
        $this->inviter = $inviter;
        $this->hash = self::hash($secret);
        $this->expiresAt = $now->modify(self::LIFETIME);
    }

    public static function send(User $inviter, string $secret, \DateTimeImmutable $now): self
    {
        return new self($inviter, $secret, $now);
    }

    public static function hash(string $secret): string
    {
        return hash('sha256', $secret);
    }

    public function accept(User $friend, \DateTimeImmutable $now): void
    {
        $this->check($friend, $now);
        $this->acceptedBy = $friend;
    }

    public function check(User $friend, \DateTimeImmutable $now): void
    {
        if ($friend->id()->equals($this->inviter->id())) {
            throw new OwnInvite();
        }
        if (null !== $this->acceptedBy) {
            throw new InviteAlreadyUsed();
        }
        if ($now >= $this->expiresAt) {
            throw new InviteExpired();
        }
    }

    public function inviter(): User
    {
        return $this->inviter;
    }

    public function expiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }
}
