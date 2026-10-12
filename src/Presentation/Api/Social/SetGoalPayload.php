<?php

declare(strict_types=1);

namespace App\Presentation\Api\Social;

use App\Domain\Social\SharedGoal;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class SetGoalPayload
{
    /**
     * @param list<Ulid> $friendIds
     */
    public function __construct(
        #[Assert\NotBlank(message: 'title.required')]
        #[Assert\Length(max: SharedGoal::MAX_TITLE_LENGTH, maxMessage: 'title.too_long')]
        public string $title = '',
        #[Assert\Range(min: 1, max: SharedGoal::MAX_TARGET, notInRangeMessage: 'goal.target.range')]
        public int $target = 0,
        #[Assert\NotBlank(message: 'date.invalid')]
        #[Assert\Date(message: 'date.invalid')]
        public string $endsOn = '',
        #[Assert\Count(min: 1, minMessage: 'goal.friends.required')]
        public array $friendIds = [],
    ) {
    }
}
