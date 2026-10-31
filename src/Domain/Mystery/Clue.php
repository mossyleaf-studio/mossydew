<?php

declare(strict_types=1);

namespace App\Domain\Mystery;

final readonly class Clue
{
    public function __construct(
        public ClueKind $kind,
        public ?int $person = null,
        public ?int $other = null,
        public ?int $room = null,
        public ?Furniture $furniture = null,
        public ?int $number = null,
    ) {
    }

    /**
     * @param list<int> $cells
     */
    public function holdsFor(Floorplan $plan, array $cells): bool
    {
        return match ($this->kind) {
            ClueKind::InRoom => $plan->roomOf($cells[$this->person()]) === $this->room(),
            ClueKind::OnFurniture => $plan->furniture[$cells[$this->person()]] === $this->furniture(),
            ClueKind::BesideFurniture => $plan->isBeside($cells[$this->person()], $this->furniture()),
            ClueKind::InRow => intdiv($cells[$this->person()], $plan->size) === $this->number(),
            ClueKind::InColumn => $cells[$this->person()] % $plan->size === $this->number(),
            ClueKind::SameRoom => $plan->roomOf($cells[$this->person()]) === $plan->roomOf($cells[$this->other()]),
            ClueKind::NorthOf => intdiv($cells[$this->person()], $plan->size) < intdiv($cells[$this->other()], $plan->size),
            ClueKind::WestOf => $cells[$this->person()] % $plan->size < $cells[$this->other()] % $plan->size,
            ClueKind::EmptyRoom => 0 === $this->headCount($plan, $cells),
            ClueKind::RoomHolds => $this->headCount($plan, $cells) === $this->number(),
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

    /**
     * @return array{kind: string, person?: int, other?: int, room?: int, furniture?: string, number?: int}
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
        ], static fn (int|string|null $value): bool => null !== $value);
    }

    /**
     * @param array{kind: string, person?: int, other?: int, room?: int, furniture?: string, number?: int} $data
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
        );
    }

    /**
     * @param list<int> $cells
     */
    private function headCount(Floorplan $plan, array $cells): int
    {
        return \count(array_filter($cells, fn (int $cell): bool => $plan->roomOf($cell) === $this->room()));
    }
}
