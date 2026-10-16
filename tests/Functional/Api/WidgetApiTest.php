<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Infrastructure\Security\SecurityUser;
use App\Tests\Support\FreezesClock;
use App\Tests\Support\Json;
use App\Tests\Support\SignsInClient;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class WidgetApiTest extends WebTestCase
{
    use FreezesClock;
    use SignsInClient;

    protected function setUp(): void
    {
        self::freezeAt('2026-10-06 08:00 UTC');
    }

    public function testEachSignedInDeviceGetsItsOwnTokenUntilItSignsOut(): void
    {
        $client = self::createClient();
        $client->jsonRequest('POST', '/api/widget/token');
        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');

        $client->loginUser(SecurityUser::fromUser(self::createUser()));
        $phone = self::deviceToken($client);
        $tablet = self::deviceToken($client);
        self::assertMatchesRegularExpression('/^mdd_[0-9a-f]{48}$/', $phone);
        self::assertNotSame($phone, $tablet);
        $client->getCookieJar()->clear();

        self::asDevice($client, $phone, 'GET', '/widget/today');
        self::assertResponseIsSuccessful();
        self::asDevice($client, $tablet, 'GET', '/widget/today');
        self::assertResponseIsSuccessful();

        self::asDevice($client, $phone, 'DELETE', '/widget/token');
        self::assertResponseStatusCodeSame(204);
        self::asDevice($client, $phone, 'GET', '/widget/today');
        self::assertResponseStatusCodeSame(401);
        self::asDevice($client, $tablet, 'GET', '/widget/today');
        self::assertResponseIsSuccessful();
    }

    public function testADeviceTokenOpensOnlyTheWidget(): void
    {
        $client = self::signedInClient();
        $water = self::task($client, ['title' => 'Water', 'plan' => 'today']);
        $device = self::deviceToken($client);
        $client->jsonRequest('POST', '/api/integrations/injust/token');
        $injust = Json::string(self::body($client), 'token');

        self::asDevice($client, null, 'GET', '/widget/today');
        self::assertResponseStatusCodeSame(401);
        self::asDevice($client, null, 'POST', "/widget/tasks/$water/complete");
        self::assertResponseStatusCodeSame(401);
        $client->getCookieJar()->clear();

        self::asDevice($client, 'mdd_'.str_repeat('0', 48), 'GET', '/widget/today');
        self::assertResponseStatusCodeSame(401);
        self::asDevice($client, $injust, 'GET', '/widget/today');
        self::assertResponseStatusCodeSame(401);
        self::asDevice($client, $device, 'GET', '/api/tasks/today');
        self::assertResponseStatusCodeSame(401);
        self::asDevice($client, $device, 'GET', '/integrations/injust/journal');
        self::assertResponseStatusCodeSame(401);
        self::asDevice($client, $device, 'GET', '/widget/anything');
        self::assertResponseStatusCodeSame(404);
        $client->request('POST', "/widget/tasks/$water/complete", server: ['HTTP_AUTHORIZATION' => "Bearer $device", 'HTTP_X_REFRESH' => '["/api/tasks/inbox"]']);
        self::assertResponseStatusCodeSame(204);
    }

    public function testTheWidgetShowsTodayOfTheTokensOwner(): void
    {
        $client = self::signedInClient();
        self::task($client, ['title' => 'Not mine', 'plan' => 'today']);
        $client->loginUser(SecurityUser::fromUser(self::createUser()));
        $drawing = self::task($client, ['title' => 'Drawing', 'plan' => 'today', 'quadrant' => 'schedule']);
        $sketch = self::subtask($client, $drawing, 'Sketch');
        $water = self::task($client, ['title' => 'Water', 'plan' => 'today', 'quadrant' => 'do_first']);
        $bank = self::task($client, ['title' => 'Call the bank', 'plan' => 'today']);
        $client->jsonRequest('POST', "/api/tasks/$bank/complete");
        $token = self::deviceToken($client);

        self::asDevice($client, $token, 'GET', '/widget/today');

        self::assertResponseIsSuccessful();
        self::assertSame([
            'date' => '2026-10-06',
            'theme' => null,
            'tasks' => [
                ['id' => $water, 'title' => 'Water', 'quadrant' => 'do_first', 'done' => false, 'nested' => false, 'locked' => false],
                ['id' => $drawing, 'title' => 'Drawing', 'quadrant' => 'schedule', 'done' => false, 'nested' => false, 'locked' => true],
                ['id' => $sketch, 'title' => 'Sketch', 'quadrant' => null, 'done' => false, 'nested' => true, 'locked' => false],
                ['id' => $bank, 'title' => 'Call the bank', 'quadrant' => null, 'done' => true, 'nested' => false, 'locked' => false],
            ],
        ], self::body($client));

        $client->jsonRequest('PUT', '/api/me/theme', ['background' => '#1d201b', 'accent' => '#93bb6c']);
        self::asDevice($client, $token, 'GET', '/widget/today');
        self::assertSame(['background' => '#1d201b', 'accent' => '#93bb6c', 'dark' => true], Json::at(self::body($client), 'theme'));
    }

    public function testTickingInTheWidgetCompletesTheTaskLikeTheApp(): void
    {
        $client = self::signedInClient();
        $notMine = self::task($client, ['title' => 'Not mine', 'plan' => 'today']);
        $client->loginUser(SecurityUser::fromUser(self::createUser()));
        $drawing = self::task($client, ['title' => 'Drawing', 'plan' => 'today']);
        $sketch = self::subtask($client, $drawing, 'Sketch');
        $token = self::deviceToken($client);

        self::asDevice($client, $token, 'POST', "/widget/tasks/$drawing/complete");
        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
        self::asDevice($client, $token, 'POST', "/widget/tasks/$notMine/complete");
        self::assertResponseStatusCodeSame(404);

        self::asDevice($client, $token, 'POST', "/widget/tasks/$sketch/complete");
        self::assertResponseStatusCodeSame(204);
        self::assertSame(['Drawing' => true, 'Sketch' => true], self::doneByTitle($client, $token), 'the last subtask completes its parent');
        $client->jsonRequest('GET', '/api/player');
        self::assertGreaterThan(0, Json::int(self::body($client), 'xp'));

        self::asDevice($client, $token, 'POST', "/widget/tasks/$sketch/reopen");
        self::assertResponseStatusCodeSame(204);
        self::assertSame(['Drawing' => false, 'Sketch' => false], self::doneByTitle($client, $token), 'reopening a subtask reopens its parent');
    }

    private static function deviceToken(KernelBrowser $client): string
    {
        $client->jsonRequest('POST', '/api/widget/token');
        self::assertResponseStatusCodeSame(201);

        return Json::string(self::body($client), 'token');
    }

    private static function asDevice(KernelBrowser $client, ?string $token, string $method, string $uri): void
    {
        $client->request($method, $uri, server: null === $token ? [] : ['HTTP_AUTHORIZATION' => "Bearer $token"]);
    }

    /**
     * @param array<string, string> $task
     */
    private static function task(KernelBrowser $client, array $task): string
    {
        $client->jsonRequest('POST', '/api/tasks', $task);
        self::assertResponseStatusCodeSame(201);

        return Json::string(self::body($client), 'id');
    }

    private static function subtask(KernelBrowser $client, string $parent, string $title): string
    {
        $client->jsonRequest('POST', "/api/tasks/$parent/subtasks", ['title' => $title]);
        self::assertResponseStatusCodeSame(201);

        return Json::string(self::body($client), 'id');
    }

    /**
     * @return array<string, mixed>
     */
    private static function doneByTitle(KernelBrowser $client, string $token): array
    {
        self::asDevice($client, $token, 'GET', '/widget/today');
        self::assertResponseIsSuccessful();
        $done = [];
        foreach (Json::array(self::body($client), 'tasks') as $task) {
            $done[Json::string($task, 'title')] = Json::at($task, 'done');
        }

        return $done;
    }

    /**
     * @return array<mixed>
     */
    private static function body(KernelBrowser $client): array
    {
        return Json::decode((string) $client->getResponse()->getContent());
    }
}
