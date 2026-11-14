<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Infrastructure\Security\SecurityUser;
use App\Tests\Support\Json;
use App\Tests\Support\SignsInClient;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ProjectApiTest extends WebTestCase
{
    use SignsInClient;

    public function testProjectLifecycle(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('POST', '/api/projects', ['name' => 'Home', 'color' => 'berry']);
        self::assertResponseStatusCodeSame(201);
        $id = Json::string(self::body($client), 'id');
        self::assertSame(['Home', 'berry'], [Json::at(self::body($client), 'name'), Json::at(self::body($client), 'color')]);

        $client->jsonRequest('POST', '/api/tasks', ['title' => 'Laundry', 'projectId' => $id]);
        self::assertResponseStatusCodeSame(201);

        $client->jsonRequest('GET', "/api/projects/$id/tasks");
        self::assertResponseIsSuccessful();
        self::assertSame(['Laundry'], array_column(self::body($client), 'title'));

        $client->jsonRequest('PATCH', "/api/projects/$id", ['name' => 'Home sweet home', 'color' => 'lichen']);
        self::assertResponseIsSuccessful();
        self::assertSame('lichen', Json::string(self::body($client), 'color'));

        $client->jsonRequest('GET', '/api/projects');
        self::assertSame(['Home sweet home'], array_column(self::body($client), 'name'));

        $client->jsonRequest('DELETE', "/api/projects/$id");
        self::assertResponseStatusCodeSame(204);

        $client->jsonRequest('GET', '/api/tasks/inbox');
        self::assertSame(['Laundry'], array_column(self::body($client), 'title'));
    }

    public function testInvalidProjectsAre422(): void
    {
        $client = self::signedInClient();
        $client->jsonRequest('POST', '/api/projects', ['name' => 'Home']);

        foreach ([['name' => 'home'], ['name' => ''], ['name' => str_repeat('a', 61)], ['name' => 'Work', 'color' => 'black']] as $payload) {
            $client->jsonRequest('POST', '/api/projects', $payload);
            self::assertResponseStatusCodeSame(422, (string) json_encode($payload));
        }
    }

    public function testAnotherUsersProjectIs404(): void
    {
        $client = self::signedInClient();
        $client->jsonRequest('POST', '/api/projects', ['name' => 'Secret']);
        $id = Json::string(self::body($client), 'id');
        $client->loginUser(SecurityUser::fromUser(self::createUser()));

        $client->jsonRequest('GET', "/api/projects/$id/tasks");
        self::assertResponseStatusCodeSame(404);
        $client->jsonRequest('PATCH', "/api/projects/$id", ['name' => 'Mine']);
        self::assertResponseStatusCodeSame(404);
        $client->jsonRequest('DELETE', "/api/projects/$id");
        self::assertResponseStatusCodeSame(404);
        $client->jsonRequest('POST', '/api/tasks', ['title' => 'Sneaky', 'projectId' => $id]);
        self::assertResponseStatusCodeSame(404);
    }

    public function testReorderProjects(): void
    {
        $client = self::signedInClient();
        $client->jsonRequest('POST', '/api/projects', ['name' => 'Home']);
        $home = Json::string(self::body($client), 'id');
        $client->jsonRequest('POST', '/api/projects', ['name' => 'Work']);
        $work = Json::string(self::body($client), 'id');

        $client->jsonRequest('PUT', '/api/projects/order', ['ids' => [$work, $home]]);
        self::assertResponseStatusCodeSame(204);
        $client->jsonRequest('GET', '/api/projects');
        self::assertSame(['Work', 'Home'], array_column(self::body($client), 'name'));

        $client->jsonRequest('PUT', '/api/projects/order', ['ids' => ['not-a-ulid']]);
        self::assertResponseStatusCodeSame(422);

        $client->loginUser(SecurityUser::fromUser(self::createUser()));
        $client->jsonRequest('PUT', '/api/projects/order', ['ids' => [$home]]);
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
