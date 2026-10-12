<?php

declare(strict_types=1);

namespace App\Domain\Planning;

use App\Domain\Identity\User;
use App\Domain\Planning\Exception\DoneTaskCannotRepeat;
use App\Domain\Planning\Exception\EmptyTaskTitle;
use App\Domain\Planning\Exception\ProjectOfAnotherOwner;
use App\Domain\Planning\Exception\SubtaskCannotHaveSubtasks;
use App\Domain\Planning\Exception\SubtaskCannotRepeat;
use App\Domain\Planning\Exception\SubtaskFollowsParentProject;
use App\Domain\Planning\Exception\TaskAlreadyDone;
use App\Domain\Planning\Exception\TaskCannotNestUnderItself;
use App\Domain\Planning\Exception\TaskHasOpenSubtasks;
use App\Domain\Planning\Exception\TaskNotDone;
use App\Domain\Planning\Exception\TaskWithSubtasksCannotBeNested;
use App\Domain\Shared\Day;
use App\Domain\Shared\OptionalText;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\Table(name: 'task')]
#[ORM\Index(name: 'task_owner_planned', columns: ['owner_id', 'planned_on'])]
#[ORM\Index(name: 'task_owner_quadrant', columns: ['owner_id', 'quadrant', 'rank'])]
#[ORM\Index(name: 'task_owner_completed', columns: ['owner_id', 'completed_at'])]
#[ORM\UniqueConstraint(name: 'task_owner_external', columns: ['owner_id', 'external_id'])]
class Task
{
    public const int MAX_TITLE_LENGTH = 200;

    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    private Ulid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $owner;

