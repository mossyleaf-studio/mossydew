<?php

declare(strict_types=1);

namespace App\Domain\Identity;

use App\Domain\Identity\Exception\EmptyDisplayName;
use App\Domain\Identity\Exception\InvalidEmail;
use App\Domain\Identity\Exception\UnknownTimezone;
use App\Domain\Shared\Day;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\Table(name: 'app_user')]
class User
{
    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    private Ulid $id;

    #[ORM\Column(length: 180, unique: true)]
    private string $email;

    #[ORM\Column(length: 255, unique: true, nullable: true)]
    private ?string $accountId = null;

    #[ORM\Column(length: 60)]
    private string $displayName;

    #[ORM\Column(length: 64)]
    private string $timezone;

    #[ORM\Column(length: 2, nullable: true, enumType: Language::class)]
    private ?Language $language = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(length: 7, nullable: true)]
    private ?string $themeBackground = null;

    #[ORM\Column(length: 7, nullable: true)]
    private ?string $themeAccent = null;

    #[ORM\Column(length: 10, enumType: Design::class, options: ['default' => 'classic'])]
    private Design $design = Design::Classic;

    #[ORM\Column(length: 10, enumType: IconSet::class, options: ['default' => 'lucide'])]
    private IconSet $iconSet = IconSet::Lucide;

    #[ORM\Column(options: ['default' => false])]
    private bool $playsMysteries = false;

    private function __construct(string $email, string $displayName, string $timezone, \DateTimeImmutable $now)
    {
        $this->id = new Ulid();
        $this->email = self::normalizeEmail($email);
        $this->displayName = self::validDisplayName($displayName);
        $this->timezone = self::validTimezone($timezone);
        $this->createdAt = $now;
    }

    public static function join(string $accountId, string $email, string $displayName, string $timezone, \DateTimeImmutable $now): self
    {
        $user = new self($email, $displayName, $timezone, $now);
        $user->linkAccount($accountId);

        return $user;
    }

    public static function normalizeEmail(string $email): string
    {
        $email = mb_strtolower(trim($email));
        if (false === filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            throw new InvalidEmail($email);
        }

        return $email;
    }

    public function linkAccount(string $accountId): void
    {
        $this->accountId = $accountId;
    }

    public function changeEmail(string $email): void
    {
        $this->email = self::normalizeEmail($email);
    }

    public function rename(string $displayName): void
    {
        $this->displayName = self::validDisplayName($displayName);
    }

    public function moveTo(string $timezone): void
    {
        $this->timezone = self::validTimezone($timezone);
    }

    public function speak(Language $language): void
    {
        $this->language = $language;
    }

    public function wear(Theme $theme): void
    {
        $this->themeBackground = $theme->background;
        $this->themeAccent = $theme->accent;
    }

    public function theme(): ?Theme
    {
        if (null === $this->themeBackground || null === $this->themeAccent) {
            return null;
        }

        return Theme::of($this->themeBackground, $this->themeAccent);
    }

    public function decorate(Design $design): void
    {
        $this->design = $design;
    }

    public function design(): Design
    {
        return $this->design;
    }

    public function drawWith(IconSet $iconSet): void
    {
        $this->iconSet = $iconSet;
    }

    public function iconSet(): IconSet
    {
        return $this->iconSet;
    }

    public function playMysteries(bool $plays): void
    {
        $this->playsMysteries = $plays;
    }

    public function playsMysteries(): bool
    {
        return $this->playsMysteries;
    }

    public function today(\DateTimeImmutable $now): \DateTimeImmutable
    {
        return Day::at($now, $this->zone());
    }

    public function zone(): \DateTimeZone
    {
        return new \DateTimeZone($this->timezone);
    }

    public function id(): Ulid
    {
        return $this->id;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function accountId(): ?string
    {
        return $this->accountId;
    }

    public function displayName(): string
    {
        return $this->displayName;
    }

    public function timezone(): string
    {
        return $this->timezone;
    }

    public function language(): ?Language
    {
        return $this->language;
    }

    private static function validDisplayName(string $displayName): string
    {
        $displayName = trim($displayName);
        if ('' === $displayName) {
            throw new EmptyDisplayName();
        }

        return mb_substr($displayName, 0, 60);
    }

    private static function validTimezone(string $timezone): string
    {
        if (!\in_array($timezone, \DateTimeZone::listIdentifiers(), true)) {
            throw new UnknownTimezone($timezone);
        }

        return $timezone;
    }
}
