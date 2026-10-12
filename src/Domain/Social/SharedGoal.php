<?php

declare(strict_types=1);

namespace App\Domain\Social;

use App\Domain\Identity\User;
use App\Domain\Shared\Day;
use App\Domain\Social\Exception\EmptyGoalTitle;
use App\Domain\Social\Exception\GoalEndsInThePast;
use App\Domain\Social\Exception\GoalNeedsFriends;
use App\Domain\Social\Exception\InvalidGoalTarget;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\Table(name: 'shared_goal')]
class SharedGoal
{
    public const int MAX_TITLE_LENGTH = 80;
    public const int MAX_TARGET = 1000;

    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    private Ulid $id;

    #[ORM\Column(length: self::MAX_TITLE_LENGTH)]
    private string $title;

    #[ORM\Column]
    private int $target;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $endsOn;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $reachedAt = null;

    /** @var Collection<int, GoalMember> */
    #[ORM\OneToMany(targetEntity: GoalMember::class, mappedBy: 'goal', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $members;

    /**
     * @param list<User> $friends
     */
    private function __construct(User $creator, string $title, int $target, \DateTimeImmutable $endsOn, array $friends, \DateTimeImmutable $now)
    {
        $title = trim((string) preg_replace('/\s+/', ' ', $title));
        if ('' === $title) {
            throw new EmptyGoalTitle();
        }
        if ($target < 1 || $target > self::MAX_TARGET) {
            throw new InvalidGoalTarget();
        }
        if ([] === $friends) {
            throw new GoalNeedsFriends();
        }
        if (Day::normalize($endsOn) < $creator->today($now)) {
            throw new GoalEndsInThePast();
        }
        $this->id = new Ulid();
        $this->title = mb_substr($title, 0, self::MAX_TITLE_LENGTH);
        $this->target = $target;
        $this->endsOn = Day::normalize($endsOn);
        $this->createdAt = $now;
        $this->members = new ArrayCollection();
        foreach ([$creator, ...$friends] as $member) {
            $this->members->add(new GoalMember($this, $member));
        }
    }

    /**
     * @param list<User> $friends
     */
    public static function set(User $creator, string $title, int $target, \DateTimeImmutable $endsOn, array $friends, \DateTimeImmutable $now): self
    {
        return new self($creator, $title, $target, $endsOn, $friends, $now);
    }

    public function isActiveOn(\DateTimeImmutable $today): bool
    {
        return null === $this->reachedAt && Day::normalize($today) <= $this->endsOn;
    }

    public function contribute(User $user, int $tasks, \DateTimeImmutable $today, \DateTimeImmutable $now): bool
    {
        $member = $this->memberOf($user);
        if (null === $member || $tasks < 1 || !$this->isActiveOn($today)) {
            return false;
        }
        $member->add($tasks);
        if ($this->total() < $this->target) {
            return false;
        }
        $this->reachedAt = $now;

        return true;
    }

    public function leave(User $user): void
    {
        $member = $this->memberOf($user);
        if (null !== $member) {
            $this->members->removeElement($member);
        }
    }

    public function isEmpty(): bool
    {
        return $this->members->isEmpty();
    }

    public function total(): int
    {
        return array_sum(array_map(static fn (GoalMember $member): int => $member->contributed(), $this->members->toArray()));
    }

    public function memberOf(User $user): ?GoalMember
    {
        foreach ($this->members as $member) {
            if ($member->is($user)) {
                return $member;
            }
        }

        return null;
    }

    /**
     * @return list<GoalMember>
     */
    public function members(): array
    {
        return array_values($this->members->toArray());
    }

    public function id(): Ulid
    {
        return $this->id;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function target(): int
    {
        return $this->target;
    }

    public function endsOn(): \DateTimeImmutable
    {
        return $this->endsOn;
    }

    public function reachedAt(): ?\DateTimeImmutable
    {
        return $this->reachedAt;
    }
}
