<?php

declare(strict_types=1);

namespace App\Domain\Planning\Injust;

use App\Domain\Identity\User;
use App\Domain\Planning\Task;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\Table(name: 'injust_link')]
class InjustLink
{
    public const string PREFIX = 'injust:';
    public const string GROUP = 'injust-group:';

    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    private Ulid $id;

    #[ORM\OneToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, unique: true, onDelete: 'CASCADE')]
    private User $owner;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $instance = null;

    #[ORM\Column(nullable: true)]
    private ?int $feedSeq = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $feedAt = null;

    #[ORM\Column]
    private int $lastEventSeq = 0;

    private function __construct(User $owner)
    {
        $this->id = new Ulid();
        $this->owner = $owner;
    }

    public static function open(User $owner): self
    {
        return new self($owner);
    }

    public static function groupOf(string $tag): string
    {
        return self::GROUP.substr(hash('sha256', $tag), 0, 32);
    }

    public static function epochOf(User $owner): string
    {
        return substr(hash('sha256', 'injust:'.$owner->id()), 0, 8);
    }

    public static function itemOf(Task $task): ?string
    {
        $externalId = $task->externalId();

        return null !== $externalId && str_starts_with($externalId, self::PREFIX) ? substr($externalId, strlen(self::PREFIX)) : null;
    }

    public function receive(string $instance, int $seq, \DateTimeImmutable $now): void
    {
        $this->instance = $instance;
        $this->feedSeq = $seq;
        $this->feedAt = $now;
    }

    public function acknowledged(?string $epoch, int $appliedThrough): int
    {
        return $epoch === self::epochOf($this->owner) ? $appliedThrough : 0;
    }

    public function record(string $item, InjustEventType $type, \DateTimeImmutable $at): InjustEvent
    {
        return new InjustEvent($this->owner, ++$this->lastEventSeq, $item, $type, $at);
    }

    public function owner(): User
    {
        return $this->owner;
    }

    public function instance(): ?string
    {
        return $this->instance;
    }

    public function feedSeq(): ?int
    {
        return $this->feedSeq;
    }

    public function feedAt(): ?\DateTimeImmutable
    {
        return $this->feedAt;
    }
}
