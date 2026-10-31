<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Domain\Identity\User;
use App\Domain\Mystery\Investigation;
use App\Domain\Mystery\InvestigationRepository;
use App\Infrastructure\Security\SecurityUser;
use App\Tests\Support\FreezesClock;
use App\Tests\Support\Json;
use App\Tests\Support\SignsInClient;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Ulid;

final class MysteryApiTest extends WebTestCase
{
    use FreezesClock;
    use SignsInClient;

    protected function setUp(): void
    {
        self::freezeAt('2026-10-06 08:00 UTC');
    }

    public function testThereIsNoCaseYet(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('GET', '/api/mystery');

        self::assertResponseIsSuccessful();
        self::assertSame(['investigation' => null, 'solved' => 0], self::body($client));
    }

    public function testOpeningACaseKeepsTheSolutionAndTheHiddenCluesBack(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('POST', '/api/mystery/investigations');
        self::assertResponseStatusCodeSame(201);
        $opened = self::body($client);

        $client->jsonRequest('GET', '/api/mystery');
        self::assertResponseIsSuccessful();
        $body = self::body($client);
        self::assertSame($body, $opened);
        self::assertSame(['investigation', 'solved'], array_keys($body));
        self::assertSame(0, Json::int($body, 'solved'));
        $case = Json::array($body, 'investigation');
        self::assertSame(['id', 'size', 'rooms', 'kinds', 'furniture', 'people', 'victim', 'clues', 'hiddenClues', 'notes', 'solved', 'solution', 'murderer'], array_keys($case));
        $size = Json::int($case, 'size');
        self::assertCount($size * $size, Json::array($case, 'rooms'));
        self::assertCount($size * $size, Json::array($case, 'furniture'));
        self::assertCount($size, Json::array($case, 'people'));
        self::assertContainsOnlyString(Json::array($case, 'kinds'));
        self::assertGreaterThanOrEqual(0, Json::int($case, 'victim'));
        self::assertLessThan($size, Json::int($case, 'victim'));
        self::assertSame([[], false, null, null], [Json::at($case, 'clues'), Json::at($case, 'solved'), Json::at($case, 'solution'), Json::at($case, 'murderer')]);
        self::assertGreaterThan(0, Json::int($case, 'hiddenClues'));
        self::assertSame(['placements' => array_fill(0, $size, null), 'crosses' => []], Json::array($case, 'notes'));
    }

    public function testOnlyOneCaseIsOpenAtATime(): void
    {
        $client = self::signedInClient();
        $client->jsonRequest('POST', '/api/mystery/investigations');

        $client->jsonRequest('POST', '/api/mystery/investigations');

        self::assertResponseStatusCodeSame(422);
        self::assertSame('Finish your current case before opening a new one.', Json::string(self::body($client), 'detail'));

        $client->jsonRequest('POST', '/api/mystery/investigations', server: ['HTTP_ACCEPT_LANGUAGE' => 'fr']);
        self::assertSame('Terminez l’enquête en cours avant d’en ouvrir une nouvelle.', Json::string(self::body($client), 'detail'));
    }

    public function testNotesAreSavedWithTheCase(): void
    {
        $client = self::signedInClient();
        $client->jsonRequest('POST', '/api/mystery/investigations');
        $size = Json::int(self::body($client), 'investigation', 'size');
        $case = Json::string(self::body($client), 'investigation', 'id');
        $placements = array_fill(0, $size, null);
        $placements[2] = 11;

        $client->jsonRequest('PUT', self::notesUrl($case), ['placements' => $placements, 'crosses' => [0, 5]]);
        self::assertResponseStatusCodeSame(204);

        $client->jsonRequest('GET', '/api/mystery');
        self::assertSame(['placements' => $placements, 'crosses' => [0, 5]], Json::array(self::body($client), 'investigation', 'notes'));
    }

