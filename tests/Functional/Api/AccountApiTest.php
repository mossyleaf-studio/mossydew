<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Tests\Support\SignsInClient;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AccountApiTest extends WebTestCase
{
    use SignsInClient;

    public function testChangeTimezone(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('PUT', '/api/me/timezone', ['timezone' => 'Asia/Tokyo']);
        self::assertResponseStatusCodeSame(204);

        $client->jsonRequest('PUT', '/api/me/timezone', ['timezone' => 'Mars/Olympus']);
        self::assertResponseStatusCodeSame(422);
    }

    public function testChooseLanguage(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('PUT', '/api/me/language', ['language' => 'fr']);
        self::assertResponseStatusCodeSame(204);

        $client->jsonRequest('PUT', '/api/me/language', ['language' => 'de']);
        self::assertResponseStatusCodeSame(422);
    }

    public function testChooseDesign(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('PUT', '/api/me/design', ['design' => 'cozy']);
        self::assertResponseStatusCodeSame(204);

        $client->jsonRequest('PUT', '/api/me/design', ['design' => 'brutalist']);
        self::assertResponseStatusCodeSame(422);
    }

    public function testChooseIcons(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('PUT', '/api/me/icons', ['icons' => 'phosphor']);
        self::assertResponseStatusCodeSame(204);

        $client->jsonRequest('PUT', '/api/me/icons', ['icons' => 'emoji']);
        self::assertResponseStatusCodeSame(422);
    }
}
