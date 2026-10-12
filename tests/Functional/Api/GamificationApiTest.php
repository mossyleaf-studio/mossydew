<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Tests\Support\Json;
use App\Tests\Support\SignsInClient;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class GamificationApiTest extends WebTestCase
{
    use SignsInClient;

    public function testShowPlayer(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('GET', '/api/player');

        self::assertResponseIsSuccessful();
        self::assertSame(1, Json::int(self::body($client), 'level'));
        self::assertSame([0, 48], [Json::int(self::body($client), 'speciesCollected'), Json::int(self::body($client), 'speciesTotal')]);
        self::assertSame(0, Json::int(self::body($client), 'dew'));
    }

    public function testListHerbarium(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('GET', '/api/herbarium');

        self::assertResponseIsSuccessful();
        self::assertSame(['total' => 48, 'specimens' => [], 'remaining' => ['common' => 16, 'uncommon' => 13, 'rare' => 14, 'very_rare' => 5]], self::body($client));
    }

    public function testAchievementsAreCelebratedOnce(): void
    {
        $client = self::signedInClient();
        $client->jsonRequest('POST', '/api/tasks', ['title' => 'Vet']);
        $client->jsonRequest('POST', '/api/tasks/'.Json::string(self::body($client), 'id').'/complete');

        $client->jsonRequest('GET', '/api/achievements');
        self::assertResponseIsSuccessful();
        self::assertCount(16, self::body($client));
        self::assertSame('first_drop', Json::string(self::body($client), 0, 'id'));
        self::assertIsString(Json::at(self::body($client), 0, 'unlockedAt'));

        $client->jsonRequest('POST', '/api/achievements/seen');
        self::assertResponseStatusCodeSame(204);

        $client->jsonRequest('GET', '/api/player');
        self::assertSame([], Json::array(self::body($client), 'newAchievements'));
    }

    /**
     * @return array<mixed>
     */
    private static function body(KernelBrowser $client): array
    {
        return Json::decode((string) $client->getResponse()->getContent());
    }
}
