<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application\Identity;

use App\Application\Identity\IssueDeviceToken\IssueDeviceTokenHandler;
use App\Application\Identity\RevokeDeviceToken\RevokeDeviceToken;
use App\Application\Identity\RevokeDeviceToken\RevokeDeviceTokenHandler;
use App\Domain\Identity\DeviceTokenRepository;
use App\Tests\Support\ActsAsUser;
use App\Tests\Support\FreezesClock;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DeviceTokensTest extends KernelTestCase
{
    use ActsAsUser;
    use FreezesClock;

    protected function setUp(): void
    {
        self::freezeAt('2026-10-06 08:00 UTC');
    }

    public function testEachDeviceGetsItsOwnTokenAndOnlyItsHashIsKept(): void
    {
        $user = self::actAsNewUser();

        $phone = self::issue();
        $tablet = self::issue();

        self::assertMatchesRegularExpression('/^mdd_[0-9a-f]{48}$/', $phone);
        self::assertNotSame($phone, $tablet);
        self::assertEqualsCanonicalizing(
            [hash('sha256', $phone), hash('sha256', $tablet)],
            self::getContainer()->get(EntityManagerInterface::class)->getConnection()->fetchFirstColumn('SELECT hash FROM device_token WHERE owner_id = ?', [$user->id()->toRfc4122()]),
        );
    }

    public function testRevokingDeletesOnlyThatTokenAndOnlyForItsOwner(): void
    {
        $owner = self::actAsNewUser();
        $phone = self::issue();
        $tablet = self::issue();
        $tokens = self::getContainer()->get(DeviceTokenRepository::class);

        self::actAsNewUser();
        self::revoke($phone);
        self::assertNotNull($tokens->withSecret($phone), 'another user cannot revoke it');

        self::actAs($owner);
        self::revoke($phone);
        self::assertNull($tokens->withSecret($phone));
        self::assertNotNull($tokens->withSecret($tablet));
    }

    private static function issue(): string
    {
        return self::getContainer()->get(IssueDeviceTokenHandler::class)();
    }

    private static function revoke(string $secret): void
    {
        self::getContainer()->get(RevokeDeviceTokenHandler::class)(new RevokeDeviceToken($secret));
    }
}
