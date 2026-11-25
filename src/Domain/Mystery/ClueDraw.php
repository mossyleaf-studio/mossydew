<?php

declare(strict_types=1);

namespace App\Domain\Mystery;

use Random\Randomizer;

final readonly class ClueDraw
{
    public const int ATTEMPTS = 3;
    private const int RULE_TRIES = 6;
    private const int TRIES = 150;
    private const float FURNITURE_SHARE = 0.3;
    private const array SAME_LINE = [[ClueKind::NorthBy, ClueKind::InRow], [ClueKind::InRow, ClueKind::NorthBy], [ClueKind::WestBy, ClueKind::InColumn], [ClueKind::InColumn, ClueKind::WestBy]];

    public function __construct(private Randomizer $randomizer)
    {
    }

    /**
     * @param list<Gender> $genders
     * @param list<int>    $solution
     *
     * @return ?array{list<Clue>, list<Clue>}
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
     * @return ?array{list<Clue>, list<Clue>}
     */
    private function chain(Floorplan $plan, int $victim, array $genders, array $solution): ?array
    {
        $clues = [new Clue(ClueKind::AloneWithMurderer, person: $victim)];
        $rules = $this->rules($plan, $victim, $genders, $solution, Deduction::of($plan, $victim, $genders, $clues));
        $deduction = Deduction::of($plan, $victim, $genders, [...$rules, ...$clues]);
        $facts = $this->facts($plan, $victim, $genders, $solution);
        while (\count($clues) < $plan->size) {
            $slack = $plan->size - \count($clues) > $plan->size - 1 - \count($deduction->placed());
            $next = $this->next($plan, $deduction, $facts, $clues, $slack);
            if (null === $next) {
                return null;
            }
            [$clue, $deduction] = $next;
            $clues[] = $clue;
        }

        return $deduction->endsWithTheVictim() ? [$rules, $clues] : null;
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
            $byKind[$rule->kind->describesARoom() ? 'room' : $rule->kind->value][] = $rule;
        }
        $rules = [];
        $wanted = [0, 1, 1, 1, 2, 2, 2][$this->randomizer->getInt(0, 6)];
        foreach ($this->randomizer->shuffleArray(array_values($byKind)) as $group) {
            if (\count($rules) >= $wanted) {
                break;
            }
            $group = array_filter($group, static fn (Clue $rule): bool => !array_any($rules, static fn (Clue $known): bool => self::repeats($rule, $known) || self::repeats($known, $rule)));
            $picked = $this->ruleOf($group, $deduction);
            if (null !== $picked && ($picked[2] || 0 === $this->randomizer->getInt(0, 1))) {
                [$rules[], $deduction] = $picked;
            }
        }

        return $rules;
    }

    /**
     * @param array<Clue> $group
     *
     * @return ?array{Clue, Deduction, bool}
     */
    private function ruleOf(array $group, Deduction $deduction): ?array
    {
        $fallback = null;
        foreach (\array_slice($this->randomizer->shuffleArray($group), 0, self::RULE_TRIES) as $rule) {
            $further = clone $deduction;
            $further->learn($rule);
            if ($further->placesTheVictimTooSoon()) {
                continue;
            }
            if ($further->freedom() < $deduction->freedom()) {
                return [$rule, $further, true];
            }
            $fallback ??= [$rule, $further, false];
        }

        return $fallback;
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

        return [...self::seatRules($plan, $genders, $solution, $crimeScene), ...self::roomRules($heads, $crimeScene), ...self::pairingRules($plan, $victim, $genders, $solution)];
    }

    /**
     * @param list<Gender> $genders
     * @param list<int>    $solution
     *
     * @return list<Clue>
     */
    private static function seatRules(Floorplan $plan, array $genders, array $solution, int $crimeScene): array
    {
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

        return $rules;
    }

    /**
     * @param array<int, int> $heads
     *
     * @return list<Clue>
     */
    private static function roomRules(array $heads, int $crimeScene): array
    {
        $rules = [];
        if (!\in_array(0, $heads, true)) {
            $rules[] = new Clue(ClueKind::EveryRoomOccupied);
        }
        $most = max(array_diff_key($heads, [$crimeScene => true]) ?: [0]);
        if ($most >= 2) {
            $rules[] = new Clue(ClueKind::RoomCap, number: $most);
        }
        foreach (array_diff_key($heads, [$crimeScene => true]) as $room => $inside) {
            $rules[] = 0 === $inside ? new Clue(ClueKind::EmptyRoom, room: $room) : new Clue(ClueKind::RoomHolds, room: $room, number: $inside);
            for ($least = 2; $least <= $inside; ++$least) {
                $rules[] = new Clue(ClueKind::RoomAtLeast, room: $room, number: $least);
            }
        }

        return $rules;
    }

    /**
     * @param list<Gender> $genders
     * @param list<int>    $solution
     *
     * @return list<Clue>
     */
    private static function pairingRules(Floorplan $plan, int $victim, array $genders, array $solution): array
    {
        $rules = [];
        foreach (Gender::cases() as $gender) {
            $pairing = new Clue(ClueKind::Pairing, gender: $gender);
            if ($genders[$victim] !== $gender && \in_array($gender, $genders, true) && $pairing->holdsFor($plan, $solution, $genders)) {
                $rules[] = $pairing;
            }
        }

        return $rules;
    }

    /**
     * @param array{people: array<int, list<Clue>>, companions: array<int, list<Clue>>} $facts
     * @param list<Clue>                                                                $clues
     *
     * @return ?array{Clue, Deduction}
     */
    private function next(Floorplan $plan, Deduction $deduction, array $facts, array $clues, bool $slack): ?array
    {
        $placed = $deduction->placed();
        $used = self::used($clues);
        $open = self::unanchored($facts['people'], $clues);
        $narrowing = null;
        for ($try = 0; $try < self::TRIES; ++$try) {
            $first = $this->pick($open, $used);
            if (null === $first) {
                break;
            }
            $clue = $this->extend($plan, $first, $facts, $used);
            if (\in_array($clue, $clues, false)) {
                continue;
            }
            $further = clone $deduction;
            $further->learn($clue);
            if ($further->placesTheVictimTooSoon()) {
                continue;
            }
            if ($slack || \count($further->placed()) > \count($placed)) {
                return [$clue, $further];
            }
            if ($further->freedom() < ($narrowing ?? [1 => $deduction])[1]->freedom()) {
                $narrowing = [$clue, $further];
            }
        }

        return $narrowing;
    }

    /**
     * @param list<Clue> $clues
     *
     * @return array<string, int>
     */
    private static function used(array $clues): array
    {
        $used = [];
        foreach ($clues as $clue) {
            foreach (null === $clue->then ? [$clue] : [$clue, $clue->then] as $fact) {
                $used[$fact->kind->value] = ($used[$fact->kind->value] ?? 0) + 1;
            }
        }

        return $used;
    }

    /**
     * @param array<int, list<Clue>> $people
     * @param list<Clue>             $clues
     *
     * @return list<Clue>
     */
    private static function unanchored(array $people, array $clues): array
    {
        $anchored = array_flip(array_map(static fn (Clue $clue): int => $clue->person(), $clues));
        $open = [];
        foreach ($people as $person => $about) {
            if (!isset($anchored[$person])) {
                array_push($open, ...$about);
            }
        }

        return $open;
    }

    /**
     * @param array{people: array<int, list<Clue>>, companions: array<int, list<Clue>>} $facts
     * @param array<string, int>                                                        $used
     */
    private function extend(Floorplan $plan, Clue $first, array $facts, array $used): Clue
    {
        $person = $first->person();
        $roll = $this->randomizer->getInt(1, 100);
        $then = match (true) {
            ClueKind::AloneWith === $first->kind && $roll <= 80 => $this->pick($facts['companions'][$person] ?? [], $used),
            $roll <= 40 => $this->pick(array_values(array_filter($facts['people'][$person], static fn (Clue $fact): bool => $fact->kind !== $first->kind && !\in_array([$first->kind, $fact->kind], self::SAME_LINE, true))), $used),
            default => null,
        };

        return null === $then || self::givesAway($plan, $first, $then) ? $first : new Clue($first->kind, $first->person, $first->other, $first->room, $first->furniture, $first->number, $first->gender, then: $then);
    }

    private static function givesAway(Floorplan $plan, Clue $first, Clue $then): bool
    {
        $basis = $first->square();

        if ($then->companion || null === $basis || !$then->kind->describesASquare()) {
            return false;
        }
        [$onlyFirst, $onlyThen, $both] = self::overlap($plan, $basis, $then);

        return !$onlyFirst || !$onlyThen || $both < 2;
    }

    /**
     * @return array{bool, bool, int}
     */
    private static function overlap(Floorplan $plan, Clue $first, Clue $then): array
    {
        $onlyFirst = $onlyThen = false;
        $both = 0;
        foreach (array_keys($plan->rooms) as $cell) {
            $isFirst = $first->holdsAt($plan, $cell);
            $isThen = $then->holdsAt($plan, $cell);
            $onlyFirst = $onlyFirst || ($isFirst && !$isThen);
            $onlyThen = $onlyThen || ($isThen && !$isFirst);
            $both += (int) ($isFirst && $isThen && !$plan->blocks($cell));
        }

        return [$onlyFirst, $onlyThen, $both];
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
        $furniture = $used[ClueKind::BesideFurniture->value] ?? 0;
        $weights = [];
        foreach ($byKind as $kind => $group) {
            $kind = ClueKind::from($kind);
            $weights[] = [$group, ClueKind::BesideFurniture === $kind && $furniture > self::FURNITURE_SHARE * $total ? 0 : intdiv(12 * $kind->weight(), 1 + 2 * ($used[$kind->value] ?? 0))];
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
     *
     * @return array{people: array<int, list<Clue>>, companions: array<int, list<Clue>>}
     */
    private function facts(Floorplan $plan, int $victim, array $genders, array $solution): array
    {
        $crimeScene = $plan->roomOf($solution[$victim]);
        $guests = array_fill(0, \count($plan->kinds), []);
        foreach ($solution as $person => $cell) {
            $guests[$plan->roomOf($cell)][] = $person;
        }
        $people = [];
        foreach ($solution as $person => $cell) {
            if ($victim !== $person) {
                $people[$person] = [
                    ...self::factsAboutTheSquare($plan, $person, $cell, $genders, $solution),
                    ...self::factsAboutTheRoom($plan, $person, $cell, $genders, $guests, $crimeScene),
                    ...self::factsAboutTheOthers($plan, $person, $cell, $victim, $genders, $solution, $crimeScene),
                ];
            }
        }

        return ['people' => $people, 'companions' => self::companions($plan, $people, $solution, $guests, $crimeScene)];
    }

    /**
     * @param list<Gender> $genders
     * @param list<int>    $solution
     *
     * @return list<Clue>
     */
    private static function factsAboutTheSquare(Floorplan $plan, int $person, int $cell, array $genders, array $solution): array
    {
        $about = [
            new Clue(ClueKind::InRoom, person: $person, room: $plan->roomOf($cell)),
            new Clue(ClueKind::InRow, person: $person, number: intdiv($cell, $plan->size)),
            new Clue(ClueKind::InColumn, person: $person, number: $cell % $plan->size),
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

        return $about;
    }

    /**
     * @param list<Gender>          $genders
     * @param array<int, list<int>> $guests
     *
     * @return list<Clue>
     */
    private static function factsAboutTheRoom(Floorplan $plan, int $person, int $cell, array $genders, array $guests, int $crimeScene): array
    {
        $room = $plan->roomOf($cell);
        $about = [];
        if ([$person] === $guests[$room]) {
            $about[] = new Clue(ClueKind::Alone, person: $person);
        }
        if (2 === \count($guests[$room]) && $crimeScene !== $room) {
            $about[] = new Clue(ClueKind::AloneWith, person: $person, gender: $genders[array_sum($guests[$room]) - $person]);
        }
        foreach (array_filter(Furniture::cases(), static fn (Furniture $piece): bool => $plan->furnishes($room, $piece)) as $piece) {
            $about[] = $plan->isBeside($cell, $piece)
                ? new Clue(ClueKind::BesideFurniture, person: $person, furniture: $piece)
                : new Clue(ClueKind::NotBeside, person: $person, furniture: $piece);
            if ($crimeScene !== $room) {
                $about[] = new Clue(ClueKind::RoomHas, person: $person, furniture: $piece);
            }
        }

        return $about;
    }

    /**
     * @param list<Gender> $genders
     * @param list<int>    $solution
     *
     * @return list<Clue>
     */
    private static function factsAboutTheOthers(Floorplan $plan, int $person, int $cell, int $victim, array $genders, array $solution, int $crimeScene): array
    {
        $about = [];
        foreach ($solution as $other => $where) {
            if ($victim !== $other && $person !== $other) {
                array_push($about, ...self::roommateFacts($plan, $person, $cell, $other, $where, $genders, $crimeScene), ...self::lineFacts($plan->size, $person, $cell, $other, $where));
            }
        }

        return $about;
    }

    /**
     * @param list<Gender> $genders
     *
     * @return list<Clue>
     */
    private static function roommateFacts(Floorplan $plan, int $person, int $cell, int $other, int $where, array $genders, int $crimeScene): array
    {
        $room = $plan->roomOf($cell);
        if ($room !== $plan->roomOf($where)) {
            return [];
        }
        $about = [new Clue(ClueKind::SameRoom, person: $person, other: $other)];
        $seated = $plan->furniture[$where];
        if (null !== $seated && $crimeScene !== $room) {
            $about[] = new Clue(ClueKind::SomeoneOn, person: $person, gender: $genders[$other], furniture: $seated);
        }
        foreach (array_filter(Furniture::cases(), static fn (Furniture $piece): bool => $crimeScene !== $room && $plan->isBeside($where, $piece)) as $piece) {
            $about[] = new Clue(ClueKind::SomeoneBeside, person: $person, furniture: $piece);
        }

        return $about;
    }

    /**
     * @return list<Clue>
     */
    private static function lineFacts(int $size, int $person, int $cell, int $other, int $where): array
    {
        $about = [];
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

        return $about;
    }

    /**
     * @param array<int, list<Clue>> $people
     * @param list<int>              $solution
     * @param array<int, list<int>>  $guests
     *
     * @return array<int, list<Clue>>
     */
    private static function companions(Floorplan $plan, array $people, array $solution, array $guests, int $crimeScene): array
    {
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

        return $companions;
    }
}
