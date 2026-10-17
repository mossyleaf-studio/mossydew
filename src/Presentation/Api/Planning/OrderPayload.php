<?php

declare(strict_types=1);

namespace App\Presentation\Api\Planning;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class OrderPayload
{
    public function __construct(
        /** @var list<string> */
        #[Assert\All([new Assert\Ulid(message: 'id.invalid')])]
        #[Assert\Count(max: 500)]
        public array $ids = [],
        public bool $withSubtasks = false,
    ) {
    }
}
