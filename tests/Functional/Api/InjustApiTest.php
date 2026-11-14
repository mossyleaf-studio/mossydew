<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Domain\Planning\Injust\InjustLink;
use App\Domain\Planning\Project;
use App\Domain\Planning\Task;
use App\Tests\Support\FreezesClock;
use App\Tests\Support\Json;
use App\Tests\Support\SignsInClient;
use Doctrine\ORM\EntityManagerInterface;
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
        $client->request('GET', '/integrations/anything', server: ['HTTP_AUTHORIZATION' => "Bearer $token"]);
        self::assertResponseStatusCodeSame(404);
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
        self::assertSame(['Cover revealed · Untitled EP', 'Guitar playthrough', 'Mixing: She Needs Help'], array_keys($tasks));
        self::assertSame(['2026-10-09', null, null, 'Untitled EP, due Fri 9 Oct.'], [Json::at($tasks['Cover revealed · Untitled EP'], 'dueOn'), Json::at($tasks['Cover revealed · Untitled EP'], 'plannedOn'), Json::at($tasks['Cover revealed · Untitled EP'], 'quadrant'), Json::at($tasks['Cover revealed · Untitled EP'], 'notes')]);
        self::assertSame([null, null, null], array_values(array_map(static fn (array $task): mixed => Json::at($task, 'parentTitle'), $tasks)), 'every item is a plain task');
        self::assertSame([], self::injustTasks($client, groups: true));

        $client->jsonRequest('PATCH', '/api/tasks/'.Json::string($tasks['Mixing: She Needs Help'], 'id'), ['title' => 'Mix it my way', 'projectId' => Json::at($tasks['Mixing: She Needs Help'], 'projectId')]);
        self::assertResponseIsSuccessful();
        $client->jsonRequest('POST', '/api/tasks/'.Json::string($tasks['Cover revealed · Untitled EP'], 'id').'/complete');
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
            self::item('stage:mix', 'b2', 'Mixing: She Needs Help', '2026-10-12', ['Untitled EP'], 'Needed by Mon 12 Oct.'),
            self::item('step:new', 'd1', 'Pitch playlists', '2026-10-20', ['Untitled EP'], 'Spotify for Artists.'),
        ], [['id' => 'step:cover', 'status' => 'done', 'at' => '2026-10-06T08:00:00.000Z']], $epoch, 2));
        self::assertSame([], Json::array(self::journal($client, $token), 'events'));
        $open = self::injustTasks($client, open: true);
        self::assertSame(['Mixing: She Needs Help · Untitled EP', 'Pitch playlists · Untitled EP'], array_keys($open));
        self::assertSame('2026-10-12', Json::at($open['Mixing: She Needs Help · Untitled EP'], 'dueOn'));

        $client->jsonRequest('GET', '/api/player');
        $xp = Json::int(self::body($client), 'xp');
        self::feed($client, $token, self::feedOf(4, [
            self::item('step:new', 'd1', 'Pitch playlists', '2026-10-20', ['Untitled EP'], 'Spotify for Artists.'),
        ], [['id' => 'stage:mix', 'status' => 'done', 'at' => '2026-10-06T08:00:00.000Z']], $epoch, 2));
        self::assertSame(['Pitch playlists · Untitled EP'], array_keys(self::injustTasks($client, open: true)));
        $client->jsonRequest('GET', '/api/player');
        self::assertGreaterThan($xp, Json::int(self::body($client), 'xp'), 'an item done in Injust Release earns its reward here');
        self::assertSame(['seq' => 3, 'instance' => self::INSTANCE, 'item' => 'stage:mix', 'type' => 'done', 'at' => '2026-10-06T08:00:00.000Z'], Json::array(self::journal($client, $token), 'events', 0));

        $client->jsonRequest('POST', '/api/tasks/'.Json::string(self::injustTasks($client)['Pitch playlists · Untitled EP'], 'id').'/complete');
        self::feed($client, $token, self::feedOf(5, [
            self::item('step:new', 'd1', 'Pitch playlists', '2026-10-20', ['Untitled EP'], 'Spotify for Artists.'),
        ], [], $epoch, 4));
        self::assertSame(['Pitch playlists · Untitled EP'], array_keys(self::injustTasks($client, open: true)), 'a tick Injust Release refused comes back open');
    }

    public function testOnlyLateUndatedAndNearItemsBecomeTasks(): void
    {
        $client = self::signedInClient();
        $token = self::token($client);

        self::feed($client, $token, self::feedOf(1, [
            self::item('step:pitch', 'a1', 'Pitch sent', '2026-09-30', ['Untitled EP'], 'Late.'),
            self::item('step:cover', 'b1', 'Cover revealed', '2026-10-20', ['Untitled EP'], 'The last day of the 14.'),
            self::item('step:stats', 'c1', 'Stats reviewed', '2026-10-21', ['Untitled EP'], 'One day too far.'),
            self::item('stage:mix', 'd1', 'Mixing: She Needs Help', null, ['Studio'], 'Current stage.'),
        ]));
        self::assertResponseStatusCodeSame(204);
        self::assertSame(['Cover revealed · Untitled EP', 'Mixing: She Needs Help', 'Pitch sent · Untitled EP'], array_keys(self::injustTasks($client)));

        self::feed($client, $token, self::feedOf(2, [
            self::item('step:pitch', 'a1', 'Pitch sent', '2026-09-30', ['Untitled EP'], 'Late.'),
            self::item('step:cover', 'b2', 'Cover revealed', '2026-11-03', ['Untitled EP'], 'Moved away.'),
            self::item('step:stats', 'c2', 'Stats reviewed', '2026-10-19', ['Untitled EP'], 'Moved closer.'),
            self::item('stage:mix', 'd1', 'Mixing: She Needs Help', null, ['Studio'], 'Current stage.'),
        ]));
        self::assertSame(['Mixing: She Needs Help', 'Pitch sent · Untitled EP', 'Stats reviewed · Untitled EP'], array_keys(self::injustTasks($client)), 'a task now due later goes, an item that comes closer arrives');
        $journal = self::journal($client, $token);
        self::assertSame([], $journal['events'], 'a task removed for being due later is not a skip');

        $client->jsonRequest('POST', '/api/tasks/'.Json::string(self::injustTasks($client)['Pitch sent · Untitled EP'], 'id').'/complete');
        self::assertResponseIsSuccessful();
        self::feed($client, $token, self::feedOf(3, [
            self::item('step:pitch', 'a2', 'Pitch sent', '2026-11-03', ['Untitled EP'], 'Refused and moved away.'),
        ], [], Json::string($journal, 'epoch'), 1));
        $tasks = self::injustTasks($client) + self::injustTasks($client, groups: true);
        self::assertSame(['Pitch sent · Untitled EP'], array_keys($tasks));
        self::assertTrue(Json::at($tasks['Pitch sent · Untitled EP'], 'done'), 'a done task stays, even when its item is due later');
    }

    public function testTasksLeaveTheParentsOfTheEarlierGrouping(): void
    {
        $client = self::signedInClient();
        $token = self::token($client);
        $open = [
            self::item('step:cover', 'a1', 'Cover revealed', '2026-10-09', ['Untitled EP'], 'Cover.'),
            self::item('step:pitch', 'b1', 'Pitch playlists', '2026-10-12', ['Untitled EP'], 'Pitch.'),
            self::item('post:r:2026-10-08', 'c1', 'Guitar playthrough', '2026-10-08', ['Posts'], 'Reels first.'),
        ];
        self::feed($client, $token, self::feedOf(1, [...$open, self::item('cut:r:2026-10-08', 'd1', 'Cut: Darkest Memories', '2026-10-08', ['Cut'], 'Cut it.')]));
        self::assertResponseStatusCodeSame(204);

        self::group('Untitled EP', ['step:cover' => 'Cover revealed', 'step:pitch' => 'Pitch playlists']);
        self::group('Posts', ['post:r:2026-10-08' => 'Guitar playthrough']);
        self::group('Cut', ['cut:r:2026-10-08' => 'Cut: Darkest Memories']);
        self::group('Shoot', []);
        self::group('Studio', [], 'My own note');
        self::assertSame(['Cut', 'Posts', 'Studio', 'Untitled EP'], array_keys(self::injustTasks($client, groups: true)));
        self::assertSame(['Cover revealed' => 'Untitled EP', 'Cut: Darkest Memories' => 'Cut', 'Guitar playthrough' => 'Posts', 'My own note' => 'Studio', 'Pitch playlists' => 'Untitled EP', 'Shoot' => null], array_map(static fn (array $task): mixed => Json::at($task, 'parentTitle'), self::injustTasks($client)));

        $client->jsonRequest('POST', '/api/tasks/'.Json::string(self::injustTasks($client)['Cut: Darkest Memories'], 'id').'/complete');
        self::assertResponseIsSuccessful();
        $client->request('GET', '/integrations/injust/journal', server: ['HTTP_AUTHORIZATION' => "Bearer $token"]);
        $epoch = Json::string(self::body($client), 'epoch');

        self::feed($client, $token, self::feedOf(2, $open, [['id' => 'cut:r:2026-10-08', 'status' => 'done', 'at' => '2026-10-06T08:00:00.000Z']], $epoch, 1));
        self::assertResponseStatusCodeSame(204);

        $tasks = self::injustTasks($client, open: true);
        self::assertSame(['Cover revealed · Untitled EP', 'Guitar playthrough', 'My own note', 'Pitch playlists · Untitled EP'], array_keys($tasks), 'the steps are rewritten although their revision did not change');
        self::assertSame('Studio', Json::at($tasks['My own note'], 'parentTitle'));
        foreach (['Cover revealed · Untitled EP', 'Guitar playthrough', 'Pitch playlists · Untitled EP'] as $title) {
            self::assertSame([null, null], [Json::at($tasks[$title], 'parentTitle'), Json::at($tasks[$title], 'plannedOn')], $title);
        }
        $all = self::injustTasks($client) + self::injustTasks($client, groups: true);
        self::assertSame(['Cover revealed · Untitled EP', 'Cut', 'Cut: Darkest Memories', 'Guitar playthrough', 'My own note', 'Pitch playlists · Untitled EP', 'Studio'], array_keys(self::sorted($all)), 'the open parents left empty are deleted');
        self::assertSame([true, true, null], [Json::at($all['Cut'], 'done'), Json::at($all['Cut: Darkest Memories'], 'done'), Json::at($all['Cut: Darkest Memories'], 'parentTitle')]);
        self::assertSame(['Studio'], array_keys(self::injustTasks($client, open: true, groups: true)));
    }

    /**
     * @param array<string, string> $items
     */
    private static function group(string $name, array $items, ?string $own = null): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $project = $entityManager->getRepository(Project::class)->findOneBy(['name' => 'Injust']);
        self::assertInstanceOf(Project::class, $project);
        $now = new \DateTimeImmutable('2026-10-06 08:00 UTC');
        $parent = Task::create($project->owner(), $name, $now);
        $parent->linkTo(InjustLink::GROUP.$name);
        $parent->fileUnder($project);
        $entityManager->persist($parent);
        foreach ($items as $itemId => $title) {
            $task = $entityManager->getRepository(Task::class)->findOneBy(['externalId' => InjustLink::PREFIX.$itemId]);
            self::assertInstanceOf(Task::class, $task);
            $task->rename($title);
            $task->nestUnder($parent);
        }
        if (null !== $own) {
            $entityManager->persist($parent->addSubtask($own, $now));
        }
        $entityManager->flush();
    }

    /**
     * @param array<string, array<mixed>> $tasks
     *
     * @return array<string, array<mixed>>
     */
    private static function sorted(array $tasks): array
    {
        ksort($tasks);

        return $tasks;
    }

    public function testTheInjustProjectGoesLast(): void
    {
        $client = self::signedInClient();
        $client->jsonRequest('POST', '/api/projects', ['name' => 'Zoo']);
        $token = self::token($client);

        self::feed($client, $token, self::feedOf(1, [self::item('step:cover', 'a1', 'Cover revealed', null, [], 'Cover.')]));
        self::assertResponseStatusCodeSame(204);

        $client->jsonRequest('GET', '/api/projects');
        self::assertSame(['Zoo', 'Injust'], array_column(self::body($client), 'name'));
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
    private static function injustTasks(KernelBrowser $client, bool $open = false, bool $groups = false): array
    {
        $client->jsonRequest('GET', '/api/projects');
        $project = array_values(array_filter(self::body($client), static fn (mixed $project): bool => 'Injust' === Json::at($project, 'name')))[0] ?? null;
        if (null === $project) {
            return [];
        }
        $client->jsonRequest('GET', '/api/projects/'.Json::string($project, 'id').'/tasks');
        $tasks = [];
        foreach (self::body($client) as $task) {
            if ((!$open || false === Json::at($task, 'done')) && $groups === Json::int($task, 'subtaskCount') > 0) {
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