    public function testNotesThatDoNotFitAreRefused(): void
    {
        $client = self::signedInClient();
        $client->jsonRequest('POST', '/api/mystery/investigations');
        $size = Json::int(self::body($client), 'investigation', 'size');
        $notes = self::notesUrl(Json::string(self::body($client), 'investigation', 'id'));

        $client->jsonRequest('PUT', $notes, ['placements' => [null], 'crosses' => []]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('These notes do not fit the board.', Json::string(self::body($client), 'detail'));

        $client->jsonRequest('PUT', $notes, ['placements' => [$size * $size, ...array_fill(0, $size - 1, null)], 'crosses' => []]);
        self::assertResponseStatusCodeSame(422);

        $client->jsonRequest('PUT', $notes, ['placements' => array_fill(0, $size, null), 'crosses' => [4, 4]]);
        self::assertResponseStatusCodeSame(422);

        $client->jsonRequest('PUT', $notes, ['placements' => array_fill(0, $size, 'x'), 'crosses' => []]);
        self::assertResponseStatusCodeSame(422);

        $client->jsonRequest('GET', '/api/mystery');
        self::assertSame(['placements' => array_fill(0, $size, null), 'crosses' => []], Json::array(self::body($client), 'investigation', 'notes'));
    }

    public function testNotesAndAccusationsNeedAnOpenCase(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('PUT', self::notesUrl((string) new Ulid()), ['placements' => [], 'crosses' => []]);
        self::assertResponseStatusCodeSame(404);
        self::assertSame('This case no longer exists.', Json::string(self::body($client), 'detail'));

        $client->jsonRequest('POST', self::accusationUrl((string) new Ulid()), ['placements' => [], 'murderer' => 0], ['HTTP_ACCEPT_LANGUAGE' => 'fr']);
        self::assertResponseStatusCodeSame(404);
        self::assertSame('Cette enquête n’existe plus.', Json::string(self::body($client), 'detail'));
    }

    public function testNotesAndAccusationsAimedAtAnEarlierCaseChangeNothing(): void
    {
        [$client, $user] = self::client();
        $client->jsonRequest('POST', '/api/mystery/investigations');
        $earlier = self::caseOf($user);
        $client->jsonRequest('POST', self::accusationUrl((string) $earlier->id()), ['placements' => $earlier->solution(), 'murderer' => $earlier->murderer()]);
        self::assertResponseStatusCodeSame(204);
        $client->jsonRequest('POST', '/api/mystery/investigations');
        $current = self::caseOf($user);

        $client->jsonRequest('PUT', self::notesUrl((string) $earlier->id()), ['placements' => array_fill(0, $earlier->floorplan()->size, null), 'crosses' => [1]]);
        self::assertResponseStatusCodeSame(404);
        self::assertSame('This case no longer exists.', Json::string(self::body($client), 'detail'));

        $client->jsonRequest('POST', self::accusationUrl((string) $earlier->id()), ['placements' => $earlier->solution(), 'murderer' => $earlier->murderer()]);
        self::assertResponseStatusCodeSame(404);

        $client->jsonRequest('GET', '/api/mystery');
        $body = self::body($client);
        self::assertSame([(string) $current->id(), false, 1], [Json::at($body, 'investigation', 'id'), Json::at($body, 'investigation', 'solved'), Json::at($body, 'solved')]);
        self::assertSame(['placements' => array_fill(0, $current->floorplan()->size, null), 'crosses' => []], Json::array($body, 'investigation', 'notes'));
    }

    public function testAWrongAccusationSaysNothingAboutWhatIsWrong(): void
    {
        [$client, $user] = self::client();
        $client->jsonRequest('POST', '/api/mystery/investigations');
        $case = self::caseOf($user);
        $solution = $case->solution();
        $accusation = self::accusationUrl((string) $case->id());

        $client->jsonRequest('POST', $accusation, ['placements' => [$solution[1], $solution[0], ...\array_slice($solution, 2)], 'murderer' => $case->murderer()]);
        self::assertResponseStatusCodeSame(422);
        $swapped = Json::string(self::body($client), 'detail');
        self::assertSame('Something doesn’t add up yet. Look again.', $swapped);

        $client->jsonRequest('POST', $accusation, ['placements' => $solution, 'murderer' => $case->victim()]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame($swapped, Json::string(self::body($client), 'detail'));

        $client->jsonRequest('POST', $accusation, ['placements' => ['one', 'two'], 'murderer' => 'me']);
        self::assertResponseStatusCodeSame(422);

        $client->jsonRequest('POST', $accusation, ['placements' => $solution, 'murderer' => $case->victim()], ['HTTP_ACCEPT_LANGUAGE' => 'fr']);
        self::assertSame('Quelque chose ne colle pas encore. Regardez de plus près.', Json::string(self::body($client), 'detail'));

        $client->jsonRequest('GET', '/api/mystery');
        self::assertSame([false, null, null, 0], [
            Json::at(self::body($client), 'investigation', 'solved'),
            Json::at(self::body($client), 'investigation', 'solution'),
            Json::at(self::body($client), 'investigation', 'murderer'),
            Json::at(self::body($client), 'solved'),
        ]);
    }

    public function testARightAccusationSolvesTheCaseAndRevealsTheSolution(): void
    {
        [$client, $user] = self::client();
        $client->jsonRequest('POST', '/api/mystery/investigations');
        $case = self::caseOf($user);
        $accusation = self::accusationUrl((string) $case->id());

        $client->jsonRequest('POST', $accusation, ['placements' => $case->solution(), 'murderer' => $case->murderer()]);
        self::assertResponseStatusCodeSame(204);

        $client->jsonRequest('GET', '/api/mystery');
        $body = self::body($client);
        self::assertSame(1, Json::int($body, 'solved'));
        self::assertSame([true, $case->solution(), $case->murderer()], [
            Json::at($body, 'investigation', 'solved'),
            Json::at($body, 'investigation', 'solution'),
            Json::at($body, 'investigation', 'murderer'),
        ]);
        self::assertSame($case->solution(), Json::at($body, 'investigation', 'notes', 'placements'));

        $client->jsonRequest('GET', '/api/achievements');
        self::assertNotNull(array_column(self::body($client), 'unlockedAt', 'id')['first_case']);

        $client->jsonRequest('POST', $accusation, ['placements' => $case->solution(), 'murderer' => $case->murderer()]);
        self::assertResponseStatusCodeSame(404);

        $client->jsonRequest('POST', '/api/mystery/investigations');
        self::assertResponseStatusCodeSame(201);
        self::assertSame([false, 1], [Json::at(self::body($client), 'investigation', 'solved'), Json::at(self::body($client), 'solved')]);
    }

    public function testACompletedTaskRevealsAClueOnlyWhileMysteriesAreOn(): void
    {
        $client = self::signedInClient();
        $client->jsonRequest('POST', '/api/mystery/investigations');
        $hidden = Json::int(self::body($client), 'investigation', 'hiddenClues');

        self::assertSame(0, self::completeATask($client));

        $client->jsonRequest('PUT', '/api/me/mysteries', ['enabled' => true]);
        self::assertResponseStatusCodeSame(204);
        self::assertSame(1, self::completeATask($client));

        $client->jsonRequest('GET', '/api/mystery');
        $case = Json::array(self::body($client), 'investigation');
        self::assertCount(1, Json::array($case, 'clues'));
        self::assertSame($hidden - 1, Json::int($case, 'hiddenClues'));
        self::assertContains(Json::string($case, 'clues', 0, 'kind'), ['in_room', 'on_furniture', 'beside_furniture', 'in_row', 'in_column', 'same_room', 'north_of', 'west_of', 'empty_room', 'room_holds']);

        $client->jsonRequest('PUT', '/api/me/mysteries', ['enabled' => false]);
        self::assertSame(0, self::completeATask($client));
    }

    public function testEachPlayerHasTheirOwnCase(): void
    {
        [$client, $user] = self::client();
        $client->jsonRequest('POST', '/api/mystery/investigations');
        $mine = Json::string(self::body($client), 'investigation', 'id');

        $client->loginUser(SecurityUser::fromUser(self::createUser()));

        $client->jsonRequest('GET', '/api/mystery');
        self::assertSame(['investigation' => null, 'solved' => 0], self::body($client));
        $client->jsonRequest('PUT', self::notesUrl($mine), ['placements' => [], 'crosses' => []]);
        self::assertResponseStatusCodeSame(404);
        $client->jsonRequest('POST', '/api/mystery/investigations');
        self::assertResponseStatusCodeSame(201);
        $theirs = Json::string(self::body($client), 'investigation', 'id');
        self::assertNotSame($mine, $theirs);
        $client->jsonRequest('PUT', self::notesUrl($mine), ['placements' => array_fill(0, Json::int(self::body($client), 'investigation', 'size'), null), 'crosses' => [2]]);
        self::assertResponseStatusCodeSame(404);
        self::assertSame($mine, (string) self::caseOf($user)->id());
        self::assertSame([], self::caseOf($user)->notes()['crosses']);
    }

    public function testWritesCanAskForTheRefreshedCase(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('POST', '/api/mystery/investigations', server: ['HTTP_X_REFRESH' => json_encode(['/api/mystery'])]);

        self::assertResponseStatusCodeSame(200);
        self::assertResponseHeaderSame('X-Refreshed', '1');
        $body = self::body($client);
        self::assertSame(Json::at($body, 'data'), Json::at($body, 'refreshed', '/api/mystery'));
        self::assertNotNull(Json::at($body, 'refreshed', '/api/mystery', 'investigation'));

        $client->jsonRequest('PUT', self::notesUrl(Json::string($body, 'data', 'investigation', 'id')), ['placements' => array_fill(0, Json::int($body, 'data', 'investigation', 'size'), null), 'crosses' => [3]], ['HTTP_X_REFRESH' => json_encode(['/api/mystery'])]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame([3], Json::array(self::body($client), 'refreshed', '/api/mystery', 'investigation', 'notes', 'crosses'));
    }

    /**
     * @return array{KernelBrowser, User}
     */
    private static function client(): array
    {
        $client = self::createClient();
        $user = self::createUser();
        $client->loginUser(SecurityUser::fromUser($user));

        return [$client, $user];
    }

    private static function caseOf(User $user): Investigation
    {
        $case = self::getContainer()->get(InvestigationRepository::class)->latestOf($user);
        self::assertNotNull($case);

        return $case;
    }

    private static function notesUrl(string $case): string
    {
        return '/api/mystery/investigations/'.$case.'/notes';
    }

    private static function accusationUrl(string $case): string
    {
        return '/api/mystery/investigations/'.$case.'/accusation';
    }

    private static function completeATask(KernelBrowser $client): int
    {
        $client->jsonRequest('POST', '/api/tasks', ['title' => 'Water the fern']);
        $client->jsonRequest('POST', '/api/tasks/'.Json::string(self::body($client), 'id').'/complete');
        self::assertResponseIsSuccessful();

        return Json::int(self::body($client), 'cluesRevealed');
    }

    /**
     * @return array<mixed>
     */
    private static function body(KernelBrowser $client): array
    {
        return Json::decode((string) $client->getResponse()->getContent());
    }
}
