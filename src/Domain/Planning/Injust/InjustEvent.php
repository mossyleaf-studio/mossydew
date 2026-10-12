<?php

declare(strict_types=1);

namespace App\Domain\Planning\Injust;

use App\Domain\Identity\User;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\Table(name: 'injust_event')]
#[ORM\UniqueConstraint(name: 'injust_event_owner_seq', columns: ['owner_id', 'seq'])]
class InjustEvent
{
    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    private Ulid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $owner;

    #[ORM\Column]
    private int $seq;

    #[ORM\Column(length: 120)]
    private string $item;

    #[ORM\Column(length: 8, enumType: InjustEventType::class)]
    private InjustEventType $type;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $at;

    public function __construct(User $owner, int $seq, string $item, InjustEventType $type, \DateTimeImmutable $at)
    {
        $this->id = new Ulid();
        $this->owner = $owner;
        $this->seq = $seq;
        $this->item = $item;
        $this->type = $type;
        $this->at = $at;
    }

    public function seq(): int
    {
        return $this->seq;
    }

    public function item(): string
    {
        return $this->item;
    }

    public function type(): InjustEventType
    {
        return $this->type;
    }

    public function at(): \DateTimeImmutable
    {
        return $this->at;
    }
}
