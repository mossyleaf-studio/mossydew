<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Tests\Support\FreezesClock;
use App\Tests\Support\Json;
use App\Tests\Support\SignsInClient;
use App\Tests\Support\SuperProductivityBackups;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ImportApiTest extends WebTestCase
{
    use FreezesClock;
    use SignsInClient;

    protected function setUp(): void
    {
        self::freezeAt('2026-10-06 08:00 UTC');
    }

    public function testImportsASuperProductivityBackup(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('POST', '/api/imports/super-productivity', SuperProductivityBackups::sample());
        self::assertResponseIsSuccessful();
        self::assertSame(['imported' => 6, 'skipped' => 0, 'projects' => 2], Json::decode((string) $client->getResponse()->getContent()));

        $client->jsonRequest('GET', '/api/tasks/inbox');
        self::assertStringContainsString('Book the vet', (string) $client->getResponse()->getContent());
    }

    public function testRefusesAnotherFile(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('POST', '/api/imports/super-productivity', ['hello' => 'world']);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('This file is not a Super Productivity backup. Export one from Super Productivity’s settings.', Json::string(Json::decode((string) $client->getResponse()->getContent()), 'detail'));
    }
}
