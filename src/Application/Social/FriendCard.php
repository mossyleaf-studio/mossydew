<?php

declare(strict_types=1);

namespace App\Application\Social;

use App\Domain\Gamification\Herbarium\SpecimenRepository;
use App\Domain\Gamification\PlayerRepository;
use App\Domain\Identity\User;
use App\Domain\Social\Friendship;
use Psr\Clock\ClockInterface;

final readonly class FriendCard
{
    public function __construct(
        private PlayerRepository $players,
        private SpecimenRepository $specimens,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(Friendship $friendship, User $me): FriendView
    {
        $friend = $friendship->friendOf($me);
        $player = $this->players->of($friend);

        return new FriendView(
            (string) $friend->id(),
            $friend->displayName(),
            $player->level(),
            $player->xp(),
            $player->streak()->asOf($friend->today($this->clock->now())),
            $player->streak()->best,
            \count($this->specimens->of($friend)),
            $friendship->since()->format(\DATE_ATOM),
        );
    }
}
