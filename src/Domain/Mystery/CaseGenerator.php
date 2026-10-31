<?php

declare(strict_types=1);

namespace App\Domain\Mystery;

use Random\Randomizer;

final readonly class CaseGenerator
{
    private const int MIN_SIZE = 6;
    private const int MAX_SIZE = 14;
    private const array NAMES = ['Aster', 'Basil', 'Clover', 'Dahlia', 'Elder', 'Fern', 'Ginger', 'Hazel', 'Iris', 'Juniper', 'Laurel', 'Myrtle', 'Nettle', 'Olive', 'Poppy', 'Quince', 'Rowan', 'Sage', 'Thyme', 'Violet', 'Willow', 'Yarrow', 'Zinnia'];

    public function __construct(private Randomizer $randomizer)
    {
    }

    public function generate(): Blueprint
    {
        $size = $this->randomizer->getInt(self::MIN_SIZE, self::MAX_SIZE);
        $kinds = \array_slice($this->randomizer->shuffleArray(RoomKind::cases()), 0, $this->randomizer->getInt((int) ceil($size / 2), (int) ceil(0.8 * $size)));
        $rooms = $this->rooms($size, \count($kinds));
        [$solution, $victim] = $this->crime($size, $rooms);
        $plan = new Floorplan($size, $rooms, $kinds, $this->furnish($size, $rooms, $kinds, $solution));
        $people = array_map(static fn (int $key): string => self::NAMES[$key], $this->randomizer->pickArrayKeys(self::NAMES, $size));

        return new Blueprint($plan, $people, $victim, $solution, new ClueDraw($this->randomizer)->draw($plan, $victim, $solution));
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
     * @param list<int>      $rooms
     * @param list<RoomKind> $kinds
     * @param list<int>      $solution
     *
     * @return list<?Furniture>
     */
    private function furnish(int $size, array $rooms, array $kinds, array $solution): array
    {
        $furniture = array_fill(0, $size * $size, null);
        $occupied = array_flip($solution);
        $cellsByRoom = [];
        foreach ($rooms as $cell => $room) {
            $cellsByRoom[$room][] = $cell;
        }
        foreach ($cellsByRoom as $room => $cells) {
            $affinity = $kinds[$room]->furniture();
            for ($pieces = max(1, intdiv(\count($cells), 4)); $pieces > 0; --$pieces) {
                $piece = $affinity[$this->randomizer->getInt(0, \count($affinity) - 1)];
                $spots = array_values(array_filter(
                    $cells,
                    static fn (int $cell): bool => null === $furniture[$cell] && ($piece->canHoldSomeone() || !isset($occupied[$cell])),
                ));
                if ([] !== $spots) {
                    $furniture[$spots[$this->randomizer->getInt(0, \count($spots) - 1)]] = $piece;
                }
            }
        }

        return array_values($furniture);
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
