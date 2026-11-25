<?php

declare(strict_types=1);

namespace App\Application\Planning\ImportSuperProductivity;

use App\Domain\Shared\Day;

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

    public function plannedOn(\DateTimeZone $zone, \DateTimeImmutable $today): ?\DateTimeImmutable
    {
        return match (true) {
            null !== $this->plannedDay => Day::of($this->plannedDay),
            null !== $this->plannedAt => Day::at($this->plannedAt, $zone),
            $this->plannedToday => $today,
            default => null,
        };
    }

    public function dueOn(\DateTimeZone $zone): ?\DateTimeImmutable
    {
        return match (true) {
            null !== $this->deadlineDay => Day::of($this->deadlineDay),
            null !== $this->deadlineAt => Day::at($this->deadlineAt, $zone),
            default => null,
        };
    }
}