    #[ORM\Column(length: self::MAX_TITLE_LENGTH)]
    private string $title;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Project $project = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'subtasks')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?self $parent = null;

    /** @var Collection<int, Task> */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent', cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['createdAt' => 'ASC', 'id' => 'ASC'])]
    private Collection $subtasks;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $plannedOn = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $dueOn = null;

    #[ORM\Column(length: 16, nullable: true, enumType: Quadrant::class)]
    private ?Quadrant $quadrant = null;

    #[ORM\Column]
    private int $rank = 0;

    #[ORM\Column(length: 8, nullable: true, enumType: RecurrenceUnit::class)]
    private ?RecurrenceUnit $recurrenceUnit = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    private ?int $recurrenceInterval = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $rewardedAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $externalId = null;

    private function __construct(User $owner, string $title, \DateTimeImmutable $now)
    {
        $this->id = new Ulid();
        $this->owner = $owner;
        $this->title = self::validTitle($title);
        $this->createdAt = $now;
        $this->subtasks = new ArrayCollection();
    }

    public static function create(User $owner, string $title, \DateTimeImmutable $now): self
    {
        return new self($owner, $title, $now);
    }

    public function addSubtask(string $title, \DateTimeImmutable $now): self
    {
        $subtask = new self($this->owner, $title, $now);
        $subtask->plannedOn = $this->plannedOn;
        $subtask->nestUnder($this);

        return $subtask;
    }

    public function nestUnder(self $parent): void
    {
        if ($parent->id->equals($this->id)) {
            throw new TaskCannotNestUnderItself($this->title);
        }
        if (null !== $parent->parent) {
            throw new SubtaskCannotHaveSubtasks($parent->title);
        }
        if (!$this->subtasks->isEmpty()) {
            throw new TaskWithSubtasksCannotBeNested($this->title);
        }
        if (null !== $this->recurrence()) {
            throw new SubtaskCannotRepeat($this->title);
        }
        $this->parent?->subtasks->removeElement($this);
        $this->parent = $parent;
        $this->project = $parent->project;
        $parent->subtasks->add($this);
        if (!$this->isDone()) {
            $parent->completedAt = null;
        }
    }

    public function promote(): void
    {
        $this->parent?->subtasks->removeElement($this);
        $this->parent = null;
    }

    public function linkTo(string $externalId): void
    {
        $this->externalId = $externalId;
    }

    public function rename(string $title): void
    {
        $this->title = self::validTitle($title);
    }

    public function describe(?string $notes): void
    {
        $this->notes = OptionalText::of($notes);
    }

    public function planFor(\DateTimeImmutable $day): void
    {
        $this->plannedOn = Day::normalize($day);
    }

    public function unplan(): void
    {
        $this->plannedOn = null;
    }

    public function dueBy(\DateTimeImmutable $day): void
    {
        $this->dueOn = Day::normalize($day);
    }

    public function clearDeadline(): void
    {
        $this->dueOn = null;
    }

    public function fileUnder(Project $project): void
    {
        if (!$project->owner()->id()->equals($this->owner->id())) {
            throw new ProjectOfAnotherOwner();
        }
        $this->move($project);
    }

    public function detach(): void
    {
        $this->move(null);
    }

    public function classify(Quadrant $quadrant, int $rank): void
    {
        $this->quadrant = $quadrant;
        $this->rank = $rank;
    }

    public function unclassify(): void
    {
        $this->quadrant = null;
        $this->rank = 0;
    }

    public function repeat(?Recurrence $recurrence): void
    {
        if (null !== $recurrence && $this->isDone()) {
            throw new DoneTaskCannotRepeat($this->title);
        }
        if (null !== $recurrence && null !== $this->parent) {
            throw new SubtaskCannotRepeat($this->title);
        }
        $this->recurrenceUnit = $recurrence?->unit;
        $this->recurrenceInterval = $recurrence?->interval;
    }

    public function nextOccurrence(\DateTimeImmutable $today, \DateTimeImmutable $now): ?self
    {
        $recurrence = $this->recurrence();
        if (null === $recurrence) {
            return null;
        }
        if (!$this->isDone()) {
            throw new TaskNotDone($this->title);
        }

        $today = Day::normalize($today);
        $plannedOn = $this->plannedOn();
        $dueOn = $this->dueOn();
        $anchor = $plannedOn ?? $dueOn ?? $today;
        $nextDay = $anchor;
        do {
            $nextDay = $recurrence->next($nextDay);
        } while ($nextDay <= $today);

        $occurrence = new self($this->owner, $this->title, $now);
        $occurrence->notes = $this->notes;
        $occurrence->project = $this->project;
        $occurrence->quadrant = $this->quadrant;
        $occurrence->rank = $this->rank;
        if (null !== $plannedOn || null === $dueOn) {
            $occurrence->plannedOn = $nextDay;
        }
        if (null !== $dueOn) {
            $occurrence->dueOn = $dueOn->modify(\sprintf('%+d days', Day::daysBetween($anchor, $nextDay)));
        }
        foreach ($this->subtasks as $subtask) {
            $occurrence->addSubtask($subtask->title, $now)->describe($subtask->notes);
        }
        $occurrence->repeat($recurrence);
        $this->repeat(null);

        return $occurrence;
    }

    /**
     * @return list<Task>
     */
    public function complete(\DateTimeImmutable $now): array
    {
        if ($this->isDone()) {
            throw new TaskAlreadyDone($this->title);
        }
        if ($this->openSubtaskCount() > 0) {
            throw new TaskHasOpenSubtasks($this->title);
        }
        $this->completedAt = $now;

        $parent = $this->parent;
        if (null === $parent || $parent->isDone() || $parent->openSubtaskCount() > 0) {
            return [$this];
        }
        $parent->completedAt = $now;

        return [$this, $parent];
    }

    public function reopen(): void
    {
        if (!$this->isDone()) {
            throw new TaskNotDone($this->title);
        }
        $this->completedAt = null;
        if (null !== $this->parent) {
            $this->parent->completedAt = null;
        }
    }

    public function claimReward(\DateTimeImmutable $now): bool
    {
        if (!$this->isDone() || null !== $this->rewardedAt) {
            return false;
        }
        $this->rewardedAt = $now;

        return true;
    }

    public function isDone(): bool
    {
        return null !== $this->completedAt;
    }

    public function isOnTime(\DateTimeImmutable $today): bool
    {
        return null !== $this->dueOn && Day::normalize($today) <= $this->dueOn();
    }

    public function id(): Ulid
    {
        return $this->id;
    }

    public function owner(): User
    {
        return $this->owner;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function notes(): ?string
    {
        return $this->notes;
    }

    public function project(): ?Project
    {
        return $this->project;
    }

    public function parent(): ?self
    {
        return $this->parent;
    }

    /**
     * @return list<Task>
     */
    public function subtasks(): array
    {
        return array_values($this->subtasks->toArray());
    }

    public function subtaskCount(): int
    {
        return $this->subtasks->count();
    }

    public function openSubtaskCount(): int
    {
        return \count(array_filter($this->subtasks->toArray(), static fn (self $subtask): bool => !$subtask->isDone()));
    }

    public function plannedOn(): ?\DateTimeImmutable
    {
        return null === $this->plannedOn ? null : Day::normalize($this->plannedOn);
    }

    public function dueOn(): ?\DateTimeImmutable
    {
        return null === $this->dueOn ? null : Day::normalize($this->dueOn);
    }

    public function quadrant(): ?Quadrant
    {
        return $this->quadrant;
    }

    public function rank(): int
    {
        return $this->rank;
    }

    public function recurrence(): ?Recurrence
    {
        if (null === $this->recurrenceUnit || null === $this->recurrenceInterval) {
            return null;
        }

        return new Recurrence($this->recurrenceInterval, $this->recurrenceUnit);
    }

    public function completedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    private function move(?Project $project): void
    {
        if (null !== $this->parent && !self::sameProject($project, $this->parent->project)) {
            throw new SubtaskFollowsParentProject($this->title);
        }
        $this->project = $project;
        foreach ($this->subtasks as $subtask) {
            $subtask->project = $project;
        }
    }

    private static function sameProject(?Project $one, ?Project $other): bool
    {
        return null === $one || null === $other ? $one === $other : $one->id()->equals($other->id());
    }

    private static function validTitle(string $title): string
    {
        $title = trim(preg_replace('/\s+/u', ' ', $title) ?? '');
        if ('' === $title) {
            throw new EmptyTaskTitle();
        }

        return mb_substr($title, 0, self::MAX_TITLE_LENGTH);
    }

    public function externalId(): ?string
    {
        return $this->externalId;
    }
}
