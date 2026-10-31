<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Domain\Identity\Theme;
use App\Domain\Identity\User;
use App\Infrastructure\Security\SecurityUser;
use App\Tests\Support\Json;
use App\Tests\Support\SignsInClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ThemeTest extends WebTestCase
{
    use SignsInClient;

    public function testPagesUseTheDefaultThemeUntilOneIsChosen(): void
    {
        $client = self::signedInClient();

        $crawler = $client->request('GET', '/today');

        self::assertNull($crawler->filter('html')->attr('style'));
        $session = Json::decode($crawler->filter('#app-session')->text());
        self::assertNull(Json::at($session, 'theme'));
        self::assertNull($crawler->filter('html')->attr('data-design'));
        self::assertSame('classic', Json::string($session, 'design'));
        self::assertSame('lucide', Json::string($session, 'icons'));
        self::assertFalse(Json::at($session, 'mysteries'));
    }

    public function testChooseMysteriesFlagsEveryPageAfterAFullReload(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('PUT', '/api/me/mysteries', ['enabled' => true]);
        self::assertResponseStatusCodeSame(204);

        foreach (['/today', '/mysteries', '/today'] as $path) {
            $crawler = $client->request('GET', $path);
            self::assertTrue(Json::at(Json::decode($crawler->filter('#app-session')->text()), 'mysteries'));
        }

        $client->jsonRequest('PUT', '/api/me/mysteries', ['enabled' => false]);

        $crawler = $client->request('GET', '/today');
        self::assertFalse(Json::at(Json::decode($crawler->filter('#app-session')->text()), 'mysteries'));
    }

    public function testChooseDesignAndIconsStoresThemAndStylesThePages(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('PUT', '/api/me/design', ['design' => 'cozy']);
        $client->jsonRequest('PUT', '/api/me/icons', ['icons' => 'phosphor']);

        $crawler = $client->request('GET', '/today');
        self::assertSame('cozy', $crawler->filter('html')->attr('data-design'));
        $session = Json::decode($crawler->filter('#app-session')->text());
        self::assertSame('cozy', Json::string($session, 'design'));
        self::assertSame('phosphor', Json::string($session, 'icons'));
    }

    public function testChooseThemeStoresItAndColoursThePages(): void
    {
        $client = self::createClient();
        $user = self::createUser();
        $client->loginUser(SecurityUser::fromUser($user));

        $client->jsonRequest('PUT', '/api/me/theme', ['background' => '#1D201B', 'accent' => '#93bb6c']);
        self::assertResponseStatusCodeSame(204);

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        self::assertEquals(Theme::of('#1d201b', '#93bb6c'), $entityManager->find(User::class, $user->id())?->theme());

        $crawler = $client->request('GET', '/today');
        self::assertSame('--theme-background: #1d201b; --theme-accent: #93bb6c; color-scheme: dark', $crawler->filter('html')->attr('style'));
        $session = Json::decode($crawler->filter('#app-session')->text());
        self::assertSame('#1d201b', Json::string($session, 'theme', 'background'));
        self::assertSame('#93bb6c', Json::string($session, 'theme', 'accent'));
    }

    public function testChooseThemeRejectsAColourThatIsNotAHexCode(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('PUT', '/api/me/theme', ['background' => 'white', 'accent' => '#93bb6c'], ['HTTP_ACCEPT_LANGUAGE' => 'fr']);

        self::assertResponseStatusCodeSame(422);
        $response = Json::decode((string) $client->getResponse()->getContent());
        self::assertSame('background', Json::string($response, 'violations', 0, 'propertyPath'));
        self::assertSame('Utilisez une couleur hexadécimale comme #5b7f3a.', Json::string($response, 'violations', 0, 'title'));
    }
}
