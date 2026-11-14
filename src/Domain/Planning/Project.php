<?php

declare(strict_types=1);

namespace App\Domain\Planning;

use App\Domain\Identity\User;
use App\Domain\Planning\Exception\EmptyProjectName;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\Table(name: 'project')]
#[ORM\UniqueConstraint(name: 'project_owner_name', columns: ['owner_id', 'name'])]
class Project
{
    public const int MAX_NAME_LENGTH = 60;

    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    private Ulid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $owner;

    #[ORM\Column(length: self::MAX_NAME_LENGTH)]
    private string $name;

    #[ORM\Column(length: 16, enumType: ProjectColor::class)]
    private ProjectColor $color;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private int $position;

    private function __construct(User $owner, string $name, ProjectColor $color, \DateTimeImmutable $now, int $position)
    {
        $this->id = new Ulid();
        $this->owner = $owner;
        $this->name = self::validName($name);
        $this->color = $color;
        $this->createdAt = $now;
        $this->position = $position;
    }

    public static function create(User $owner, string $name, ProjectColor $color, \DateTimeImmutable $now, int $position): self
    {
        return new self($owner, $name, $color, $now, $position);
    }

    public function moveTo(int $position): void
    {
        $this->position = $position;
    }

    public function rename(string $name): void
    {
        $this->name = self::validName($name);
    }

    public function recolor(ProjectColor $color): void
    {
        $this->color = $color;
    }

    public function id(): Ulid
    {
        return $this->id;
    }

    public function owner(): User
    {
        return $this->owner;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function color(): ProjectColor
    {
        return $this->color;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function position(): int
    {
        return $this->position;
    }

    private static function validName(string $name): string
    {
        $name = trim($name);
        if ('' === $name) {
            throw new EmptyProjectName();
        }

        return mb_substr($name, 0, self::MAX_NAME_LENGTH);
    }
}
