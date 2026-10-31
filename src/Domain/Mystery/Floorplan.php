<?php

declare(strict_types=1);

namespace App\Domain\Mystery;

final readonly class Floorplan
{
    /**
     * @param list<int>        $rooms
     * @param list<RoomKind>   $kinds
     * @param list<?Furniture> $furniture
     */
    public function __construct(
        public int $size,
        public array $rooms,
        public array $kinds,
        public array $furniture,
    ) {
    }

    public function roomOf(int $cell): int
    {
        return $this->rooms[$cell];
    }

    public function blocks(int $cell): bool
    {
        return false === $this->furniture[$cell]?->canHoldSomeone();
    }

    public function spareBlocks(float $share): int
    {
        return (int) round($share * \count($this->rooms)) - \count(array_filter(array_keys($this->furniture), $this->blocks(...)));
    }

    public function isBeside(int $cell, Furniture $furniture): bool
    {
        foreach (self::neighbours($this->size, $cell) as $neighbour) {
            if ($furniture === $this->furniture[$neighbour] && $this->rooms[$neighbour] === $this->rooms[$cell]) {
                return true;
            }
        }

        return false;
    }

    public function furnishes(int $room, Furniture $furniture): bool
    {
        foreach ($this->furniture as $cell => $piece) {
            if ($furniture === $piece && $this->rooms[$cell] === $room) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, int> $rows
     *
     * @return list<int>
     */
    public function diagonalsThrough(array $rows): array
    {
        $diagonals = array_fill(0, $this->size, 0);
        foreach ($rows as $row => $columns) {
            foreach (array_keys($diagonals) as $line) {
                $shift = abs($line - $row);
                $diagonals[$line] |= ($columns << $shift | $columns >> $shift) & (1 << $this->size) - 1;
            }
        }

        return $diagonals;
    }

    public function isRoomCorner(int $cell): bool
    {
        $walled = function (int $rows, int $columns) use ($cell): bool {
            $next = $this->shifted($cell, $rows, $columns);

            return null === $next || $this->rooms[$next] !== $this->rooms[$cell];
        };

        return ($walled(-1, 0) || $walled(1, 0)) && ($walled(0, -1) || $walled(0, 1));
    }

    public function with(int $cell, Furniture $piece): self
    {
        $furniture = $this->furniture;
        $furniture[$cell] = $piece;

        return new self($this->size, $this->rooms, $this->kinds, array_values($furniture));
    }

    public function shifted(int $cell, int $rows, int $columns): ?int
    {
        $row = intdiv($cell, $this->size) + $rows;
        $column = $cell % $this->size + $columns;

        return $row >= 0 && $row < $this->size && $column >= 0 && $column < $this->size ? $row * $this->size + $column : null;
    }

    /**
     * @return list<int>
     */
    public static function neighbours(int $size, int $cell): array
    {
        $row = intdiv($cell, $size);
        $column = $cell % $size;

        return array_values(array_filter([
            $row > 0 ? $cell - $size : null,
            $row < $size - 1 ? $cell + $size : null,
            $column > 0 ? $cell - 1 : null,
            $column < $size - 1 ? $cell + 1 : null,
        ], static fn (?int $neighbour): bool => null !== $neighbour));
    }

    /**
     * @param list<int> $cells
     */
    public function murdererIn(array $cells, int $victim): ?int
    {
        $room = $this->rooms[$cells[$victim]];
        $companions = array_keys(array_filter(
            $cells,
            fn (int $cell, int $person): bool => $person !== $victim && $this->rooms[$cell] === $room,
            \ARRAY_FILTER_USE_BOTH,
        ));

        return 1 === \count($companions) ? $companions[0] : null;
    }

    /**
     * @return array{size: int, rooms: list<int>, kinds: list<string>, furniture: list<?string>}
     */
    public function toArray(): array
    {
        return [
            'size' => $this->size,
            'rooms' => $this->rooms,
            'kinds' => array_map(static fn (RoomKind $kind): string => $kind->value, $this->kinds),
            'furniture' => array_map(static fn (?Furniture $piece): ?string => $piece?->value, $this->furniture),
        ];
    }

    /**
     * @param array{size: int, rooms: list<int>, kinds: list<string>, furniture: list<?string>} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['size'],
            $data['rooms'],
            array_map(RoomKind::from(...), $data['kinds']),
            array_map(static fn (?string $piece): ?Furniture => null === $piece ? null : Furniture::from($piece), $data['furniture']),
        );
    }
}
