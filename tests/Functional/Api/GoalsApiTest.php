<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Domain\Identity\User;
use App\Domain\Social\Friendship;
use App\Infrastructure\Security\SecurityUser;
use App\Tests\Support\FreezesClock;
use App\Tests\Support\Json;
use App\Tests\Support\SignsInClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class GoalsApiTest extends WebTestCase
{
    use FreezesClock;
    use SignsInClient;

    protected function setUp(): void
    {
        self::freezeAt('2026-10-06 08:00 UTC');
    }

    public function testFriendsReachASharedGoalTogether(): void
    {
        $client = self::signedInClient();
        [$louis, $mira, $noa] = [self::createUser(), self::createUser(), self::createUser()];
        self::befriend($louis, $mira);

        self::as($client, $louis);
        $client->jsonRequest('POST', '/api/goals', ['title' => 'Two tasks', 'target' => 2, 'endsOn' => '2026-10-31', 'friendIds' => [(string) $noa->id()]]);
        self::assertResponseStatusCodeSame(404, 'only friends join a goal');
        $client->jsonRequest('POST', '/api/goals', ['title' => 'Two tasks', 'target' => 0, 'endsOn' => 'soon', 'friendIds' => []]);
        self::assertResponseStatusCodeSame(422);
        $client->jsonRequest('POST', '/api/goals', ['title' => 'Two tasks', 'target' => 2, 'endsOn' => '2026-10-31', 'friendIds' => [(string) $mira->id()]]);
        self::assertResponseStatusCodeSame(201);
        $goalId = Json::string(self::body($client), 'id');

        self::assertSame([], Json::array(self::complete($client, 'Mine'), 'goalsReached'));

        self::as($client, $mira);
        $client->jsonRequest('GET', '/api/friends');
        self::assertSame([1, 2, true], [Json::at(self::body($client), 'goals', 0, 'total'), Json::at(self::body($client), 'goals', 0, 'target'), Json::at(self::body($client), 'goals', 0, 'active')]);
        $client->jsonRequest('GET', '/api/greenhouse');
        $dewBefore = Json::int(self::body($client), 'dew');
        self::assertSame(['Two tasks'], Json::array(self::complete($client, 'Hers'), 'goalsReached'));

        $client->jsonRequest('GET', '/api/friends');
        self::assertSame([2, false], [Json::at(self::body($client), 'goals', 0, 'total'), Json::at(self::body($client), 'goals', 0, 'active')]);
        $client->jsonRequest('GET', '/api/herbarium');
        self::assertCount(1, array_filter(Json::array(self::body($client), 'specimens')));
        $client->jsonRequest('GET', '/api/greenhouse');
        self::assertGreaterThanOrEqual($dewBefore + 2, Json::int(self::body($client), 'dew'));
        $client->jsonRequest('GET', '/api/achievements');
        self::assertNotNull(array_column(self::body($client), 'unlockedAt', 'id')['common_ground']);

        self::as($client, $louis);
        $client->jsonRequest('GET', '/api/herbarium');
        self::assertCount(1, array_filter(Json::array(self::body($client), 'specimens')));

        self::as($client, $noa);
        $client->jsonRequest('POST', "/api/goals/$goalId/leave");
        self::assertResponseStatusCodeSame(404);
        self::as($client, $mira);
        $client->jsonRequest('POST', "/api/goals/$goalId/leave");
        self::assertResponseStatusCodeSame(204);
        $client->jsonRequest('GET', '/api/friends');
        self::assertSame([], Json::array(self::body($client), 'goals'));
    }

    private static function befriend(User $one, User $other): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist(Friendship::between($one, $other, new \DateTimeImmutable()));
        $entityManager->flush();
    }

    private static function as(KernelBrowser $client, User $user): void
    {
        $client->loginUser(SecurityUser::fromUser($user));
    }

    /**
     * @return array<mixed>
     */
    private static function complete(KernelBrowser $client, string $title): array
    {
        $client->jsonRequest('POST', '/api/tasks', ['title' => $title]);
        $client->jsonRequest('POST', '/api/tasks/'.Json::string(self::body($client), 'id').'/complete');
        self::assertResponseIsSuccessful();

        return self::body($client);
    }

    /**
     * @return array<mixed>
     */
    private static function body(KernelBrowser $client): array
    {
        return Json::decode((string) $client->getResponse()->getContent());
    }
}
