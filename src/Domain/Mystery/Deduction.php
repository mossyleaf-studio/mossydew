<?php

declare(strict_types=1);

namespace App\Domain\Mystery;

final class Deduction
{
    /** @var array<int, int> */
    private static array $bits = [0];

    private readonly int $size;
    private readonly int $full;
    /** @var list<array<int, int>> */
    private readonly array $roomMask;
    /** @var array<int, array<int, int>> */
    private array $domain;
    /** @var list<array{Clue, int, int}> */
    private array $links = [];
    /** @var array<int, int> */
    private array $capacity = [];
    /** @var array<int, int> */
    private array $least = [];
    /** @var array<int, ?Gender> */
    private array $company = [];
    /** @var list<array{int, array<int, int>}> */
    private array $companions = [];
    /** @var list<array{int, array<int, int>, ?Gender}> */
    private array $presences = [];
    /** @var list<array{array<int, int>, list<int>, int, int}> */
    private array $tallies = [];
    /** @var array<int, int> */
    private array $placed = [];
    private bool $changed = false;

    /**
     * @param list<Gender> $genders
     */
    public function __construct(private readonly Floorplan $plan, private readonly int $victim, private readonly array $genders)
    {
        $size = $plan->size;
        $this->size = $size;
        $this->full = (1 << $size) - 1;
        while (\count(self::$bits) <= $this->full) {
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
        foreach ($roomMask as $mask) {
            $rows = \count(array_filter($mask));
            $columns = self::$bits[array_reduce($mask, static fn (int $union, int $columns): int => $union | $columns, 0)];
            if ($rows < 2 || $columns < 2) {
                $this->narrow($victim, self::inverse($mask));
            }
        }
        $this->propagate();
    }

    /**
     * @param list<Gender> $genders
     * @param list<Clue>   $clues
     */
    public static function of(Floorplan $plan, int $victim, array $genders, array $clues): self
    {
        $deduction = new self($plan, $victim, $genders);
        foreach ($clues as $clue) {
            $deduction->learn($clue);
        }

        return $deduction;
    }

    public function learn(Clue $clue): void
    {
        $this->know($clue);
        $then = $clue->then;
        if (null !== $then && !$then->companion) {
            $this->know($then);
        } elseif (null !== $then && $then->kind->describesASquare()) {
            $this->companions[] = [$clue->person(), $this->where($then)];
        }
        $this->propagate();
    }

    /**
     * @return array<int, int>
     */
    public function placed(): array
    {
        return $this->placed;
    }

    public function freedom(): int
    {
        $freedom = 0;
        foreach ($this->domain as $domain) {
            foreach ($domain as $columns) {
                $freedom += self::$bits[$columns];
            }
        }

        return $freedom;
    }

    public function isSolved(): bool
    {
        return \count($this->placed) === $this->size;
    }

    public function placesTheVictimTooSoon(): bool
    {
        return \in_array($this->victim, \array_slice(array_keys($this->placed), 0, $this->size - 2), true);
    }

    public function endsWithTheVictim(): bool
    {
        return $this->isSolved() && !$this->placesTheVictimTooSoon();
    }

    private function know(Clue $fact): void
    {
        match ($fact->kind) {
            ClueKind::AloneWithMurderer => null,
            ClueKind::EmptyRoom => $this->limit($fact->room(), 0),
            ClueKind::RoomHolds => $this->limit($fact->room(), $fact->number()),
            ClueKind::SeatCount => $this->tallies[] = [$this->seats($fact->furniture()), array_keys($this->domain), Bound::AtMost === $fact->bound ? 0 : $fact->number(), Bound::AtLeast === $fact->bound ? \PHP_INT_MAX : $fact->number()],
            ClueKind::GenderSeated => $this->tallies[] = [$this->seats($fact->furniture()), array_keys($this->genders, $fact->gender(), true), $fact->number(), $fact->number()],
            ClueKind::EveryRoomOccupied => array_push($this->tallies, ...array_map(fn (array $mask): array => [$mask, array_keys($this->domain), 1, \PHP_INT_MAX], $this->roomMask)),
            ClueKind::RoomCap => array_push($this->tallies, ...array_map(fn (array $mask): array => [$mask, array_keys($this->domain), 0, $fact->number()], $this->roomMask)),
            ClueKind::Pairing => array_push($this->presences, ...array_map(fn (int $person): array => [$person, array_fill(0, $this->size, $this->full), Gender::Female === $this->genders[$person] ? Gender::Male : Gender::Female], array_keys($this->genders, $fact->gender(), true))),
            ClueKind::RoomAtLeast => $this->least[$fact->room()] = max($this->least[$fact->room()] ?? 0, $fact->number()),
            ClueKind::SameRoom, ClueKind::NorthOf, ClueKind::WestOf, ClueKind::NorthBy, ClueKind::WestBy, ClueKind::SameDiagonal => $this->links[] = [$fact, $fact->person(), $fact->other()],
            ClueKind::SomeoneOn => $this->presences[] = [$fact->person(), $this->where(new Clue(ClueKind::OnFurniture, furniture: $fact->furniture())), $fact->gender()],
            ClueKind::SomeoneBeside => $this->presences[] = [$fact->person(), $this->where(new Clue(ClueKind::BesideFurniture, furniture: $fact->furniture())), null],
            ClueKind::Alone => $this->company[$fact->person()] = null,
            ClueKind::AloneWith => $this->company[$fact->person()] = $fact->gender(),
            ClueKind::OnlyOn => $this->seat($fact->person(), $this->seats($fact->furniture())),
            default => $this->narrow($fact->person(), $this->where($fact)),
        };
    }

    /**
     * @return array<int, int>
     */
    private function seats(Furniture $furniture): array
    {
        return $this->where(new Clue(ClueKind::OnFurniture, furniture: $furniture));
    }

    /**
     * @param array<int, int> $seats
     */
    private function seat(int $person, array $seats): void
    {
        foreach (array_keys($this->domain) as $someone) {
            $this->narrow($someone, $someone === $person ? $seats : self::inverse($seats));
        }
    }

    /**
     * @return array<int, int>
     */
    private function where(Clue $fact): array
    {
        $mask = array_fill(0, $this->size, 0);
        foreach (array_keys($this->plan->rooms) as $cell) {
            if ($fact->holdsAt($this->plan, $cell)) {
                $mask[intdiv($cell, $this->size)] |= 1 << $cell % $this->size;
            }
        }

        return $mask;
    }

    private function limit(int $room, int $heads): void
    {
        $this->capacity[$room] = $heads;
        if (0 === $heads) {
            foreach (array_keys($this->domain) as $person) {
                $this->narrow($person, self::inverse($this->roomMask[$room]));
            }
        }
    }

    private function propagate(): void
    {
        do {
            $this->changed = false;
            $this->place();
            $this->relate();
            $this->singleOut();
            $this->occupy();
            $this->accompany();
            $this->attend();
            $this->tally();
            $this->count();
        } while ($this->changed);
    }

    private function place(): void
    {
        do {
            $placing = false;
            foreach ([...array_diff(array_keys($this->domain), [$this->victim]), $this->victim] as $person) {
                if (isset($this->placed[$person])) {
                    continue;
                }
                $cell = $this->single($this->domain[$person]);
                if (null === $cell) {
                    continue;
                }
                $this->placed[$person] = $cell;
                $row = intdiv($cell, $this->size);
                $column = $cell % $this->size;
                foreach (array_keys($this->domain) as $other) {
                    if ($other !== $person) {
                        $mask = array_fill(0, $this->size, $this->full & ~(1 << $column));
                        $mask[$row] = 0;
                        $this->narrow($other, $mask);
                    }
                }
                $placing = true;
            }
        } while ($placing);
    }

    private function relate(): void
    {
        foreach ($this->links as [$clue, $person, $other]) {
            $this->narrow($person, $this->allowed($clue, $this->domain[$other], true));
            $this->narrow($other, $this->allowed($clue, $this->domain[$person], false));
        }
    }

    /**
     * @param array<int, int> $theirs
     *
     * @return array<int, int>
     */
    private function allowed(Clue $clue, array $theirs, bool $forward): array
    {
        $rows = array_keys(array_filter($theirs));
        $columns = array_reduce($theirs, static fn (int $union, int $mask): int => $union | $mask, 0);
        if ([] === $rows) {
            return array_fill(0, $this->size, 0);
        }
        $sign = $forward ? 1 : -1;

        return match ($clue->kind) {
            ClueKind::SameRoom => $this->roomsTouching($theirs),
            ClueKind::NorthOf => array_map(static fn (int $row): int => ($forward ? $row < max($rows) : $row > min($rows)) ? -1 : 0, range(0, $this->size - 1)),
            ClueKind::WestOf => array_fill(0, $this->size, $forward ? (1 << \strlen(decbin($columns)) - 1) - 1 : -(($columns & -$columns) << 1)),
            ClueKind::NorthBy => array_map(static fn (int $row): int => 0 !== ($theirs[$row + $sign * $clue->number()] ?? 0) ? -1 : 0, range(0, $this->size - 1)),
            ClueKind::WestBy => array_fill(0, $this->size, $forward ? $columns >> $clue->number() : $columns << $clue->number()),
            ClueKind::SameDiagonal => $this->plan->diagonalsThrough($theirs),
            default => array_fill(0, $this->size, -1),
        };
    }

    /**
     * @param array<int, int> $theirs
     *
     * @return array<int, int>
     */
    private function roomsTouching(array $theirs): array
    {
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

    private function singleOut(): void
    {
        $rowOnce = $rowTwice = $columnOnce = $columnTwice = 0;
        $rowsOf = $columnsOf = [];
        foreach ($this->domain as $person => $domain) {
            if (isset($this->placed[$person])) {
                continue;
            }
            $rows = $columns = 0;
            foreach ($domain as $row => $mask) {
                if (0 !== $mask) {
                    $rows |= 1 << $row;
                    $columns |= $mask;
                }
            }
            $rowsOf[$person] = $rows;
            $columnsOf[$person] = $columns;
            $rowTwice |= $rowOnce & $rows;
            $rowOnce |= $rows;
            $columnTwice |= $columnOnce & $columns;
            $columnOnce |= $columns;
        }
        $rowSingles = $rowOnce & ~$rowTwice;
        $columnSingles = $columnOnce & ~$columnTwice;
        foreach ($rowsOf as $person => $rows) {
            $row = $rows & $rowSingles;
            if (0 !== $row && $row !== $rows && 1 === self::$bits[$row]) {
                $mask = array_fill(0, $this->size, 0);
                $mask[\strlen(decbin($row)) - 1] = $this->full;
                $this->narrow($person, $mask);
            }
            $column = $columnsOf[$person] & $columnSingles;
            if (0 !== $column && $column !== $columnsOf[$person] && 1 === self::$bits[$column]) {
                $this->narrow($person, array_fill(0, $this->size, $column));
            }
        }
    }

    private function occupy(): void
    {
        $union = array_fill(0, $this->size, 0);
        foreach ($this->domain as $person => $domain) {
            if (!isset($this->placed[$person])) {
                foreach ($domain as $row => $mask) {
                    $union[$row] |= $mask;
                }
            }
        }
        $seen = $twice = 0;
        foreach ($union as $mask) {
            $twice |= $seen & $mask;
            $seen |= $mask;
        }
        foreach ($union as $row => $mask) {
            if (1 === self::$bits[$mask]) {
                $claim = array_fill(0, $this->size, $this->full & ~$mask);
                $claim[$row] = $this->full;
                $this->claim($claim);
            }
            $alone = $mask & ~$twice;
            if (1 === self::$bits[$alone]) {
                $claim = array_fill(0, $this->size, $this->full);
                $claim[$row] = $alone;
                $this->claim($claim);
            }
        }
    }

    /**
     * @param array<int, int> $mask
     */
    private function claim(array $mask): void
    {
        foreach (array_keys($this->domain) as $person) {
            if (!isset($this->placed[$person])) {
                $this->narrow($person, $mask);
            }
        }
    }

    private function accompany(): void
    {
        if ([] === $this->company) {
            return;
        }
        $within = array_map($this->roomWithin(...), $this->domain);
        foreach ($this->company as $person => $gender) {
            $wheres = [];
            foreach ($this->companions as [$someone, $where]) {
                if ($someone === $person) {
                    $wheres[] = $where;
                }
            }
            $room = $within[$person];
            if (null !== $room) {
                $this->keepCompany($person, $gender, $room, $wheres);
            }
            foreach ($this->roomMask as $candidate => $mask) {
                if ($this->touches($this->domain[$person], $candidate) && !$this->hosts($candidate, $person, $gender, $within, $wheres)) {
                    $this->narrow($person, self::inverse($mask));
                }
            }
        }
    }

    private function tally(): void
    {
        foreach ($this->tallies as [$mask, $people, $least, $most]) {
            $sure = $maybe = [];
            foreach ($people as $person) {
                if (!$this->meets($this->domain[$person], $mask)) {
                    continue;
                }
                if ($this->meets($this->domain[$person], self::inverse($mask))) {
                    $maybe[] = $person;
                } else {
                    $sure[] = $person;
                }
            }
            foreach ($maybe as $person) {
                if (\count($sure) >= $most) {
                    $this->narrow($person, self::inverse($mask));
                } elseif (\count($sure) + \count($maybe) <= $least) {
                    $this->narrow($person, $mask);
                }
            }
        }
    }

    private function attend(): void
    {
        foreach ($this->presences as [$person, $spot, $gender]) {
            $hosts = array_filter(array_keys($this->domain), fn (int $other): bool => $other !== $person && (null === $gender || $this->genders[$other] === $gender));
            foreach ($this->roomMask as $room => $mask) {
                $there = array_map(static fn (int $inside, int $where): int => $inside & $where, $mask, $spot);
                $able = array_values(array_filter($hosts, fn (int $other): bool => $this->meets($this->domain[$other], $there)));
                if ([] === $able) {
                    $this->narrow($person, self::inverse($mask));
                } elseif (1 === \count($able) && $this->roomWithin($this->domain[$person]) === $room) {
                    $this->narrow($able[0], $there);
                }
            }
        }
    }

    /**
     * @param list<array<int, int>> $wheres
     */
    private function keepCompany(int $person, ?Gender $gender, int $room, array $wheres): void
    {
        if (!isset($this->capacity[$room])) {
            $this->capacity[$room] = null === $gender ? 1 : 2;
            $this->changed = true;
        }
        foreach (array_keys($this->domain) as $other) {
            if ($other === $person) {
                continue;
            }
            if (null === $gender || $this->genders[$other] !== $gender) {
                $this->narrow($other, self::inverse($this->roomMask[$room]));
            }
            foreach ($wheres as $where) {
                $this->narrow($other, array_map(static fn (int $inside, int $there): int => ~$inside | $there, $this->roomMask[$room], $where));
            }
        }
    }

    /**
     * @param array<int, ?int>      $within
     * @param list<array<int, int>> $wheres
     */
    private function hosts(int $room, int $person, ?Gender $gender, array $within, array $wheres): bool
    {
        $heads = null === $gender ? 1 : 2;
        if ($heads !== ($this->capacity[$room] ?? $heads)) {
            return false;
        }
        $inside = 0;
        foreach ($within as $other => $where) {
            if ($other !== $person && $room === $where) {
                if (null === $gender || $this->genders[$other] !== $gender) {
                    return false;
                }
                ++$inside;
            }
        }
        if (null === $gender) {
            return true;
        }
        $spot = $this->roomMask[$room];
        foreach ($wheres as $where) {
            $spot = array_map(static fn (int $inside, int $there): int => $inside & $there, $spot, $where);
        }
        foreach ($this->domain as $other => $domain) {
            if ($other !== $person && $this->genders[$other] === $gender && $this->meets($domain, $spot)) {
                return $inside < 2;
            }
        }

        return false;
    }

    private function count(): void
    {
        $inside = array_fill(0, \count($this->roomMask), 0);
        $within = [];
        foreach ($this->domain as $person => $domain) {
            $room = $this->roomWithin($domain);
            $within[$person] = $room;
            if (null !== $room && $person !== $this->victim) {
                ++$inside[$room];
            }
        }
        $capacity = $this->capacity;
        $victimRoom = $within[$this->victim];
        if (null !== $victimRoom) {
            $capacity[$victimRoom] = 2;
        }
        foreach ($capacity as $room => $heads) {
            $sure = $inside[$room] + (int) ($victimRoom === $room);
            $maybe = [];
            foreach ($this->domain as $person => $domain) {
                if ($within[$person] !== $room && $this->touches($domain, $room)) {
                    $maybe[] = $person;
                }
            }
            foreach ($maybe as $person) {
                if ($sure === $heads) {
                    $this->narrow($person, self::inverse($this->roomMask[$room]));
                } elseif ($sure + \count($maybe) === $heads) {
                    $this->narrow($person, $this->roomMask[$room]);
                }
            }
        }
        foreach ($this->least as $room => $heads) {
            $maybe = array_filter(array_keys($this->domain), fn (int $person): bool => $within[$person] !== $room && $this->touches($this->domain[$person], $room));
            if ($inside[$room] + (int) ($victimRoom === $room) + \count($maybe) === $heads) {
                foreach ($maybe as $person) {
                    $this->narrow($person, $this->roomMask[$room]);
                }
            }
        }
        if (null === $victimRoom) {
            $union = array_fill(0, $this->size, 0);
            foreach ($this->domain as $person => $domain) {
                if ($person !== $this->victim) {
                    foreach ($domain as $row => $mask) {
                        $union[$row] |= $mask;
                    }
                }
            }
            foreach ($this->roomMask as $room => $mask) {
                if ($this->touches($this->domain[$this->victim], $room) && ($inside[$room] >= 2 || 2 !== ($this->capacity[$room] ?? 2) || ($this->least[$room] ?? 0) > 2 || !$this->touches($union, $room))) {
                    $this->narrow($this->victim, self::inverse($mask));
                }
            }
        }
    }

    /**
     * @param array<int, int> $domain
     */
    private function roomWithin(array $domain): ?int
    {
        $cell = null;
        foreach ($domain as $row => $mask) {
            if (0 !== $mask) {
                $cell = $row * $this->size + \strlen(decbin($mask & -$mask)) - 1;
                break;
            }
        }
        if (null === $cell) {
            return null;
        }
        $room = $this->plan->roomOf($cell);
        foreach ($domain as $row => $mask) {
            if (0 !== ($mask & ~$this->roomMask[$room][$row])) {
                return null;
            }
        }

        return $room;
    }

    /**
     * @param array<int, int> $domain
     */
    private function touches(array $domain, int $room): bool
    {
        return $this->meets($domain, $this->roomMask[$room]);
    }

    /**
     * @param array<int, int> $domain
     * @param array<int, int> $mask
     */
    private function meets(array $domain, array $mask): bool
    {
        foreach ($domain as $row => $columns) {
            if (0 !== ($columns & $mask[$row])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, int> $domain
     */
    private function single(array $domain): ?int
    {
        $cell = null;
        foreach ($domain as $row => $mask) {
            if (0 === $mask) {
                continue;
            }
            if (null !== $cell || 1 !== self::$bits[$mask]) {
                return null;
            }
            $cell = $row * $this->size + \strlen(decbin($mask)) - 1;
        }

        return $cell;
    }

    /**
     * @param array<int, int> $mask
     */
    private function narrow(int $person, array $mask): void
    {
        foreach ($this->domain[$person] as $row => $columns) {
            $narrowed = $columns & $mask[$row];
            if ($narrowed !== $columns) {
                $this->domain[$person][$row] = $narrowed;
                $this->changed = true;
            }
        }
    }

    /**
     * @param array<int, int> $mask
     *
     * @return array<int, int>
     */
    private static function inverse(array $mask): array
    {
        return array_map(static fn (int $columns): int => ~$columns, $mask);
    }
}
