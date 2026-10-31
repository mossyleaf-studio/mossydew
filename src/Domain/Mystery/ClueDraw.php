<?php

declare(strict_types=1);

namespace App\Domain\Mystery;

use Random\Randomizer;

final readonly class ClueDraw
{
    public const int SEARCH_BUDGET = 20_000;
    public const int DRAW_BUDGET = 150_000;

    public function __construct(private Randomizer $randomizer)
    {
    }

    /**
     * @param list<int> $solution
     *
     * @return list<Clue>
     */
    public function draw(Floorplan $plan, int $victim, array $solution): array
    {
        $pool = $this->pool($plan, $victim, $solution);
        $chosen = [];
        foreach (array_keys($solution) as $person) {
            $chosen[] = $this->take($pool, array_filter($pool, static fn (Clue $clue): bool => $person === $clue->person && null === $clue->other));
        }

        $spent = 0;
        while (true) {
            $search = new Search($plan, $victim, $chosen, max(\count($solution) + 1, min(self::SEARCH_BUDGET, self::DRAW_BUDGET - $spent)));
            $finished = $search->run();
            $spent += $search->nodes;
            if ($finished && 1 === \count($search->solutions)) {
                break;
            }
            $other = $finished ? array_find($search->solutions, static fn (array $cells): bool => $cells !== $solution) : null;
            $chosen[] = $this->take($pool, null === $other ? $pool : array_filter($pool, static fn (Clue $clue): bool => !$clue->holdsFor($plan, $other)));
        }

        $order = $this->randomizer->shuffleArray($chosen);
        usort($order, static fn (Clue $one, Clue $other): int => $one->kind->weight() <=> $other->kind->weight());
        foreach ($order as $clue) {
            if ($spent >= self::DRAW_BUDGET || \count($chosen) <= \count($solution)) {
                break;
            }
            $rest = array_values(array_filter($chosen, static fn (Clue $kept): bool => $kept !== $clue));
            $search = new Search($plan, $victim, $rest, min(self::SEARCH_BUDGET, self::DRAW_BUDGET - $spent));
            $finished = $search->run();
            $spent += $search->nodes;
            if ($finished && 1 === \count($search->solutions)) {
                $chosen = $rest;
            }
        }

        return $this->randomizer->shuffleArray($chosen);
    }

    /**
     * @param list<int> $solution
     *
     * @return array<int, Clue>
     */
    private function pool(Floorplan $plan, int $victim, array $solution): array
    {
        $size = $plan->size;
        $clues = [];
        $heads = array_fill(0, \count($plan->kinds), 0);
        foreach ($solution as $person => $cell) {
            ++$heads[$plan->roomOf($cell)];
            $clues[] = new Clue(ClueKind::InRoom, person: $person, room: $plan->roomOf($cell));
            $clues[] = new Clue(ClueKind::InRow, person: $person, number: intdiv($cell, $size));
            $clues[] = new Clue(ClueKind::InColumn, person: $person, number: $cell % $size);
            $seat = $plan->furniture[$cell];
            if (null !== $seat) {
                $clues[] = new Clue(ClueKind::OnFurniture, person: $person, furniture: $seat);
            }
            $around = [];
            foreach (Floorplan::neighbours($size, $cell) as $neighbour) {
                $piece = $plan->furniture[$neighbour];
                if (null !== $piece) {
                    $around[$piece->value] = new Clue(ClueKind::BesideFurniture, person: $person, furniture: $piece);
                }
            }
            array_push($clues, ...array_values($around));
            foreach ($solution as $other => $where) {
                if ($person < $other && $victim !== $person && $victim !== $other && $plan->roomOf($cell) === $plan->roomOf($where)) {
                    $clues[] = new Clue(ClueKind::SameRoom, person: $person, other: $other);
                }
                if (intdiv($cell, $size) < intdiv($where, $size)) {
                    $clues[] = new Clue(ClueKind::NorthOf, person: $person, other: $other);
                }
                if ($cell % $size < $where % $size) {
                    $clues[] = new Clue(ClueKind::WestOf, person: $person, other: $other);
                }
            }
        }
        foreach ($heads as $room => $count) {
            $clues[] = 0 === $count
                ? new Clue(ClueKind::EmptyRoom, room: $room)
                : new Clue(ClueKind::RoomHolds, room: $room, number: $count);
        }

        return $clues;
    }

    /**
     * @param array<int, Clue> $pool
     * @param array<int, Clue> $among
     */
    private function take(array &$pool, array $among): Clue
    {
        $byKind = [];
        foreach ($among as $index => $clue) {
            $byKind[$clue->kind->value][] = $index;
        }
        $roll = $this->randomizer->getInt(1, max(1, array_sum(array_map(static fn (string $kind): int => ClueKind::from($kind)->weight(), array_keys($byKind)))));
        foreach ($byKind as $kind => $indexes) {
            $roll -= ClueKind::from($kind)->weight();
            if ($roll <= 0) {
                $index = $indexes[$this->randomizer->getInt(0, \count($indexes) - 1)];
                $clue = $pool[$index];
                unset($pool[$index]);

                return $clue;
            }
        }

        throw new \LogicException('Every clue is already known.');
    }
}
