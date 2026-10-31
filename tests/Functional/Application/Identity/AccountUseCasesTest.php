<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application\Identity;

use App\Application\Identity\ChangeTimezone\ChangeTimezoneHandler;
use App\Application\Identity\ChooseLanguage\ChooseLanguageHandler;
use App\Application\Identity\ChooseMysteries\ChooseMysteriesHandler;
use App\Application\Identity\SignIn\SignIn;
use App\Application\Identity\SignIn\SignInHandler;
use App\Domain\Gamification\Greenhouse\GreenhouseRepository;
use App\Domain\Gamification\PlayerRepository;
use App\Domain\Identity\Exception\EmailAlreadyTaken;
use App\Domain\Identity\Exception\InvalidEmail;
use App\Domain\Identity\Language;
use App\Domain\Identity\User;
use App\Domain\Identity\UserRepository;
use App\Tests\Support\ActsAsUser;
use App\Tests\Support\FreezesClock;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class AccountUseCasesTest extends KernelTestCase
{
    use ActsAsUser;
    use FreezesClock;

    protected function setUp(): void
    {
        self::freezeAt('2026-10-06 08:00 UTC');
    }

    public function testAFirstSignInCreatesTheUserAndTheirPlayer(): void
    {
        $user = $this->signIn(new SignIn('account-1', 'Louis@Example.com', 'Louis', 'Asia/Tokyo'));

        self::assertSame(['account-1', 'louis@example.com', 'Louis', 'Asia/Tokyo', null], [$user->accountId(), $user->email(), $user->displayName(), $user->timezone(), $user->language()]);
        $player = self::getContainer()->get(PlayerRepository::class)->of($user);
        self::assertSame([0, 1], [$player->xp(), $player->level()]);
    }

    public function testAFirstSignInOpensASmallGreenhouse(): void
    {
        $user = $this->signIn(new SignIn('account-1', 'louis@example.com', 'Louis'));

        $greenhouse = self::getContainer()->get(GreenhouseRepository::class)->of($user);
        self::assertSame([0, 2, 1, 0], [$greenhouse->dew(), \count($greenhouse->pots()), $greenhouse->facilities()->glasshouse, $greenhouse->yieldTenths()]);
    }

    public function testAFirstSignInFallsBackOnTheEmailAndParis(): void
    {
        $user = $this->signIn(new SignIn('account-1', 'fern@example.com', '  ', 'Mars/Olympus'));

        self::assertSame(['fern', 'Europe/Paris'], [$user->displayName(), $user->timezone()]);
    }

    public function testAnAccountWithoutEmailCannotJoin(): void
    {
        $this->expectExceptionObject(new InvalidEmail(''));

        $this->signIn(new SignIn('account-1', null));
    }

    public function testAnExistingUserIsLinkedByEmailAndKeepsTheirData(): void
    {
        $existing = self::createUserFromBeforeAccounts('louis@example.com');

        $user = $this->signIn(new SignIn('account-1', ' LOUIS@example.com', 'Somebody else', 'Asia/Tokyo'));

        self::assertSame($existing->id()->toBase32(), $user->id()->toBase32());
        self::assertSame(['account-1', 'Louis', 'Europe/Paris'], [$user->accountId(), $user->displayName(), $user->timezone()]);
        self::assertSame(1, self::getContainer()->get(PlayerRepository::class)->of($user)->level());
    }

    public function testTheAccountIsFoundAgainAfterItsEmailChanges(): void
    {
        $first = $this->signIn(new SignIn('account-1', 'louis@example.com'));

        $again = $this->signIn(new SignIn('account-1', 'lou@example.com'));

        self::assertSame($first->id()->toBase32(), $again->id()->toBase32());
        self::assertSame('lou@example.com', $again->email());
    }

    public function testAnEmailAlreadyLinkedToAnotherAccountIsRefused(): void
    {
        $this->signIn(new SignIn('account-1', 'louis@example.com'));

        $this->expectExceptionObject(new EmailAlreadyTaken('louis@example.com'));

        $this->signIn(new SignIn('account-2', 'louis@example.com'));
    }

    public function testAnAccountCannotTakeTheEmailOfAnotherUser(): void
    {
        $this->signIn(new SignIn('account-1', 'louis@example.com'));
        $this->signIn(new SignIn('account-2', 'fern@example.com'));

        $this->expectExceptionObject(new EmailAlreadyTaken('louis@example.com'));

        $this->signIn(new SignIn('account-2', 'louis@example.com'));
    }

    public function testChangeTimezoneAndLanguage(): void
    {
        $user = self::actAsNewUser();

        self::getContainer()->get(ChangeTimezoneHandler::class)('Asia/Tokyo');
        self::getContainer()->get(ChooseLanguageHandler::class)(Language::French);

        self::assertSame(['Asia/Tokyo', Language::French], [$user->timezone(), $user->language()]);
    }

    public function testChooseMysteries(): void
    {
        $user = self::actAsNewUser();
        self::assertFalse($user->playsMysteries());

        self::getContainer()->get(ChooseMysteriesHandler::class)(true);
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        self::assertTrue(self::getContainer()->get(UserRepository::class)->get($user->id())->playsMysteries());

        self::getContainer()->get(ChooseMysteriesHandler::class)(false);
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        self::assertFalse(self::getContainer()->get(UserRepository::class)->get($user->id())->playsMysteries());
    }

    private function signIn(SignIn $command): User
    {
        $user = self::getContainer()->get(SignInHandler::class)($command);
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        return self::getContainer()->get(UserRepository::class)->get($user->id());
    }
}
