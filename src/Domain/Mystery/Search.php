<?php

declare(strict_types=1);

namespace App\Domain\Mystery;

final class Search
{
    private const int SAME = 0;
    private const int NORTH = 1;
    private const int SOUTH = 2;
    private const int WEST = 3;
    private const int EAST = 4;

    public private(set) int $nodes = 0;
    /** @var list<list<int>> */
    public private(set) array $solutions = [];

    /** @var list<int> */
    private static array $bits = [0];

    private readonly int $size;
    /** @var array<int, int> */
    private readonly array $cellRoom;
    /** @var array<int, array<int, int>> */
    private readonly array $roomMask;
    /** @var array<int, array<int, int>> */
    private array $domain;
    /** @var array<int, list<array{int, int}>> */
    private array $links;
    /** @var array<int, int> */
    private array $capacity = [];
    private bool $contradiction = false;
    /** @var array<int, int> */
    private array $cells;
    /** @var array<int, int> */
    private array $heads;
    /** @var array<int, int> */
    private array $closed;
    /** @var array<int, int> */
    private array $crowded;
    private int $freeRows;
    private int $freeColumns;

    /**
     * @param list<Clue> $clues
     */
    public function __construct(private readonly Floorplan $plan, private readonly int $victim, array $clues, private readonly int $budget)
    {
        $size = $plan->size;
        $this->size = $size;
        $this->cellRoom = $plan->rooms;
        while (\count(self::$bits) < 1 << $size) {
            self::$bits[] = self::$bits[\count(self::$bits) >> 1] + (\count(self::$bits) & 1);
        }

        $empty = array_fill(0, $size, 0);
        $roomMask = array_fill(0, \count($plan->kinds), $empty);
        $open = $empty;
        foreach ($plan->rooms as $cell => $room) {
            $roomMask[$room][intdiv($cell, $size)] |= 1 << $cell % $size;
            if (!$plan->blocks($cell)) {
                $open[intdiv($cell, $size)] |= 1 << $cell % $size;
            }
        }
        $this->roomMask = $roomMask;
        $this->domain = array_fill(0, $size, $open);
        $this->links = array_fill(0, $size, []);
        foreach ($clues as $clue) {
            $this->learn($clue);
        }
        $this->spareTheVictim();

        $this->cells = array_fill(0, $size, -1);
        $this->heads = array_fill(0, \count($plan->kinds), 0);
        $this->closed = $empty;
        $this->crowded = $empty;
        $this->freeRows = (1 << $size) - 1;
        $this->freeColumns = $this->freeRows;
    }

    public function run(): bool
    {
        if (!$this->contradiction) {
            $this->explore();
        }

        return $this->nodes <= $this->budget;
    }

    private function learn(Clue $clue): void
    {
        match ($clue->kind) {
            ClueKind::InRoom => $this->narrow($clue->person(), $this->roomMask[$clue->room()]),
            ClueKind::OnFurniture => $this->narrow($clue->person(), $this->spots($clue->furniture(), false)),
            ClueKind::BesideFurniture => $this->narrow($clue->person(), $this->spots($clue->furniture(), true)),
            ClueKind::InRow => $this->narrow($clue->person(), array_map(static fn (int $row): int => $row === $clue->number() ? -1 : 0, range(0, $this->size - 1))),
            ClueKind::InColumn => $this->narrow($clue->person(), array_fill(0, $this->size, 1 << $clue->number())),
            ClueKind::SameRoom => $this->link($clue->person(), self::SAME, self::SAME, $clue->other()),
            ClueKind::NorthOf => $this->link($clue->person(), self::NORTH, self::SOUTH, $clue->other()),
            ClueKind::WestOf => $this->link($clue->person(), self::WEST, self::EAST, $clue->other()),
            ClueKind::EmptyRoom => $this->limit($clue->room(), 0),
            ClueKind::RoomHolds => $this->limit($clue->room(), $clue->number()),
        };
    }

    /**
     * @param array<int, int> $mask
     */
    private function narrow(int $person, array $mask): void
    {
        foreach ($mask as $row => $columns) {
            $this->domain[$person][$row] &= $columns;
        }
    }

    /**
     * @return array<int, int>
     */
    private function spots(Furniture $furniture, bool $beside): array
    {
        $mask = array_fill(0, $this->size, 0);
        foreach ($this->plan->furniture as $cell => $piece) {
            if ($piece !== $furniture) {
                continue;
            }
            foreach ($beside ? Floorplan::neighbours($this->size, $cell) : [$cell] as $spot) {
                $mask[intdiv($spot, $this->size)] |= 1 << $spot % $this->size;
            }
        }

        return $mask;
    }

    private function link(int $person, int $way, int $back, int $other): void
    {
        $this->links[$person][] = [$way, $other];
        $this->links[$other][] = [$back, $person];
    }

