<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Infrastructure\Security\SecurityUser;
use App\Tests\Support\FreezesClock;
use App\Tests\Support\Json;
use App\Tests\Support\SignsInClient;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class FriendsApiTest extends WebTestCase
{
    use FreezesClock;
    use SignsInClient;

    protected function setUp(): void
    {
        self::freezeAt('2026-10-06 08:00 UTC');
    }

    public function testAnInviteLinkMakesTwoPeopleFriends(): void
    {
        $client = self::signedInClient();
        $louis = self::createUser();
        $mira = self::createUser();

        $client->loginUser(SecurityUser::fromUser($louis));
        $client->jsonRequest('POST', '/api/friends/invites');
        self::assertResponseStatusCodeSame(201);
        $token = Json::string(self::body($client), 'token');
        self::assertSame('2026-10-13T08:00:00+00:00', Json::string(self::body($client), 'expiresAt'));

        $client->jsonRequest('POST', "/api/friends/invites/$token/accept");
        self::assertResponseStatusCodeSame(422);
        self::assertSame('This is your own invitation: send the link to someone else.', Json::string(self::body($client), 'detail'));

        $client->loginUser(SecurityUser::fromUser($mira));
        $client->jsonRequest('GET', "/api/friends/invites/$token");
        self::assertSame('Louis', Json::string(self::body($client), 'inviter'));
        $client->jsonRequest('POST', "/api/friends/invites/$token/accept");
        self::assertResponseStatusCodeSame(201);
        self::assertSame([(string) $louis->id(), 1, 0], [Json::at(self::body($client), 'id'), Json::at(self::body($client), 'level'), Json::at(self::body($client), 'xp')]);

        $client->jsonRequest('POST', "/api/friends/invites/$token/accept");
        self::assertResponseStatusCodeSame(422);
        $client->jsonRequest('GET', "/api/friends/invites/$token");
        self::assertSame('This invitation has already been used. Ask for a new link.', Json::string(self::body($client), 'detail'));

        $client->loginUser(SecurityUser::fromUser($louis));
        $client->jsonRequest('GET', '/api/friends');
        self::assertSame([(string) $mira->id()], array_column(Json::array(self::body($client), 'friends'), 'id'));

        $client->jsonRequest('POST', '/api/friends/invites');
        $again = Json::string(self::body($client), 'token');
        $client->loginUser(SecurityUser::fromUser($mira));
        $client->jsonRequest('POST', "/api/friends/invites/$again/accept");
        self::assertResponseStatusCodeSame(422);
        self::assertSame('Louis is already your friend.', Json::string(self::body($client), 'detail'));

        $client->jsonRequest('DELETE', '/api/friends/'.$louis->id());
        self::assertResponseStatusCodeSame(204);
        $client->jsonRequest('GET', '/api/friends');
        self::assertSame([], Json::array(self::body($client), 'friends'));
        $client->jsonRequest('DELETE', '/api/friends/'.$louis->id());
        self::assertResponseStatusCodeSame(404);
    }

    public function testAnUnknownInviteIsNotFound(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('GET', '/api/friends/invites/'.str_repeat('a', 48));
        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @return array<mixed>
     */
    private static function body(KernelBrowser $client): array
    {
        return Json::decode((string) $client->getResponse()->getContent());
    }
}
