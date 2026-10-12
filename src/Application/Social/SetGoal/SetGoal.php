<?php

declare(strict_types=1);

namespace App\Application\Social\SetGoal;

use Symfony\Component\Uid\Ulid;

final readonly class SetGoal
{
    /**
     * @param list<Ulid> $friendIds
     */
    public function __construct(
        public string $title,
        public int $target,
        public \DateTimeImmutable $endsOn,
        public array $friendIds,
    ) {
    }
}