    private function limit(int $room, int $heads): void
    {
        $this->contradiction = $this->contradiction || ($this->capacity[$room] ?? $heads) !== $heads;
        $this->capacity[$room] = $heads;
        if (0 === $heads) {
            foreach (array_keys($this->domain) as $person) {
                $this->narrow($person, array_map(static fn (int $columns): int => ~$columns, $this->roomMask[$room]));
            }
        }
    }

    private function spareTheVictim(): void
    {
        foreach ($this->roomMask as $room => $mask) {
            $rows = \count(array_filter($mask));
            $columns = self::$bits[array_reduce($mask, static fn (int $union, int $columns): int => $union | $columns, 0)];
            if ($rows < 2 || $columns < 2 || 2 !== ($this->capacity[$room] ?? 2)) {
                $this->narrow($this->victim, array_map(static fn (int $columns): int => ~$columns, $mask));
            }
        }
    }

    private function explore(): bool
    {
        if (++$this->nodes > $this->budget) {
            return false;
        }
        if (0 === $this->freeRows) {
            if ($this->settled()) {
                $this->solutions[] = array_values($this->cells);
            }

            return \count($this->solutions) < 2;
        }
        $options = $this->options();

        return null === $options || $this->branch($options);
    }

    /**
     * @return array<int, non-empty-array<int, int>>|null
     */
    private function options(): ?array
    {
        $open = [];
        $openToVictim = [];
        foreach ($this->closed as $row => $closed) {
            $free = 0 === ($this->freeRows >> $row & 1) ? 0 : $this->freeColumns;
            $open[] = $free & ~$closed;
            $openToVictim[] = $free & ~$this->crowded[$row];
        }
        $options = [];
        foreach ($this->domain as $person => $domain) {
            if ($this->cells[$person] < 0) {
                $choices = $this->narrowed($domain, $person === $this->victim ? $openToVictim : $open);
                if ([] === $choices) {
                    return null;
                }
                $options[$person] = $choices;
            }
        }

        do {
            $changed = false;
            foreach (array_keys($options) as $person) {
                foreach ($this->links[$person] as [$way, $partner]) {
                    $cell = $this->cells[$partner];
                    $theirs = $cell < 0 ? $options[$partner] : [intdiv($cell, $this->size) => 1 << $cell % $this->size];
                    $narrowed = $this->narrowed($options[$person], $this->allowedBy($way, $theirs));
                    if ([] === $narrowed) {
                        return null;
                    }
                    $changed = $changed || $narrowed !== $options[$person];
                    $options[$person] = $narrowed;
                }
            }
        } while ($changed);

        return $options;
    }

    /**
     * @param array<int, int> $choices
     * @param array<int, int> $allowed
     *
     * @return array<int, int>
     */
    private function narrowed(array $choices, array $allowed): array
    {
        $narrowed = [];
        foreach ($choices as $row => $columns) {
            if (0 !== ($columns & $allowed[$row])) {
                $narrowed[$row] = $columns & $allowed[$row];
            }
        }

        return $narrowed;
    }

    /**
     * @param non-empty-array<int, int> $theirs
     *
     * @return array<int, int>
     */
    private function allowedBy(int $way, array $theirs): array
    {
        if (self::SAME === $way) {
            $allowed = array_fill(0, $this->size, 0);
            foreach ($this->roomMask as $room) {
                foreach ($theirs as $row => $columns) {
                    if (0 !== ($columns & $room[$row])) {
                        foreach ($room as $line => $cells) {
                            $allowed[$line] |= $cells;
                        }
                        break;
                    }
                }
            }

            return $allowed;
        }
        if (self::NORTH === $way || self::SOUTH === $way) {
            $allowed = [];
            for ($row = 0; $row < $this->size; ++$row) {
                $allowed[] = (self::NORTH === $way ? $row < array_key_last($theirs) : $row > array_key_first($theirs)) ? -1 : 0;
            }

            return $allowed;
        }
        $columns = array_reduce($theirs, static fn (int $union, int $mask): int => $union | $mask, 0);

        return array_fill(0, $this->size, self::WEST === $way ? (1 << \strlen(decbin($columns)) - 1) - 1 : -(($columns & -$columns) << 1));
    }

