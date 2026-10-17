<?php

declare(strict_types=1);

namespace App\Domain\Gamification\Greenhouse;

use App\Domain\Gamification\Exception\FacilityAtMaxLevel;
use App\Domain\Gamification\Exception\MossAlreadyPlanted;
use App\Domain\Gamification\Exception\MossNotCollected;
use App\Domain\Gamification\Exception\NotEnoughDew;
use App\Domain\Gamification\Exception\PotIsEmpty;
use App\Domain\Gamification\Exception\UnknownPot;
use App\Domain\Gamification\Herbarium\Species;
use App\Domain\Gamification\Herbarium\Specimen;
use App\Domain\Identity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\Table(name: 'greenhouse')]
class Greenhouse
{
    public const int TENTHS = 10;
    public const int EXPEDITION_BASE_COST = 150;
    public const int EXPEDITION_COST_PER_TRIP = 40;
    public const int EXPEDITION_COST_PER_TRIP_SQUARED = 8;
    private const int PERCENT = 100;

    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    private Ulid $id;

    #[ORM\OneToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, unique: true, onDelete: 'CASCADE')]
    private User $owner;

    #[ORM\Column]
    private int $dew = 0;

    #[ORM\Column]
    private int $dewGathered = 0;

    #[ORM\Column]
    private int $expeditions = 0;

    #[ORM\Version]
    #[ORM\Column(type: 'integer')]
    private int $version = 1;

    #[ORM\Embedded(class: Facilities::class, columnPrefix: 'facilities_')]
    private Facilities $facilities;

    /** @var Collection<int, Pot> */
    #[ORM\OneToMany(targetEntity: Pot::class, mappedBy: 'greenhouse', cascade: ['persist'])]
    #[ORM\OrderBy(['number' => 'ASC'])]
    private Collection $pots;

    private function __construct(User $owner)
    {
        $this->id = new Ulid();
        $this->owner = $owner;
        $this->facilities = new Facilities();
        $this->pots = new ArrayCollection();
        for ($number = 1; $number <= $this->facilities->effectOf(Facility::Glasshouse); ++$number) {
            $this->pots->add(Pot::make($this, $number));
        }
    }

    public static function open(User $owner): self
    {
        return new self($owner);
    }

    public function receive(DewGain $gain): void
    {
        $this->dew += $gain->amount;
        $this->dewGathered += $gain->amount;
    }

    public function giveBack(int $dew): void
    {
        $this->dew -= $dew;
        $this->dewGathered -= $dew;
    }

    public function plant(int $number, Specimen $specimen, \DateTimeImmutable $now): void
    {
        $pot = $this->pot($number);
        $species = $specimen->species();
        if (!$specimen->owner()->id()->equals($this->owner->id())) {
            throw new MossNotCollected($species->slug);
        }
        $current = $this->potOf($species);
        if (null !== $current) {
            throw new MossAlreadyPlanted($current->number());
        }
        $pot->grow($species, $now);
    }

    public function unplant(int $number): void
    {
        $pot = $this->pot($number);
        if ($pot->isEmpty()) {
            throw new PotIsEmpty($number);
        }
        $pot->empty();
    }

    public function upgrade(Facility $facility): void
    {
        $this->spend($facility->upgradeCost($this->facilities->levelOf($facility) + 1) ?? throw new FacilityAtMaxLevel($facility));
        $this->facilities = $this->facilities->raised($facility);
        if (Facility::Glasshouse === $facility) {
            $this->pots->add(Pot::make($this, $this->pots->count() + 1));
        }
    }

    public function fundExpedition(): void
    {
        $this->spend($this->expeditionCost());
        ++$this->expeditions;
    }

    public function yieldTenths(): int
    {
        $base = array_sum(array_map(static fn (Pot $pot): int => $pot->yield(), $this->pots()));

        return intdiv($base * self::TENTHS * (self::PERCENT + $this->facilities->effectOf(Facility::Misters)), self::PERCENT);
    }

    public function wateringMultiplier(): int
    {
        return $this->facilities->effectOf(Facility::RainBarrel);
    }

    public function expeditions(): int
    {
        return $this->expeditions;
    }

    public function expeditionCost(): int
    {
        $trips = $this->expeditions;

        return self::EXPEDITION_BASE_COST + self::EXPEDITION_COST_PER_TRIP * $trips + self::EXPEDITION_COST_PER_TRIP_SQUARED * $trips * $trips;
    }

    public function potOf(Species $species): ?Pot
    {
        foreach ($this->pots() as $pot) {
            if ($pot->holds($species)) {
                return $pot;
            }
        }

        return null;
    }

    /**
     * @return list<Pot>
     */
    public function pots(): array
    {
        return array_values($this->pots->toArray());
    }

    public function id(): Ulid
    {
        return $this->id;
    }

    public function owner(): User
    {
        return $this->owner;
    }

    public function dew(): int
    {
        return $this->dew;
    }

    public function dewGathered(): int
    {
        return $this->dewGathered;
    }

    public function facilities(): Facilities
    {
        return $this->facilities;
    }

    private function pot(int $number): Pot
    {
        foreach ($this->pots() as $pot) {
            if ($pot->number() === $number) {
                return $pot;
            }
        }

        throw new UnknownPot($number);
    }

    private function spend(int $cost): void
    {
        if ($this->dew < $cost) {
            throw new NotEnoughDew($cost, $this->dew);
        }
        $this->dew -= $cost;
    }
}
