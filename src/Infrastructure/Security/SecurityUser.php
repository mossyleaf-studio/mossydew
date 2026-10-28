<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Domain\Identity\Design;
use App\Domain\Identity\IconSet;
use App\Domain\Identity\Language;
use App\Domain\Identity\Theme;
use App\Domain\Identity\User;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Ulid;

final class SecurityUser implements UserInterface
{
    private function __construct(
        public readonly Ulid $id,
        public readonly string $email,
        public readonly string $displayName,
        public readonly ?Language $language,
        public readonly ?Theme $theme,
        public readonly Design $design,
        public readonly IconSet $iconSet,
    ) {
    }

    public static function fromUser(User $user): self
    {
        return new self($user->id(), $user->email(), $user->displayName(), $user->language(), $user->theme(), $user->design(), $user->iconSet());
    }

    public function getUserIdentifier(): string
    {
        return $this->id->toBase32();
    }

    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function __serialize(): array
    {
        return [
            'id' => (string) $this->id,
            'email' => $this->email,
            'displayName' => $this->displayName,
            'language' => $this->language?->value,
            'theme' => null === $this->theme ? null : [$this->theme->background, $this->theme->accent],
            'design' => $this->design->value,
            'iconSet' => $this->iconSet->value,
        ];
    }

    /** @param array{id: string, email: string, displayName: string, language?: ?string, theme?: ?array{string, string}, design?: string, iconSet?: string} $data */
    public function __unserialize(array $data): void
    {
        $this->id = Ulid::fromString($data['id']);
        $this->email = $data['email'];
        $this->displayName = $data['displayName'];
        $this->language = Language::tryFrom($data['language'] ?? '');
        $this->theme = isset($data['theme']) ? Theme::of(...$data['theme']) : null;
        $this->design = Design::tryFrom($data['design'] ?? '') ?? Design::Classic;
        $this->iconSet = IconSet::tryFrom($data['iconSet'] ?? '') ?? IconSet::Lucide;
    }
}