    /**
     * @param array<int, non-empty-array<int, int>> $options
     */
    private function branch(array $options): bool
    {
        $bits = self::$bits;
        $rowCount = array_fill(0, $this->size, 0);
        $reach = $rowCount;
        $once = 0;
        $twice = 0;
        $rowsOf = [];
        $columnsOf = [];
        $best = \PHP_INT_MAX;
        $bestPerson = -1;
        foreach ($options as $person => $choices) {
            $count = 0;
            $rowsOf[$person] = 0;
            $columnsOf[$person] = 0;
            foreach ($choices as $row => $columns) {
                $count += $bits[$columns];
                $rowCount[$row] += $bits[$columns];
                $twice |= $once & $columns;
                $once |= $columns;
                $reach[$row] |= $columns;
                $rowsOf[$person] |= 1 << $row;
                $columnsOf[$person] |= $columns;
            }
            if ($count < $best) {
                $best = $count;
                $bestPerson = $person;
            }
        }
        if (!self::matched($rowsOf) || !self::matched($columnsOf) || !$this->feasible($reach)) {
            return true;
        }

        $single = $this->freeColumns & $once & ~$twice;
        if ($best > 1 && 0 !== $single) {
            $column = \strlen(decbin($single & -$single)) - 1;
            foreach ($options as $person => $choices) {
                foreach ($choices as $row => $columns) {
                    if (0 !== ($columns >> $column & 1)) {
                        return $this->descend($person, $row, $column);
                    }
                }
            }
        }
        $bestRow = -1;
        foreach ($rowCount as $row => $count) {
            if (0 !== $count && $count < $best) {
                $best = $count;
                $bestRow = $row;
            }
        }
        if ($bestRow >= 0) {
            foreach ($options as $person => $choices) {
                if (!$this->tryEach($person, $bestRow, $choices[$bestRow] ?? 0)) {
                    return false;
                }
            }

            return true;
        }
        foreach ($options[$bestPerson] as $row => $columns) {
            if (!$this->tryEach($bestPerson, $row, $columns)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<int, int> $reach
     */
    private function feasible(array $reach): bool
    {
        $needs = [];
        foreach ($this->capacity as $room => $capacity) {
            $needs[$room] = $capacity - $this->heads[$room];
        }
        $victimCell = $this->cells[$this->victim];
        if ($victimCell >= 0) {
            $needs[$this->cellRoom[$victimCell]] = 2 - $this->heads[$this->cellRoom[$victimCell]];
        }

        $total = 0;
        $allRows = 0;
        $allColumns = 0;
        foreach ($needs as $room => $need) {
            if ($need <= 0) {
                continue;
            }
            $rows = 0;
            $columns = 0;
            foreach ($this->roomMask[$room] as $row => $mask) {
                if (0 !== ($reach[$row] & $mask)) {
                    $rows |= 1 << $row;
                    $columns |= $reach[$row] & $mask;
                }
            }
            if (self::$bits[$rows] < $need || self::$bits[$columns] < $need) {
                return false;
            }
            $total += $need;
            $allRows |= $rows;
            $allColumns |= $columns;
        }

        return self::$bits[$allRows] >= $total && self::$bits[$allColumns] >= $total;
    }

    /**
     * @param array<int, int> $masks
     */
    private static function matched(array $masks): bool
    {
        $owners = [];
        foreach (array_keys($masks) as $person) {
            $seen = 0;
            if (!self::augment($person, $masks, $owners, $seen)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<int, int> $masks
     * @param array<int, int> $owners
     */
    private static function augment(int $person, array $masks, array &$owners, int &$seen): bool
    {
        $free = $masks[$person] & ~$seen;
        while (0 !== $free) {
            $bit = $free & -$free;
            $seen |= $bit;
            if (!isset($owners[$bit]) || self::augment($owners[$bit], $masks, $owners, $seen)) {
                $owners[$bit] = $person;

                return true;
            }
            $free &= ~$seen;
        }

        return false;
    }

    private function tryEach(int $person, int $row, int $columns): bool
    {
        for ($column = 0; 0 !== $columns; ++$column, $columns >>= 1) {
            if (0 !== ($columns & 1) && !$this->descend($person, $row, $column)) {
                return false;
            }
        }

        return true;
    }

    private function descend(int $person, int $row, int $column): bool
    {
        $cell = $row * $this->size + $column;
        $room = $this->cellRoom[$cell];
        $closed = $this->closed;
        $crowded = $this->crowded;
        $this->cells[$person] = $cell;
        $this->freeRows ^= 1 << $row;
        $this->freeColumns ^= 1 << $column;
        $heads = ++$this->heads[$room];
        $victimCell = $this->cells[$this->victim];
        if ($heads === ($this->capacity[$room] ?? -1) || (2 === $heads && $victimCell >= 0 && $this->cellRoom[$victimCell] === $room)) {
            $this->shut($room, true);
        } elseif (2 === $heads && $victimCell < 0) {
            $this->shut($room, false);
        }

        $going = $this->explore();

        --$this->heads[$room];
        $this->freeColumns ^= 1 << $column;
        $this->freeRows ^= 1 << $row;
        $this->cells[$person] = -1;
        $this->closed = $closed;
        $this->crowded = $crowded;

        return $going;
    }

    private function shut(int $room, bool $toEveryone): void
    {
        foreach ($this->roomMask[$room] as $row => $mask) {
            $this->crowded[$row] |= $mask;
            if ($toEveryone) {
                $this->closed[$row] |= $mask;
            }
        }
    }

    private function settled(): bool
    {
        foreach ($this->capacity as $room => $capacity) {
            if ($this->heads[$room] !== $capacity) {
                return false;
            }
        }

        return 2 === $this->heads[$this->cellRoom[$this->cells[$this->victim]]];
    }
}
