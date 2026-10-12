<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Social;

use App\Domain\Identity\User;
use App\Domain\Social\Exception\InviteAlreadyUsed;
use App\Domain\Social\Exception\InviteExpired;
use App\Domain\Social\Exception\OwnInvite;
use App\Domain\Social\FriendInvite;
use App\Domain\Social\Friendship;
use PHPUnit\Framework\TestCase;

final class FriendInviteTest extends TestCase
{
    private const string NOW = '2026-10-06 08:00 UTC';

    public function testAnInviteIsAcceptedOnceWithinAWeekBySomeoneElse(): void
    {
        $louis = self::user('louis');
        $invite = FriendInvite::send($louis, 'secret', new \DateTimeImmutable(self::NOW));
        self::assertSame('2026-10-13 08:00', $invite->expiresAt()->format('Y-m-d H:i'));

        $invite->accept(self::user('mira'), new \DateTimeImmutable('2026-10-13 07:59 UTC'));

        $this->expectException(InviteAlreadyUsed::class);
        $invite->accept(self::user('noa'), new \DateTimeImmutable(self::NOW));
    }

    public function testAnExpiredInviteIsRefused(): void
    {
        $invite = FriendInvite::send(self::user('louis'), 'secret', new \DateTimeImmutable(self::NOW));

        $this->expectException(InviteExpired::class);
        $invite->accept(self::user('mira'), new \DateTimeImmutable('2026-10-13 08:00 UTC'));
    }

    public function testNobodyBefriendsThemselves(): void
    {
        $louis = self::user('louis');
        $invite = FriendInvite::send($louis, 'secret', new \DateTimeImmutable(self::NOW));

        $this->expectException(OwnInvite::class);
        $invite->accept($louis, new \DateTimeImmutable(self::NOW));
    }

    public function testAFriendshipIsTheSamePairWhoeverInvited(): void
    {
        $louis = self::user('louis');
        $mira = self::user('mira');

        self::assertSame(Friendship::ordered($louis, $mira), Friendship::ordered($mira, $louis));
        $friendship = Friendship::between($mira, $louis, new \DateTimeImmutable(self::NOW));
        self::assertSame($mira, $friendship->friendOf($louis));
        self::assertSame($louis, $friendship->friendOf($mira));
    }

    private static function user(string $name): User
    {
        return User::join($name, "$name@mossydew.test", ucfirst($name), 'Europe/Paris', new \DateTimeImmutable(self::NOW));
    }
}
