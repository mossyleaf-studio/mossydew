<?php

declare(strict_types=1);

namespace App\Application\Planning\ImportSuperProductivity;

final readonly class SuperProductivityTask
{
    public function __construct(
        public string $id,
        public string $title,
        public ?string $notes,
        public ?string $projectName,
        public ?string $parentId,
        public bool $urgent,
        public bool $important,
        public int $order,
        public ?string $plannedDay,
        public ?\DateTimeImmutable $plannedAt,
        public bool $plannedToday,
        public ?string $deadlineDay,
        public ?\DateTimeImmutable $deadlineAt,
        public ?\DateTimeImmutable $createdAt,
    ) {
    }
}
