<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Identity;

use App\Domain\Identity\Exception\EmptyDisplayName;
use App\Domain\Identity\Exception\InvalidEmail;
use App\Domain\Identity\Exception\UnknownTimezone;
use App\Domain\Identity\Language;
use App\Domain\Identity\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testEmailIsNormalized(): void
    {
        self::assertSame('louis@example.com', $this->user('  Louis@Example.COM ')->email());
    }

    public function testRejectsInvalidEmail(): void
    {
        $this->expectExceptionObject(new InvalidEmail('not-an-email'));

        $this->user('not-an-email');
    }

    public function testRejectsUnknownTimezone(): void
    {
        $this->expectExceptionObject(new UnknownTimezone('Europe/Atlantis'));

        $this->user(timezone: 'Europe/Atlantis');
    }

    public function testMovingToAnUnknownTimezoneThrows(): void
    {
        $user = $this->user();

        $this->expectExceptionObject(new UnknownTimezone('Mars/Olympus'));

        $user->moveTo('Mars/Olympus');
    }

    public function testDisplayNameIsRequired(): void
    {
        $this->expectExceptionObject(new EmptyDisplayName());

        User::join('account', 'louis@example.com', '  ', 'UTC', new \DateTimeImmutable());
    }

    #[DataProvider('todays')]
    public function testTodayDependsOnTheTimezone(string $timezone, string $now, string $today): void
    {
        self::assertEquals(new \DateTimeImmutable($today.' 00:00 UTC'), $this->user(timezone: $timezone)->today(new \DateTimeImmutable($now)));
    }

    /** @return iterable<array{string, string, string}> */
    public static function todays(): iterable
    {
        yield 'paris before midnight UTC' => ['Europe/Paris', '2026-10-06 21:59 UTC', '2026-10-06'];
        yield 'paris after its midnight' => ['Europe/Paris', '2026-10-06 23:30 UTC', '2026-10-07'];
        yield 'utc' => ['UTC', '2026-10-06 23:30 UTC', '2026-10-06'];
        yield 'tokyo morning' => ['Asia/Tokyo', '2026-10-06 16:00 UTC', '2026-10-07'];
        yield 'los angeles evening' => ['America/Los_Angeles', '2026-10-07 05:00 UTC', '2026-10-06'];
    }

    public function testNewUserHasNoLanguageUntilChosen(): void
    {
        $user = $this->user();
        self::assertNull($user->language());

        $user->speak(Language::French);
        $user->moveTo('Asia/Tokyo');
        $user->rename(' Lou ');

        self::assertSame([Language::French, 'Asia/Tokyo', 'Lou'], [$user->language(), $user->timezone(), $user->displayName()]);
    }

    public function testMysteriesAreOffUntilChosen(): void
    {
        $user = $this->user();
        self::assertFalse($user->playsMysteries());

        $user->playMysteries(true);
        self::assertTrue($user->playsMysteries());

        $user->playMysteries(false);
        self::assertFalse($user->playsMysteries());
    }

    public function testFollowsItsMossyleafAccount(): void
    {
        $user = $this->user();
        self::assertSame('account', $user->accountId());

        $user->linkAccount('another-account');
        $user->changeEmail(' Lou@Example.com ');

        self::assertSame(['another-account', 'lou@example.com'], [$user->accountId(), $user->email()]);
    }

    public function testChangedEmailMustBeValid(): void
    {
        $user = $this->user();

        $this->expectExceptionObject(new InvalidEmail('lou'));

        $user->changeEmail('lou');
    }

    private function user(string $email = 'louis@example.com', string $timezone = 'Europe/Paris'): User
    {
        return User::join('account', $email, 'Louis', $timezone, new \DateTimeImmutable('2026-10-06 09:00'));
    }
}
