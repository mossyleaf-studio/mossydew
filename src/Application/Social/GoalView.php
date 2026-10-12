<?php

declare(strict_types=1);

namespace App\Application\Social;

use App\Domain\Identity\User;
use App\Domain\Shared\Day;
use App\Domain\Social\GoalMember;
use App\Domain\Social\SharedGoal;

final readonly class GoalView
{
    /**
     * @param list<array{id: string, displayName: string, contributed: int, me: bool}> $members
     */
    public function __construct(
        public string $id,
        public string $title,
        public int $target,
        public int $total,
        public string $endsOn,
        public ?string $reachedAt,
        public bool $active,
        public array $members,
    ) {
    }

    public static function of(SharedGoal $goal, User $me, \DateTimeImmutable $today): self
    {
        return new self(
            (string) $goal->id(),
            $goal->title(),
            $goal->target(),
            $goal->total(),
            (string) Day::format($goal->endsOn()),
            $goal->reachedAt()?->format(\DATE_ATOM),
            $goal->isActiveOn($today),
            array_map(static fn (GoalMember $member): array => [
                'id' => (string) $member->user()->id(),
                'displayName' => $member->user()->displayName(),
                'contributed' => $member->contributed(),
                'me' => $member->is($me),
            ], $goal->members()),
        );
    }
}
