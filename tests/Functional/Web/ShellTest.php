<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Tests\Support\Json;
use App\Tests\Support\SignsInClient;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ShellTest extends WebTestCase
{
    use SignsInClient;

    public function testSignedInUsersSkipTheLogin(): void
    {
        $client = self::signedInClient();

        $client->request('GET', '/login');

        self::assertResponseRedirects('/');
    }

    public function testSignedOutVisitorsAreSentToLogin(): void
    {
        $client = self::createClient();

        $client->request('GET', '/');

        self::assertResponseRedirects('/login');
    }

    public function testTheHerbariumPagePreloadsTheHerbarium(): void
    {
        $client = self::signedInClient();

        $crawler = $client->request('GET', '/herbarium');

        self::assertResponseIsSuccessful();
        self::assertSame(['/api/projects', '/api/player', '/api/herbarium'], array_keys(Json::decode($crawler->filter('#app-preload')->text())));
    }

    public function testTheGreenhousePagePreloadsTheGreenhouse(): void
    {
        $client = self::signedInClient();

        $crawler = $client->request('GET', '/greenhouse');

        self::assertResponseIsSuccessful();
        $preloaded = Json::decode($crawler->filter('#app-preload')->text());
        self::assertSame(['/api/projects', '/api/player', '/api/greenhouse'], array_keys($preloaded));
        self::assertSame(2, Json::int($preloaded, '/api/greenhouse', 'wateringMultiplier'));
    }

    public function testTheMysteriesPagePreloadsTheCase(): void
    {
        $client = self::signedInClient();

        $crawler = $client->request('GET', '/mysteries');

        self::assertResponseIsSuccessful();
        $preloaded = Json::decode($crawler->filter('#app-preload')->text());
        self::assertSame(['/api/projects', '/api/player', '/api/mystery'], array_keys($preloaded));
        self::assertSame(['investigation' => null, 'solved' => 0], Json::array($preloaded, '/api/mystery'));
    }

    public function testSignedInUsersGetTheShell(): void
    {
        $client = self::signedInClient('louis@mossydew.test');

        $crawler = $client->request('GET', '/today');

        self::assertResponseIsSuccessful();
        $preloaded = Json::decode($crawler->filter('#app-preload')->text());
        self::assertSame(['/api/projects', '/api/player', '/api/tasks/today'], array_keys($preloaded));
        self::assertSame(1, Json::int($preloaded, '/api/player', 'level'));
        $session = Json::decode($crawler->filter('#app-session')->text());
        self::assertSame(['louis@mossydew.test', 'https://accounts.test'], [Json::string($session, 'email'), Json::string($session, 'accountsUrl')]);
    }
}
