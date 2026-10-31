<?php

declare(strict_types=1);

namespace App\Domain\Mystery;

/**
 * @phpstan-type FactData array{kind: string, person?: int, other?: int, room?: int, furniture?: string, number?: int, gender?: string, bound?: string, companion?: true}
 * @phpstan-type ClueData array{kind: string, person?: int, other?: int, room?: int, furniture?: string, number?: int, gender?: string, bound?: string, companion?: true, then?: FactData}
 */
final readonly class Clue
{
    public function __construct(
        public ClueKind $kind,
        public ?int $person = null,
        public ?int $other = null,
        public ?int $room = null,
        public ?Furniture $furniture = null,
        public ?int $number = null,
        public ?Gender $gender = null,
        public ?Bound $bound = null,
        public bool $companion = false,
        public ?Clue $then = null,
    ) {
    }

    /**
     * @param list<int>    $cells
     * @param list<Gender> $genders
     */
    public function holdsFor(Floorplan $plan, array $cells, array $genders): bool
    {
        if (!$this->holds($plan, $cells, $genders, $this->person)) {
            return false;
        }
        if (null === $this->then) {
            return true;
        }

        return $this->then->holds($plan, $cells, $genders, $this->then->companion ? $this->companionOf($plan, $cells, $this->person()) : $this->then->person);
    }

    public function holdsAt(Floorplan $plan, int $cell): bool
    {
        return match ($this->kind) {
            ClueKind::InRoom => $plan->roomOf($cell) === $this->room(),
            ClueKind::OnFurniture => $plan->furniture[$cell] === $this->furniture(),
            ClueKind::BesideFurniture => $plan->isBeside($cell, $this->furniture()),
            ClueKind::InRow => intdiv($cell, $plan->size) === $this->number(),
            ClueKind::InColumn => $cell % $plan->size === $this->number(),
            ClueKind::GridCorner => \in_array(intdiv($cell, $plan->size), [0, $plan->size - 1], true) && \in_array($cell % $plan->size, [0, $plan->size - 1], true),
            ClueKind::RoomCorner => $plan->isRoomCorner($cell),
            ClueKind::RoomHas => $plan->furnishes($plan->roomOf($cell), $this->furniture()),
            ClueKind::NotBeside => !$plan->isBeside($cell, $this->furniture()),
            ClueKind::NorthOfFurniture, ClueKind::SouthOfFurniture, ClueKind::EastOfFurniture, ClueKind::WestOfFurniture => $this->liesBeyondFurniture($plan, $cell),
            ClueKind::FurnitureNorth, ClueKind::FurnitureSouth, ClueKind::FurnitureEast, ClueKind::FurnitureWest => $this->pointsAtFurniture($plan, $cell),
            default => throw new \LogicException(\sprintf('A %s clue is not about a single square.', $this->kind->value)),
        };
    }

    public function square(): ?self
    {
        return match (true) {
            $this->kind->describesASquare() => $this,
            ClueKind::OnlyOn === $this->kind => new self(ClueKind::OnFurniture, furniture: $this->furniture),
            ClueKind::SomeoneOn === $this->kind, ClueKind::SomeoneBeside === $this->kind => new self(ClueKind::RoomHas, furniture: $this->furniture),
            default => null,
        };
    }

    public function person(): int
    {
        return $this->person ?? throw new \LogicException(\sprintf('A %s clue names no person.', $this->kind->value));
    }

    public function other(): int
    {
        return $this->other ?? throw new \LogicException(\sprintf('A %s clue names no other person.', $this->kind->value));
    }

    public function room(): int
    {
        return $this->room ?? throw new \LogicException(\sprintf('A %s clue names no room.', $this->kind->value));
    }

    public function furniture(): Furniture
    {
        return $this->furniture ?? throw new \LogicException(\sprintf('A %s clue names no furniture.', $this->kind->value));
    }

    public function number(): int
    {
        return $this->number ?? throw new \LogicException(\sprintf('A %s clue holds no number.', $this->kind->value));
    }

    public function bound(): Bound
    {
        return $this->bound ?? throw new \LogicException(\sprintf('A %s clue holds no bound.', $this->kind->value));
    }

    public function gender(): Gender
    {
        return $this->gender ?? throw new \LogicException(\sprintf('A %s clue names no gender.', $this->kind->value));
    }

    /**
     * @return ClueData
     */
    public function toArray(): array
    {
        return array_filter([
            'kind' => $this->kind->value,
            'person' => $this->person,
            'other' => $this->other,
            'room' => $this->room,
            'furniture' => $this->furniture?->value,
            'number' => $this->number,
            'gender' => $this->gender?->value,
            'bound' => $this->bound?->value,
            'companion' => $this->companion ?: null,
            'then' => $this->then?->toArray(),
        ], static fn (mixed $value): bool => null !== $value);
    }

    /**
     * @param ClueData|FactData $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            ClueKind::from($data['kind']),
            $data['person'] ?? null,
            $data['other'] ?? null,
            $data['room'] ?? null,
            isset($data['furniture']) ? Furniture::from($data['furniture']) : null,
            $data['number'] ?? null,
            isset($data['gender']) ? Gender::from($data['gender']) : null,
            isset($data['bound']) ? Bound::from($data['bound']) : null,
            $data['companion'] ?? false,
            isset($data['then']) ? self::fromArray($data['then']) : null,
        );
    }

    /**
     * @param list<int>    $cells
     * @param list<Gender> $genders
     */
    private function holds(Floorplan $plan, array $cells, array $genders, ?int $person): bool
    {
        if ($this->kind->describesARoom()) {
            return match ($this->kind) {
                ClueKind::EmptyRoom => 0 === $this->headCount($plan, $cells),
                ClueKind::RoomAtLeast => $this->headCount($plan, $cells) >= $this->number(),
                default => $this->headCount($plan, $cells) === $this->number(),
            };
        }
        if ($this->kind->isRule()) {
            return $this->holdsOnTheBoard($plan, $cells, $genders);
        }
        if (null === $person) {
            return false;
        }

        return match ($this->kind) {
            ClueKind::SameRoom => $plan->roomOf($cells[$person]) === $plan->roomOf($cells[$this->other()]),
            ClueKind::NorthOf => intdiv($cells[$person], $plan->size) < intdiv($cells[$this->other()], $plan->size),
            ClueKind::WestOf => $cells[$person] % $plan->size < $cells[$this->other()] % $plan->size,
            ClueKind::NorthBy => intdiv($cells[$this->other()], $plan->size) - intdiv($cells[$person], $plan->size) === $this->number(),
            ClueKind::WestBy => $cells[$this->other()] % $plan->size - $cells[$person] % $plan->size === $this->number(),
            ClueKind::AloneWithMurderer => null !== $plan->murdererIn($cells, $person),
            ClueKind::Alone => 1 === \count(array_keys(array_map($plan->roomOf(...), $cells), $plan->roomOf($cells[$person]), true)),
            ClueKind::AloneWith => \in_array($this->companionOf($plan, $cells, $person), array_keys($genders, $this->gender(), true), true),
            ClueKind::SameDiagonal => abs(intdiv($cells[$person], $plan->size) - intdiv($cells[$this->other()], $plan->size)) === abs($cells[$person] % $plan->size - $cells[$this->other()] % $plan->size),
            ClueKind::SomeoneOn => array_any($this->roommates($plan, $cells, $person), fn (int $other): bool => $genders[$other] === $this->gender() && $plan->furniture[$cells[$other]] === $this->furniture()),
            ClueKind::SomeoneBeside => array_any($this->roommates($plan, $cells, $person), fn (int $other): bool => $plan->isBeside($cells[$other], $this->furniture())),
            ClueKind::OnlyOn => $plan->furniture[$cells[$person]] === $this->furniture()
                && 1 === \count(array_filter($cells, fn (int $cell): bool => $plan->furniture[$cell] === $this->furniture())),
            default => $this->holdsAt($plan, $cells[$person]),
        };
    }

    /**
     * @param list<int>    $cells
     * @param list<Gender> $genders
     */
    private function holdsOnTheBoard(Floorplan $plan, array $cells, array $genders): bool
    {
        $heads = array_count_values(array_map($plan->roomOf(...), $cells));
        $seated = array_keys(array_filter($cells, fn (int $cell): bool => $plan->furniture[$cell] === $this->furniture));

        return match ($this->kind) {
            ClueKind::SeatCount => $this->bound()->admits(\count($seated), $this->number()),
            ClueKind::EveryRoomOccupied => \count($heads) === \count($plan->kinds),
            ClueKind::RoomCap => max([0, ...$heads]) <= $this->number(),
            ClueKind::GenderSeated => \count(array_intersect($seated, array_keys($genders, $this->gender(), true))) === $this->number(),
            ClueKind::Pairing => array_all(array_keys($genders, $this->gender(), true), fn (int $person): bool => array_any(
                $this->roommates($plan, $cells, $person),
                fn (int $other): bool => $genders[$other] !== $this->gender(),
            )),
            default => throw new \LogicException(\sprintf('A %s clue is not a rule of the board.', $this->kind->value)),
        };
    }

    /**
     * @param list<int> $cells
     */
    private function companionOf(Floorplan $plan, array $cells, int $person): ?int
    {
        $others = $this->roommates($plan, $cells, $person);

        return 1 === \count($others) ? $others[0] : null;
    }

    /**
     * @param list<int> $cells
     *
     * @return list<int>
     */
    private function roommates(Floorplan $plan, array $cells, int $person): array
    {
        $room = $plan->roomOf($cells[$person]);

        return array_keys(array_filter($cells, static fn (int $cell, int $other): bool => $other !== $person && $plan->roomOf($cell) === $room, \ARRAY_FILTER_USE_BOTH));
    }

    /**
     * @param list<int> $cells
     */
    private function headCount(Floorplan $plan, array $cells): int
    {
        return \count(array_filter($cells, fn (int $cell): bool => $plan->roomOf($cell) === $this->room()));
    }

    private function liesBeyondFurniture(Floorplan $plan, int $cell): bool
    {
        $row = intdiv($cell, $plan->size);
        $column = $cell % $plan->size;
        foreach ($plan->furniture as $spot => $piece) {
            if ($piece === $this->furniture() && match ($this->kind) {
                ClueKind::NorthOfFurniture => intdiv($spot, $plan->size) > $row,
                ClueKind::SouthOfFurniture => intdiv($spot, $plan->size) < $row,
                ClueKind::EastOfFurniture => $spot % $plan->size < $column,
                default => $spot % $plan->size > $column,
            }) {
                return true;
            }
        }

        return false;
    }

    private function pointsAtFurniture(Floorplan $plan, int $cell): bool
    {
        [$rows, $columns] = $this->kind->offset();
        $next = $plan->shifted($cell, $rows * $this->number(), $columns * $this->number());

        return null !== $next && $plan->furniture[$next] === $this->furniture();
    }
}
