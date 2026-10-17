<?php

declare(strict_types=1);

namespace App\Domain\Gamification;

use App\Domain\Identity\User;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\Table(name: 'player')]
class Player
{
    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    private Ulid $id;

    #[ORM\OneToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, unique: true, onDelete: 'CASCADE')]
    private User $owner;

    #[ORM\Column]
    private int $xp = 0;

    #[ORM\Column]
    private int $highestLevel = 1;

    #[ORM\Embedded(class: Streak::class, columnPrefix: 'streak_')]
    private Streak $streak;

    private function __construct(User $owner)
    {
        $this->id = new Ulid();
        $this->owner = $owner;
        $this->streak = new Streak();
    }

    public static function start(User $owner): self
    {
        return new self($owner);
    }

    public function recordActivity(\DateTimeImmutable $day): void
    {
        $this->streak = $this->streak->record($day);
    }

    public function earn(Reward $reward): ?int
    {
        $this->xp += $reward->xp;
        if ($this->level() <= $this->highestLevel) {
            return null;
        }
        $this->highestLevel = $this->level();

        return $this->highestLevel;
    }

    public function forfeit(Reward $reward): void
    {
        $this->xp = max(0, $this->xp - $reward->xp);
    }

    public function highestLevel(): int
    {
        return $this->highestLevel;
    }

    public function level(): int
    {
        return LevelCurve::levelFor($this->xp);
    }

    public function id(): Ulid
    {
        return $this->id;
    }

    public function owner(): User
    {
        return $this->owner;
    }

    public function xp(): int
    {
        return $this->xp;
    }

    public function streak(): Streak
    {
        return $this->streak;
    }
}
