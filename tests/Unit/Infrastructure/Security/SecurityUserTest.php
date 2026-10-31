<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Security;

use App\Domain\Identity\User;
use App\Infrastructure\Security\SecurityUser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class SecurityUserTest extends TestCase
{
    public function testTheMysteriesChoiceSurvivesTheSession(): void
    {
        $user = User::join('account', 'louis@example.com', 'Louis', 'Europe/Paris', new \DateTimeImmutable());
        self::assertFalse(self::throughTheSession(SecurityUser::fromUser($user))->mysteries);

        $user->playMysteries(true);

        self::assertTrue(self::throughTheSession(SecurityUser::fromUser($user))->mysteries);
    }

    public function testASessionStoredBeforeMysteriesExistedPlaysNone(): void
    {
        $old = new \ReflectionClass(SecurityUser::class)->newInstanceWithoutConstructor();

        $old->__unserialize(['id' => (string) new Ulid(), 'email' => 'louis@example.com', 'displayName' => 'Louis']);

        self::assertFalse($old->mysteries);
    }

    private static function throughTheSession(SecurityUser $user): SecurityUser
    {
        $restored = unserialize(serialize($user), ['allowed_classes' => [SecurityUser::class, Ulid::class]]);
        self::assertInstanceOf(SecurityUser::class, $restored);

        return $restored;
    }
}
