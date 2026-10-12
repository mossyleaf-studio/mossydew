<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Tests\Support\FreezesClock;
use App\Tests\Support\Json;
use App\Tests\Support\SignsInClient;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class InjustApiTest extends WebTestCase
{
    use FreezesClock;
    use SignsInClient;

    private const string INSTANCE = '01JA2Z5Q6M8N0P2R4T6V8X0Z2B';

    protected function setUp(): void
    {
        self::freezeAt('2026-10-06 08:00 UTC');
    }

    public function testTokensAreShownOnceReplacedAndRevoked(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('GET', '/api/integrations/injust');
        self::assertSame(['tokenCreatedAt' => null, 'feedAt' => null, 'waiting' => 0], self::body($client));

        $first = self::token($client);
        self::assertMatchesRegularExpression('/^mdw_[0-9a-f]{48}$/', $first);
        $second = self::token($client);
        self::assertNotSame($first, $second);

        self::feed($client, $first, self::feedOf(1, []));
        self::assertResponseStatusCodeSame(401);
        self::feed($client, $second, self::feedOf(1, []));
        self::assertResponseStatusCodeSame(204);

        $client->jsonRequest('GET', '/api/integrations/injust');
        self::assertSame('2026-10-06T08:00:00+00:00', Json::string(self::body($client), 'feedAt'));

        $client->jsonRequest('DELETE', '/api/integrations/injust/token');
        self::assertResponseStatusCodeSame(204);
        self::feed($client, $second, self::feedOf(2, []));
        self::assertResponseStatusCodeSame(401);
    }

    public function testATokenOnlyOpensTheIntegrationRoutes(): void
    {
        $client = self::signedInClient();
        $token = self::token($client);

        $client->request('GET', '/integrations/injust/journal');
        self::assertResponseStatusCodeSame(401);
        $client->getCookieJar()->clear();
        $client->request('GET', '/api/tasks/inbox', server: ['HTTP_AUTHORIZATION' => "Bearer $token", 'HTTP_ACCEPT' => 'application/json']);
        self::assertResponseStatusCodeSame(401);
    }

    public function testFeedItemsBecomeTasksAndTicksGoBackThroughTheJournal(): void
    {
        $client = self::signedInClient();
        $token = self::token($client);

        self::feed($client, $token, self::feedOf(1, [
            self::item('step:cover', 'a1', 'Cover revealed', '2026-10-09', ['Untitled EP'], 'Untitled EP, due Fri 9 Oct.'),
            self::item('stage:mix', 'b1', 'Mixing: She Needs Help', null, ['Studio'], 'Current stage.'),
            self::item('post:r:2026-10-07', 'c1', 'Guitar playthrough', '2026-10-07', ['Posts'], 'Reels first.'),
        ]));
        self::assertResponseStatusCodeSame(204);

        $tasks = self::injustTasks($client);
        self::assertSame(['Cover revealed', 'Guitar playthrough', 'Mixing: She Needs Help'], array_keys($tasks));
        self::assertSame(['2026-10-09', null, null, "Untitled EP\nUntitled EP, due Fri 9 Oct."], [Json::at($tasks['Cover revealed'], 'dueOn'), Json::at($tasks['Cover revealed'], 'plannedOn'), Json::at($tasks['Cover revealed'], 'quadrant'), Json::at($tasks['Cover revealed'], 'notes')]);

        $client->jsonRequest('PATCH', '/api/tasks/'.Json::string($tasks['Mixing: She Needs Help'], 'id'), ['title' => 'Mix it my way', 'projectId' => Json::at($tasks['Mixing: She Needs Help'], 'projectId')]);
        self::assertResponseIsSuccessful();
        $client->jsonRequest('POST', '/api/tasks/'.Json::string($tasks['Cover revealed'], 'id').'/complete');
        self::assertResponseIsSuccessful();
        $client->jsonRequest('DELETE', '/api/tasks/'.Json::string($tasks['Guitar playthrough'], 'id'));

        $journal = self::journal($client, $token);
        $epoch = Json::string($journal, 'epoch');
        self::assertSame(['injust-releases/sp-journal', 1, 'mossydew'], [$journal['format'], $journal['version'], $journal['plugin']]);
        self::assertSame(['instance' => self::INSTANCE, 'seq' => 1, 'at' => '2026-10-06T08:00:00.000Z'], $journal['feedSeen']);
        self::assertSame([
            ['seq' => 1, 'instance' => self::INSTANCE, 'item' => 'step:cover', 'type' => 'done', 'at' => '2026-10-06T08:00:00.000Z'],
            ['seq' => 2, 'instance' => self::INSTANCE, 'item' => 'post:r:2026-10-07', 'type' => 'skipped', 'at' => '2026-10-06T08:00:00.000Z'],
        ], $journal['events']);

        self::feed($client, $token, self::feedOf(2, [
            self::item('step:cover', 'a1', 'Cover revealed', '2026-10-09', ['Untitled EP'], 'Untitled EP, due Fri 9 Oct.'),
            self::item('stage:mix', 'b1', 'Mixing: She Needs Help', null, ['Studio'], 'Current stage.'),
            self::item('post:r:2026-10-07', 'c1', 'Guitar playthrough', '2026-10-07', ['Posts'], 'Reels first.'),
        ], [], $epoch, 0));
        self::assertSame(['Mix it my way'], array_keys(self::injustTasks($client, open: true)), 'waiting events win, and an unchanged revision keeps local edits');

        self::feed($client, $token, self::feedOf(3, [
            self::item('stage:mix', 'b2', 'Mixing: She Needs Help', '2026-10-12', ['Studio'], 'Needed by Mon 12 Oct.'),
            self::item('step:new', 'd1', 'Pitch playlists', '2026-10-20', ['Untitled EP'], 'Spotify for Artists.'),
        ], [['id' => 'step:cover', 'status' => 'done', 'at' => '2026-10-06T08:00:00.000Z']], $epoch, 2));
        self::assertSame([], Json::array(self::journal($client, $token), 'events'));
        $open = self::injustTasks($client, open: true);
        self::assertSame(['Mixing: She Needs Help', 'Pitch playlists'], array_keys($open));
        self::assertSame('2026-10-12', Json::at($open['Mixing: She Needs Help'], 'dueOn'));

        $client->jsonRequest('GET', '/api/player');
        $xp = Json::int(self::body($client), 'xp');
        self::feed($client, $token, self::feedOf(4, [
            self::item('step:new', 'd1', 'Pitch playlists', '2026-10-20', ['Untitled EP'], 'Spotify for Artists.'),
        ], [['id' => 'stage:mix', 'status' => 'done', 'at' => '2026-10-06T08:00:00.000Z']], $epoch, 2));
        self::assertSame(['Pitch playlists'], array_keys(self::injustTasks($client, open: true)));
        $client->jsonRequest('GET', '/api/player');
        self::assertGreaterThan($xp, Json::int(self::body($client), 'xp'), 'an item done in Injust Release earns its reward here');
        self::assertSame(['seq' => 3, 'instance' => self::INSTANCE, 'item' => 'stage:mix', 'type' => 'done', 'at' => '2026-10-06T08:00:00.000Z'], Json::array(self::journal($client, $token), 'events', 0));

        $client->jsonRequest('POST', '/api/tasks/'.Json::string(self::injustTasks($client)['Pitch playlists'], 'id').'/complete');
        self::feed($client, $token, self::feedOf(5, [
            self::item('step:new', 'd1', 'Pitch playlists', '2026-10-20', ['Untitled EP'], 'Spotify for Artists.'),
        ], [], $epoch, 4));
        self::assertSame(['Pitch playlists'], array_keys(self::injustTasks($client, open: true)), 'a tick Injust Release refused comes back open');
    }

    public function testAPausedFeedChangesNoTaskAndABrokenOneIsRefused(): void
    {
        $client = self::signedInClient();
        $token = self::token($client);

        self::feed($client, $token, [...self::feedOf(1, [self::item('step:a', 'a', 'Cover', null, [], '')]), 'state' => 'paused']);
        self::assertResponseStatusCodeSame(204);
        self::assertSame([], self::injustTasks($client));

        self::feed($client, $token, ['format' => 'something-else']);
        self::assertResponseStatusCodeSame(422);
    }

    private static function token(KernelBrowser $client): string
    {
        $client->jsonRequest('POST', '/api/integrations/injust/token');
        self::assertResponseStatusCodeSame(201);

        return Json::string(self::body($client), 'token');
    }

    /**
     * @param array<mixed> $feed
     */
    private static function feed(KernelBrowser $client, string $token, array $feed): void
    {
        $client->request('PUT', '/integrations/injust/feed', server: ['HTTP_AUTHORIZATION' => "Bearer $token", 'CONTENT_TYPE' => 'application/json'], content: (string) json_encode($feed));
    }

    /**
     * @return array<mixed>
     */
    private static function journal(KernelBrowser $client, string $token): array
    {
        $client->request('GET', '/integrations/injust/journal', server: ['HTTP_AUTHORIZATION' => "Bearer $token"]);
        self::assertResponseIsSuccessful();

        return self::body($client);
    }

    /**
     * @param list<array<mixed>> $open
     * @param list<array<mixed>> $closed
     *
     * @return array<string, mixed>
     */
    private static function feedOf(int $seq, array $open, array $closed = [], ?string $epoch = null, int $appliedThrough = 0): array
    {
        return [
            'format' => 'injust-releases/task-feed', 'version' => 1, 'instance' => self::INSTANCE, 'seq' => $seq,
            'generatedAt' => '2026-10-06T07:59:00.000Z', 'today' => '2026-10-06', 'language' => 'en', 'state' => 'on',
            'journal' => ['epoch' => $epoch, 'appliedThrough' => $appliedThrough, 'refused' => []],
            'open' => $open, 'closed' => $closed,
        ];
    }

    /**
     * @param list<string> $tags
     *
     * @return array<string, mixed>
     */
    private static function item(string $id, string $rev, string $title, ?string $due, array $tags, string $block): array
    {
        return ['id' => $id, 'kind' => explode(':', $id)[0], 'rev' => $rev, 'title' => $title, 'due' => $due, 'tags' => $tags, 'block' => $block];
    }

    /**
     * @return array<string, array<mixed>>
     */
    private static function injustTasks(KernelBrowser $client, bool $open = false): array
    {
        $client->jsonRequest('GET', '/api/projects');
        $project = array_values(array_filter(self::body($client), static fn (mixed $project): bool => 'Injust' === Json::at($project, 'name')))[0] ?? null;
        if (null === $project) {
            return [];
        }
        $client->jsonRequest('GET', '/api/projects/'.Json::string($project, 'id').'/tasks');
        $tasks = [];
        foreach (self::body($client) as $task) {
            if (!$open || false === Json::at($task, 'done')) {
                $tasks[Json::string($task, 'title')] = Json::array($task);
            }
        }
        ksort($tasks);

        return $tasks;
    }

    /**
     * @return array<mixed>
     */
    private static function body(KernelBrowser $client): array
    {
        return Json::decode((string) $client->getResponse()->getContent());
    }
}
