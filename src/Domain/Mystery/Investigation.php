<?php

declare(strict_types=1);

namespace App\Domain\Mystery;

use App\Domain\Identity\User;
use App\Domain\Mystery\Exception\InvalidNotes;
use App\Domain\Mystery\Exception\WrongAccusation;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

/**
 * @phpstan-import-type ClueData from Clue
 */
#[ORM\Entity]
#[ORM\Table(name: 'investigation')]
#[ORM\UniqueConstraint(name: 'investigation_owner_open', columns: ['owner_id'], options: ['where' => '(solved_at IS NULL)'])]
class Investigation
{
    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    private Ulid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $owner;

    /** @var array{size: int, rooms: list<int>, kinds: list<string>, furniture: list<?string>} */
    #[ORM\Column(type: 'json')]
    private array $floorplan;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $people;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $genders;

    #[ORM\Column(type: 'smallint')]
    private int $victim;

    /** @var list<int> */
    #[ORM\Column(type: 'json')]
    private array $solution;

    /** @var list<ClueData> */
    #[ORM\Column(type: 'json')]
    private array $clues;

    /** @var list<ClueData> */
    #[ORM\Column(type: 'json')]
    private array $rules;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $revealedBy = [];

    /** @var array{placements: list<?int>, crosses: list<int>} */
    #[ORM\Column(type: 'json')]
    private array $notes;

    #[ORM\Column(options: ['default' => false])]
    private bool $gift;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $openedAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $solvedAt = null;

    private function __construct(User $owner, Blueprint $blueprint, \DateTimeImmutable $now, bool $gift)
    {
        $this->id = new Ulid();
        $this->owner = $owner;
        $this->floorplan = $blueprint->floorplan->toArray();
        $this->people = $blueprint->people;
        $this->genders = array_map(static fn (Gender $gender): string => $gender->value, $blueprint->genders);
        $this->victim = $blueprint->victim;
        $this->solution = $blueprint->solution;
        $this->clues = array_map(static fn (Clue $clue): array => $clue->toArray(), $blueprint->clues);
        $this->rules = array_map(static fn (Clue $rule): array => $rule->toArray(), $blueprint->rules);
        $this->notes = ['placements' => array_fill(0, $blueprint->floorplan->size, null), 'crosses' => []];
        $this->gift = $gift;
        $this->openedAt = $now;
    }

    public static function open(User $owner, Blueprint $blueprint, \DateTimeImmutable $now, bool $gift = false): self
    {
        return new self($owner, $blueprint, $now, $gift);
    }

    public function revealFor(Ulid $task): bool
    {
        if ($this->isSolved() || $this->hiddenClueCount() <= 0 || \in_array($task->toBase32(), $this->revealedBy, true)) {
            return false;
        }
        $this->revealedBy[] = $task->toBase32();

        return true;
    }

    /**
     * @param array<?int> $placements
     * @param array<int>  $crosses
     */
    public function takeNotes(array $placements, array $crosses): void
    {
        $size = $this->floorplan()->size;
        $placed = array_values(array_filter($placements, static fn (?int $cell): bool => null !== $cell));
        if (
            \count($placements) !== $size
            || \count($placed) !== \count(array_unique($placed))
            || \count($crosses) !== \count(array_unique($crosses))
            || !array_all([...$placed, ...$crosses], static fn (int $cell): bool => $cell >= 0 && $cell < $size * $size)
        ) {
            throw new InvalidNotes();
        }
        $this->notes = ['placements' => array_values($placements), 'crosses' => array_values($crosses)];
    }

    /**
     * @param list<int> $placements
     */
    public function accuse(array $placements, int $murderer, \DateTimeImmutable $now): void
    {
        if ($placements !== $this->solution || $murderer !== $this->murderer()) {
            throw new WrongAccusation();
        }
        $this->notes = ['placements' => $placements, 'crosses' => $this->notes['crosses']];
        $this->solvedAt = $now;
    }

    public function isSolved(): bool
    {
        return null !== $this->solvedAt;
    }

    public function id(): Ulid
    {
        return $this->id;
    }

    public function owner(): User
    {
        return $this->owner;
    }

    public function floorplan(): Floorplan
    {
        return Floorplan::fromArray($this->floorplan);
    }

    /**
     * @return list<string>
     */
    public function people(): array
    {
        return $this->people;
    }

    /**
     * @return list<Gender>
     */
    public function genders(): array
    {
        return array_map(Gender::from(...), $this->genders);
    }

    public function victim(): int
    {
        return $this->victim;
    }

    /**
     * @return list<int>
     */
    public function solution(): array
    {
        return $this->solution;
    }

    public function murderer(): int
    {
        return $this->floorplan()->murdererIn($this->solution, $this->victim) ?? throw new \LogicException('A case always has a murderer.');
    }

    /**
     * @return list<Clue>
     */
    public function revealedClues(): array
    {
        return array_map(Clue::fromArray(...), \array_slice($this->clues, 0, $this->revealedClueCount()));
    }

    /**
     * @return list<Clue>
     */
    public function rules(): array
    {
        return array_map(Clue::fromArray(...), $this->rules);
    }

    public function hiddenClueCount(): int
    {
        return \count($this->clues) - $this->revealedClueCount();
    }

    /**
     * @return array{placements: list<?int>, crosses: list<int>}
     */
    public function notes(): array
    {
        return $this->notes;
    }

    public function openedAt(): \DateTimeImmutable
    {
        return $this->openedAt;
    }

    public function solvedAt(): ?\DateTimeImmutable
    {
        return $this->solvedAt;
    }

    private function revealedClueCount(): int
    {
        $total = \count($this->clues);
        $given = \count(array_filter($this->clues, static fn (array $clue): bool => ClueKind::from($clue['kind'])->isGiven()));

        return $this->gift ? $total : min($total, $given + \count($this->revealedBy));
    }
}
