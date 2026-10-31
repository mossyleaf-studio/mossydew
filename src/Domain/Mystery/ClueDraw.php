<?php

declare(strict_types=1);

namespace App\Domain\Mystery;

use Random\Randomizer;

final readonly class ClueDraw
{
    public const int ATTEMPTS = 3;
    public const float BLOCKING = 0.14;
    private const int RULE_TRIES = 6;
    private const int TRIES = 40;
    private const float FURNITURE_SHARE = 0.3;
    private const array DIRECTIONS = [ClueKind::FurnitureNorth, ClueKind::FurnitureSouth, ClueKind::FurnitureEast, ClueKind::FurnitureWest];

    public function __construct(private Randomizer $randomizer)
    {
    }

    /**
     * @param list<Gender> $genders
     * @param list<int>    $solution
     *
     * @return ?array{Floorplan, list<Clue>, list<Clue>}
     */
    public function draw(Floorplan $plan, int $victim, array $genders, array $solution): ?array
    {
        for ($attempt = 0; $attempt < self::ATTEMPTS; ++$attempt) {
            $drawn = $this->chain($plan, $victim, $genders, $solution);
            if (null !== $drawn) {
                return $drawn;
            }
        }

        return null;
    }

    /**
     * @param list<Gender> $genders
     * @param list<int>    $solution
     *
     * @return ?array{Floorplan, list<Clue>, list<Clue>}
     */
    private function chain(Floorplan $plan, int $victim, array $genders, array $solution): ?array
    {
        $clues = [new Clue(ClueKind::AloneWithMurderer, person: $victim)];
        $rules = $this->rules($plan, $victim, $genders, $solution, Deduction::of($plan, $victim, $genders, $clues));
        $deduction = Deduction::of($plan, $victim, $genders, [...$rules, ...$clues]);
        $facts = $this->facts($plan, $victim, $genders, $solution, $rules);
        while (\count($clues) < $plan->size) {
            $slack = $plan->size - \count($clues) > $plan->size - 1 - \count($deduction->placed());
            $next = $this->next($plan, $deduction, $facts, $clues, $slack);
            if (null === $next) {
                $next = $this->furnishFor($deduction, $plan, $victim, $genders, $solution, $rules, $clues);
                if (null === $next) {
                    return null;
                }
                $plan = $next[2];
                $facts = $this->facts($plan, $victim, $genders, $solution, $rules);
            }
            [$clue, $deduction] = $next;
            $clues[] = $clue;
        }

        return $deduction->endsWithTheVictim() ? [$plan, $rules, $clues] : null;
    }

    /**
     * @param list<Gender> $genders
     * @param list<int>    $solution
     *
     * @return list<Clue>
     */
    private function rules(Floorplan $plan, int $victim, array $genders, array $solution, Deduction $deduction): array
    {
        $byKind = [];
        foreach ($this->ruleCandidates($plan, $victim, $genders, $solution) as $rule) {
            $byKind[$rule->kind->value][] = $rule;
        }
        $rules = [];
        $wanted = [0, 1, 1, 1, 2, 2, 2][$this->randomizer->getInt(0, 6)];
        foreach ($this->randomizer->shuffleArray(array_values($byKind)) as $group) {
            if (\count($rules) >= $wanted) {
                break;
            }
            $fallback = null;
            $group = array_filter($group, static fn (Clue $rule): bool => !array_any($rules, static fn (Clue $known): bool => self::repeats($rule, $known) || self::repeats($known, $rule)));
            foreach (\array_slice($this->randomizer->shuffleArray($group), 0, self::RULE_TRIES) as $rule) {
                $further = clone $deduction;
                $further->learn($rule);
                if ($further->placesTheVictimTooSoon()) {
                    continue;
                }
                if ($further->freedom() < $deduction->freedom()) {
                    $fallback = null;
                    $rules[] = $rule;
                    $deduction = $further;
                    break;
                }
                $fallback ??= [$rule, $further];
            }
            if (null !== $fallback && 0 === $this->randomizer->getInt(0, 1)) {
                [$rules[], $deduction] = $fallback;
            }
        }

        return $rules;
    }

    private static function repeats(Clue $count, Clue $gendered): bool
    {
        return ClueKind::SeatCount === $count->kind && ClueKind::GenderSeated === $gendered->kind && $count->furniture === $gendered->furniture
            && ((0 === $count->number() && Bound::AtLeast !== $count->bound) || (Bound::AtLeast === $count->bound && $count->number() <= $gendered->number()));
    }

    /**
     * @param list<Gender> $genders
     * @param list<int>    $solution
     *
     * @return list<Clue>
     */
    private function ruleCandidates(Floorplan $plan, int $victim, array $genders, array $solution): array
    {
        $crimeScene = $plan->roomOf($solution[$victim]);
        $heads = array_replace(array_fill(0, \count($plan->kinds), 0), array_count_values(array_map($plan->roomOf(...), $solution)));
        $rules = [];
        foreach ([Furniture::Armchair, Furniture::Sofa, Furniture::Bed] as $seat) {
            $pieces = array_keys($plan->furniture, $seat, true);
            if ([] === $pieces || array_all($pieces, static fn (int $cell): bool => $plan->roomOf($cell) === $crimeScene)) {
                continue;
            }
            $seated = array_keys(array_filter($solution, static fn (int $cell): bool => $plan->furniture[$cell] === $seat));
            $rules[] = new Clue(ClueKind::SeatCount, furniture: $seat, number: \count($seated), bound: Bound::Exactly);
            if ([] !== $seated) {
                $rules[] = new Clue(ClueKind::SeatCount, furniture: $seat, number: \count($seated), bound: Bound::AtLeast);
            }
            if (\count($pieces) > \count($seated)) {
                $rules[] = new Clue(ClueKind::SeatCount, furniture: $seat, number: \count($seated), bound: Bound::AtMost);
            }
            foreach (Gender::cases() as $gender) {
                $rules[] = new Clue(ClueKind::GenderSeated, furniture: $seat, number: \count(array_intersect($seated, array_keys($genders, $gender, true))), gender: $gender);
            }
        }
        if (!\in_array(0, $heads, true)) {
            $rules[] = new Clue(ClueKind::EveryRoomOccupied);
        }
        $most = max(array_diff_key($heads, [$crimeScene => true]) ?: [0]);
        if ($most >= 2) {
            $rules[] = new Clue(ClueKind::RoomCap, number: $most);
        }
        foreach (array_keys($heads, 0, true) as $room) {
            $rules[] = new Clue(ClueKind::EmptyRoom, room: $room);
        }
        foreach (Gender::cases() as $gender) {
            $pairing = new Clue(ClueKind::Pairing, gender: $gender);
            if ($genders[$victim] !== $gender && \in_array($gender, $genders, true) && $pairing->holdsFor($plan, $solution, $genders)) {
                $rules[] = $pairing;
            }
        }

        return $rules;
    }

    /**
     * @param array{people: array<int, list<Clue>>, rooms: list<Clue>, companions: array<int, list<Clue>>, homes: array<int, int>} $facts
     * @param list<Clue>                                                                                                           $clues
     *
     * @return ?array{Clue, Deduction}
     */
    private function next(Floorplan $plan, Deduction $deduction, array $facts, array $clues, bool $slack): ?array
    {
        $placed = $deduction->placed();
        $used = [];
        foreach ($clues as $clue) {
            foreach (null === $clue->then ? [$clue] : [$clue, $clue->then] as $fact) {
                $used[$fact->kind->value] = ($used[$fact->kind->value] ?? 0) + 1;
            }
        }
        $anchored = array_flip(array_map(static fn (Clue $clue): int => $clue->person(), $clues));
        $open = [];
        foreach ($facts['people'] as $person => $about) {
            if (!isset($anchored[$person])) {
                array_push($open, ...$about);
            }
        }
        for ($try = 0; $try < self::TRIES; ++$try) {
            $first = $this->pick($open, $used);
            if (null === $first) {
                return null;
            }
            $clue = $this->extend($plan, $first, $facts, $used);
            if (\in_array($clue, $clues, false)) {
                continue;
            }
            $further = clone $deduction;
            $further->learn($clue);
            if (($slack || \count($further->placed()) > \count($placed)) && !$further->placesTheVictimTooSoon()) {
                return [$clue, $further];
            }
        }

        return null;
    }

    /**
     * @param array{people: array<int, list<Clue>>, rooms: list<Clue>, companions: array<int, list<Clue>>, homes: array<int, int>} $facts
     * @param array<string, int>                                                                                                   $used
     */
    private function extend(Floorplan $plan, Clue $first, array $facts, array $used): Clue
    {
        $person = $first->person();
        $roll = $this->randomizer->getInt(1, 100);
        $then = match (true) {
            ClueKind::AloneWith === $first->kind && $roll <= 80 => $this->pick($facts['companions'][$person] ?? [], $used),
            $roll <= 40 => $this->pick(array_values(array_filter($facts['people'][$person], static fn (Clue $fact): bool => $fact->kind !== $first->kind)), $used),
            $roll <= 52 => $this->pick(array_values(array_filter($facts['rooms'], static fn (Clue $fact): bool => ClueKind::RoomAtLeast !== $fact->kind || $fact->room !== $facts['homes'][$person])), $used),
            default => null,
        };

        return null === $then || $this->givesAway($plan, $first, $then) ? $first : new Clue($first->kind, $first->person, $first->other, $first->room, $first->furniture, $first->number, $first->gender, then: $then);
    }

    private function givesAway(Floorplan $plan, Clue $first, Clue $then): bool
    {
        $basis = $first->square();

        if ($then->companion || null === $basis || !$then->kind->describesASquare()) {
            return false;
        }
        $onlyFirst = $onlyThen = false;
        $both = 0;
        foreach (array_keys($plan->rooms) as $cell) {
            $first = $basis->holdsAt($plan, $cell);
            $second = $then->holdsAt($plan, $cell);
            $onlyFirst = $onlyFirst || ($first && !$second);
            $onlyThen = $onlyThen || ($second && !$first);
            $both += (int) ($first && $second && !$plan->blocks($cell));
        }

        return !$onlyFirst || !$onlyThen || $both < 2;
    }

    /**
     * @param list<Clue>         $facts
     * @param array<string, int> $used
     */
    private function pick(array $facts, array $used): ?Clue
    {
        $byKind = [];
        foreach ($facts as $fact) {
            $byKind[$fact->kind->value][] = $fact;
        }
        $total = array_sum($used);
        $furniture = array_sum(array_map(static fn (ClueKind $kind): int => $used[$kind->value] ?? 0, self::DIRECTIONS));
        $weights = [];
        foreach ($byKind as $kind => $group) {
            $kind = ClueKind::from($kind);
            $weights[] = [$group, $kind->pointsAtFurniture() && $furniture > self::FURNITURE_SHARE * $total ? 0 : intdiv(12 * $kind->weight(), 1 + 2 * ($used[$kind->value] ?? 0))];
        }
        $sum = array_sum(array_column($weights, 1));
        if (0 === $sum) {
            return null;
        }
        $roll = $this->randomizer->getInt(1, $sum);
        foreach ($weights as [$group, $weight]) {
            $roll -= $weight;
            if ($roll <= 0) {
                return $group[$this->randomizer->getInt(0, \count($group) - 1)];
            }
        }

        throw new \LogicException('Every weight is already spent.');
    }

    /**
     * @param list<Gender> $genders
     * @param list<int>    $solution
     * @param list<Clue>   $rules
     * @param list<Clue>   $clues
     *
     * @return ?array{Clue, Deduction, Floorplan}
     */
    private function furnishFor(Deduction $deduction, Floorplan $plan, int $victim, array $genders, array $solution, array $rules, array $clues): ?array
    {
        $placed = $deduction->placed();
        $anchored = array_flip(array_map(static fn (Clue $clue): int => $clue->person(), $clues));
        $occupied = array_flip($solution);
        foreach ($this->randomizer->shuffleArray(array_keys($solution)) as $person) {
            if (isset($anchored[$person]) || isset($placed[$person])) {
                continue;
            }
            foreach ($this->randomizer->shuffleArray($this->reach($plan, $solution[$person])) as [$direction, $distance, $spot]) {
                if (null !== $plan->furniture[$spot] || isset($occupied[$spot]) || $this->freeCells($plan, $plan->roomOf($spot)) <= 2 || $plan->spareBlocks(self::BLOCKING) <= 0) {
                    continue;
                }
                $pieces = array_filter($plan->kinds[$plan->roomOf($spot)]->furniture(), static fn (Furniture $piece): bool => !$piece->canHoldSomeone());
                foreach ($this->randomizer->shuffleArray($pieces) as $piece) {
                    $furnished = $plan->with($spot, $piece);
                    $clue = new Clue($direction, person: $person, furniture: $piece, number: $distance);
                    $further = Deduction::of($furnished, $victim, $genders, [...$rules, ...$clues, $clue]);
                    if (\count($further->placed()) > \count($placed) && !$further->placesTheVictimTooSoon() && array_all($clues, static fn (Clue $known): bool => $known->holdsFor($furnished, $solution, $genders))) {
                        return [$clue, $further, $furnished];
                    }
                }
            }
        }

        return null;
    }

    /**
     * @return list<array{ClueKind, int, int}>
     */
    private function reach(Floorplan $plan, int $cell): array
    {
        $squares = [];
        foreach (self::DIRECTIONS as $direction) {
            [$rows, $columns] = $direction->offset();
            for ($distance = 1; null !== $spot = $plan->shifted($cell, $rows * $distance, $columns * $distance); ++$distance) {
                $squares[] = [$direction, $distance, $spot];
            }
        }

        return $squares;
    }

    private function freeCells(Floorplan $plan, int $room): int
    {
        return \count(array_filter(array_keys($plan->rooms, $room, true), static fn (int $cell): bool => !$plan->blocks($cell)));
    }

    /**
     * @param list<Gender> $genders
     * @param list<int>    $solution
     * @param list<Clue>   $rules
     *
     * @return array{people: array<int, list<Clue>>, rooms: list<Clue>, companions: array<int, list<Clue>>, homes: array<int, int>}
     */
    private function facts(Floorplan $plan, int $victim, array $genders, array $solution, array $rules): array
    {
        $size = $plan->size;
        $crimeScene = $plan->roomOf($solution[$victim]);
        $guests = array_fill(0, \count($plan->kinds), []);
        foreach ($solution as $person => $cell) {
            $guests[$plan->roomOf($cell)][] = $person;
        }
        $people = [];
        foreach ($solution as $person => $cell) {
            if ($victim === $person) {
                continue;
            }
            $room = $plan->roomOf($cell);
            $about = [
                new Clue(ClueKind::InRoom, person: $person, room: $room),
                new Clue(ClueKind::InRow, person: $person, number: intdiv($cell, $size)),
                new Clue(ClueKind::InColumn, person: $person, number: $cell % $size),
                new Clue(ClueKind::GridCorner, person: $person),
                new Clue(ClueKind::RoomCorner, person: $person),
            ];
            $about = array_values(array_filter($about, static fn (Clue $fact): bool => $fact->holdsAt($plan, $cell)));
            $seat = $plan->furniture[$cell];
            if (null !== $seat) {
                $about[] = new Clue(ClueKind::OnFurniture, person: $person, furniture: $seat);
                $alone = new Clue(ClueKind::OnlyOn, person: $person, furniture: $seat);
                if ($alone->holdsFor($plan, $solution, $genders)) {
                    $about[] = $alone;
                }
            }
            if ([$person] === $guests[$room]) {
                $about[] = new Clue(ClueKind::Alone, person: $person);
            }
            if (2 === \count($guests[$room]) && $crimeScene !== $room) {
                $about[] = new Clue(ClueKind::AloneWith, person: $person, gender: $genders[array_sum($guests[$room]) - $person]);
            }
            foreach ($this->reach($plan, $cell) as [$direction, $distance, $spot]) {
                $piece = $plan->furniture[$spot];
                if (null !== $piece) {
                    $about[] = new Clue($direction, person: $person, furniture: $piece, number: $distance);
                }
            }
            foreach (Furniture::cases() as $piece) {
                foreach ([ClueKind::NorthOfFurniture, ClueKind::SouthOfFurniture, ClueKind::EastOfFurniture, ClueKind::WestOfFurniture] as $kind) {
                    $beyond = new Clue($kind, person: $person, furniture: $piece);
                    if ($beyond->holdsAt($plan, $cell)) {
                        $about[] = $beyond;
                    }
                }
            }
            foreach (array_filter(Furniture::cases(), static fn (Furniture $piece): bool => $plan->furnishes($room, $piece)) as $piece) {
                $about[] = $plan->isBeside($cell, $piece)
                    ? new Clue(ClueKind::BesideFurniture, person: $person, furniture: $piece)
                    : new Clue(ClueKind::NotBeside, person: $person, furniture: $piece);
                if ($crimeScene !== $room) {
                    $about[] = new Clue(ClueKind::RoomHas, person: $person, furniture: $piece);
                }
            }
            foreach ($solution as $other => $where) {
                if ($victim === $other || $person === $other) {
                    continue;
                }
                if ($room === $plan->roomOf($where)) {
                    $about[] = new Clue(ClueKind::SameRoom, person: $person, other: $other);
                    $seated = $plan->furniture[$where];
                    if (null !== $seated && $crimeScene !== $room) {
                        $about[] = new Clue(ClueKind::SomeoneOn, person: $person, gender: $genders[$other], furniture: $seated);
                    }
                    foreach (array_filter(Furniture::cases(), static fn (Furniture $piece): bool => $crimeScene !== $room && $plan->isBeside($where, $piece)) as $piece) {
                        $about[] = new Clue(ClueKind::SomeoneBeside, person: $person, furniture: $piece);
                    }
                }
                if (abs(intdiv($cell, $size) - intdiv($where, $size)) === abs($cell % $size - $where % $size)) {
                    $about[] = new Clue(ClueKind::SameDiagonal, person: $person, other: $other);
                }
                if (intdiv($cell, $size) < intdiv($where, $size)) {
                    $about[] = new Clue(ClueKind::NorthOf, person: $person, other: $other);
                    $about[] = new Clue(ClueKind::NorthBy, person: $person, other: $other, number: intdiv($where, $size) - intdiv($cell, $size));
                }
                if ($cell % $size < $where % $size) {
                    $about[] = new Clue(ClueKind::WestOf, person: $person, other: $other);
                    $about[] = new Clue(ClueKind::WestBy, person: $person, other: $other, number: $where % $size - $cell % $size);
                }
            }
            $people[$person] = $about;
        }
        $companions = [];
        foreach ($people as $person => $about) {
            $room = $plan->roomOf($solution[$person]);
            if (2 === \count($guests[$room]) && $crimeScene !== $room) {
                $companions[$person] = array_values(array_map(
                    static fn (Clue $fact): Clue => new Clue($fact->kind, furniture: $fact->furniture, number: $fact->number, companion: true),
                    array_filter($people[array_sum($guests[$room]) - $person], static fn (Clue $fact): bool => $fact->kind->describesASquare() && !\in_array($fact->kind, [ClueKind::InRoom, ClueKind::RoomHas], true)),
                ));
            }
        }
        $rooms = [];
        foreach ($guests as $room => $inside) {
            if ($crimeScene !== $room) {
                $rooms[] = [] === $inside ? new Clue(ClueKind::EmptyRoom, room: $room) : new Clue(ClueKind::RoomHolds, room: $room, number: \count($inside));
                for ($heads = 2; $heads <= \count($inside); ++$heads) {
                    $rooms[] = new Clue(ClueKind::RoomAtLeast, room: $room, number: $heads);
                }
            }
        }

        $rooms = array_values(array_filter($rooms, static fn (Clue $fact): bool => !\in_array($fact, $rules, false)));

        return ['people' => $people, 'rooms' => $rooms, 'companions' => $companions, 'homes' => array_map($plan->roomOf(...), $solution)];
    }
}
