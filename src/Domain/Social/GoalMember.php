<?php

declare(strict_types=1);

namespace App\Domain\Social;

use App\Domain\Identity\User;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\Table(name: 'goal_member')]
#[ORM\UniqueConstraint(name: 'goal_member_user', columns: ['goal_id', 'user_id'])]
class GoalMember
{
    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    private Ulid $id;

    #[ORM\ManyToOne(targetEntity: SharedGoal::class, inversedBy: 'members')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private SharedGoal $goal;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column]
    private int $contributed = 0;

    public function __construct(SharedGoal $goal, User $user)
    {
        $this->id = new Ulid();
        $this->goal = $goal;
        $this->user = $user;
    }

    public function add(int $tasks): void
    {
        $this->contributed += $tasks;
    }

    public function withdraw(int $tasks): void
    {
        $this->contributed = max(0, $this->contributed - $tasks);
    }

    public function is(User $user): bool
    {
        return $this->user->id()->equals($user->id());
    }

    public function user(): User
    {
        return $this->user;
    }

    public function contributed(): int
    {
        return $this->contributed;
    }
}
