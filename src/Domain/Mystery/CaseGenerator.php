<?php

declare(strict_types=1);

namespace App\Domain\Mystery;

use Random\Randomizer;

final readonly class CaseGenerator
{
    private const int MIN_SIZE = 6;
    private const int MAX_SIZE = 14;
    private const array SUSPECTS = [
        'Aster' => Gender::Female, 'Basil' => Gender::Male, 'Cedar' => Gender::Male, 'Dahlia' => Gender::Female, 'Elder' => Gender::Male, 'Fern' => Gender::Female,
        'Ginger' => Gender::Female, 'Hazel' => Gender::Female, 'Iris' => Gender::Female, 'Juniper' => Gender::Male, 'Linden' => Gender::Male, 'Myrtle' => Gender::Female,
        'Nettle' => Gender::Male, 'Olive' => Gender::Female, 'Poppy' => Gender::Female, 'Quince' => Gender::Male, 'Rowan' => Gender::Male, 'Sage' => Gender::Male,
        'Thyme' => Gender::Male, 'Willow' => Gender::Female, 'Yarrow' => Gender::Male, 'Zinnia' => Gender::Female,
    ];
    private const array VICTIMS = ['Valerian' => Gender::Male, 'Verbena' => Gender::Female, 'Vervain' => Gender::Male, 'Vetch' => Gender::Male, 'Viola' => Gender::Female, 'Violet' => Gender::Female];
    private const float SPARSE = 0.12;
    private const float SPARSE_BLOCKING = 0.07;
    private const float DENSE = 0.25;
    private const int SEARCH_BUDGET = 100_000;

    public function __construct(private Randomizer $randomizer)
    {
    }

    public static function genderOf(string $name): Gender
    {
        return self::SUSPECTS[$name] ?? self::VICTIMS[$name] ?? Gender::Female;
    }

    /**
     * @param int<6, 14>|null $size
     */
    public function generate(?int $size = null): Blueprint
    {
        $size ??= $this->randomizer->getInt(self::MIN_SIZE, self::MAX_SIZE);
        $suspects = $this->randomizer->pickArrayKeys(self::SUSPECTS, $size - 1);
        $victimName = array_keys(self::VICTIMS)[$this->randomizer->getInt(0, \count(self::VICTIMS) - 1)];
        do {
            $kinds = \array_slice($this->randomizer->shuffleArray(RoomKind::cases()), 0, $this->randomizer->getInt((int) ceil($size / 2), $size));
            $rooms = $this->rooms($size, \count($kinds));
            [$solution, $victim] = $this->crime($size, $rooms);
            $people = $suspects;
            array_splice($people, $victim, 0, [$victimName]);
            $genders = array_map(self::genderOf(...), $people);
            $plan = $this->furnish(new Floorplan($size, $rooms, $kinds, array_fill(0, $size * $size, null)), $solution, self::SPARSE, self::SPARSE_BLOCKING);
            $drawn = new ClueDraw($this->randomizer)->draw($plan, $victim, $genders, $solution);
            if (null !== $drawn) {
                [$plan, $rules, $clues] = $drawn;
                $known = [...$rules, ...$clues];
                $plan = $this->furnish($plan, $solution, self::DENSE, ClueDraw::BLOCKING, static fn (Floorplan $plan): bool => array_all($known, static fn (Clue $clue): bool => $clue->holdsFor($plan, $solution, $genders))
                    && Deduction::of($plan, $victim, $genders, $known)->endsWithTheVictim());
            }
        } while (null === $drawn || 1 !== new Solver()->count($plan, $victim, $genders, $known, self::SEARCH_BUDGET));

        return new Blueprint($plan, $people, $genders, $victim, $solution, $clues, $rules);
    }

    /**
     * @return list<int>
     */
    private function rooms(int $size, int $count): array
    {
        do {
            $rooms = array_fill(0, $size * $size, -1);
            $frontiers = [];
            foreach (\array_slice($this->randomizer->shuffleArray(array_keys($rooms)), 0, $count) as $room => $cell) {
                $rooms[$cell] = $room;
                $frontiers[$room] = Floorplan::neighbours($size, $cell);
            }
            while ([] !== $frontiers) {
                $room = $this->randomizer->pickArrayKeys($frontiers, 1)[0];
                $index = $this->randomizer->getInt(0, \count($frontiers[$room]) - 1);
                $cell = $frontiers[$room][$index];
                $frontiers[$room][$index] = $frontiers[$room][\count($frontiers[$room]) - 1];
                array_pop($frontiers[$room]);
                if (-1 === $rooms[$cell]) {
                    $rooms[$cell] = $room;
                    foreach (Floorplan::neighbours($size, $cell) as $neighbour) {
                        if (-1 === $rooms[$neighbour]) {
                            $frontiers[$room][] = $neighbour;
                        }
                    }
                }
                if ([] === $frontiers[$room]) {
                    unset($frontiers[$room]);
                }
            }
        } while (\in_array(1, array_count_values($rooms), true));

        return array_values($rooms);
    }

    /**
     * @param list<int> $rooms
     *
     * @return array{list<int>, int}
     */
    private function crime(int $size, array $rooms): array
    {
        do {
            $solution = array_map(
                static fn (int $row, int $column): int => $row * $size + $column,
                $this->randomizer->shuffleArray(range(0, $size - 1)),
                $this->randomizer->shuffleArray(range(0, $size - 1)),
            );
            $pairs = array_values(array_filter(
                $this->guestsByRoom($rooms, $solution),
                static fn (array $guests): bool => 2 === \count($guests),
            ));
        } while ([] === $pairs);

        $pair = $pairs[$this->randomizer->getInt(0, \count($pairs) - 1)];

        return [$solution, $pair[$this->randomizer->getInt(0, 1)]];
    }

    /**
     * @param list<int>                  $solution
     * @param ?\Closure(Floorplan): bool $keeps
     */
    private function furnish(Floorplan $plan, array $solution, float $density, float $blocking, ?\Closure $keeps = null): Floorplan
    {
        $occupied = array_flip($solution);
        $cellsByRoom = [];
        foreach ($plan->rooms as $cell => $room) {
            $cellsByRoom[$room][] = $cell;
        }
        foreach ($cellsByRoom as $room => $cells) {
            $affinity = $plan->kinds[$room]->furniture();
            $free = \count(array_filter($cells, static fn (int $cell): bool => !$plan->blocks($cell)));
            $pieces = (int) round(\count($cells) * $density) - \count(array_filter($cells, static fn (int $cell): bool => null !== $plan->furniture[$cell]));
            for (; $pieces > 0; --$pieces) {
                $choice = $plan->spareBlocks($blocking) > 0 ? $affinity : array_values(array_filter($affinity, static fn (Furniture $piece): bool => $piece->canHoldSomeone()));
                if ([] === $choice) {
                    break;
                }
                $piece = $choice[$this->randomizer->getInt(0, \count($choice) - 1)];
                $spots = $piece->canHoldSomeone() || $free > 2 ? array_values(array_filter(
                    $cells,
                    static fn (int $cell): bool => null === $plan->furniture[$cell] && ($piece->canHoldSomeone() || !isset($occupied[$cell])),
                )) : [];
                if ([] === $spots) {
                    continue;
                }
                $next = $plan->with($spots[$this->randomizer->getInt(0, \count($spots) - 1)], $piece);
                if (null === $keeps || $keeps($next)) {
                    $plan = $next;
                    $free -= (int) !$piece->canHoldSomeone();
                }
            }
        }

        return $plan;
    }

    /**
     * @param list<int> $rooms
     * @param list<int> $solution
     *
     * @return array<int, list<int>>
     */
    private function guestsByRoom(array $rooms, array $solution): array
    {
        $guests = [];
        foreach ($solution as $person => $cell) {
            $guests[$rooms[$cell]][] = $person;
        }

        return $guests;
    }
}
