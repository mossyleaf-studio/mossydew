<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Tests\Support\SignsInClient;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SameOriginGuardTest extends WebTestCase
{
    use SignsInClient;

    /**
     * @param array<string, string> $headers
     */
    #[DataProvider('crossSiteWrites')]
    public function testCrossSiteWritesAreForbidden(array $headers): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('POST', '/api/tasks', ['title' => 'Phishing'], $headers);

        self::assertResponseStatusCodeSame(403);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
    }

    /** @return iterable<array{array<string, string>}> */
    public static function crossSiteWrites(): iterable
    {
        yield 'fetch metadata' => [['HTTP_SEC_FETCH_SITE' => 'cross-site']];
        yield 'same site but another origin' => [['HTTP_SEC_FETCH_SITE' => 'same-site']];
        yield 'origin header' => [['HTTP_ORIGIN' => 'https://evil.example']];
    }

    #[DataProvider('encodedApiPaths')]
    public function testEncodedApiPathsAreGuarded(string $path): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('POST', $path, ['title' => 'Phishing'], ['HTTP_SEC_FETCH_SITE' => 'same-site']);

        self::assertResponseStatusCodeSame(403);
    }

    /** @return iterable<array{string}> */
    public static function encodedApiPaths(): iterable
    {
        yield 'encoded letter' => ['/%61pi/tasks'];
        yield 'encoded slash' => ['/api%2Ftasks'];
    }

    /**
     * @param array<string, string> $headers
     */
    #[DataProvider('sameOriginWrites')]
    public function testSameOriginWritesPass(array $headers): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('POST', '/api/tasks', ['title' => 'Vet'], $headers);

        self::assertResponseStatusCodeSame(201);
    }

    /** @return iterable<array{array<string, string>}> */
    public static function sameOriginWrites(): iterable
    {
        yield 'no header' => [[]];
        yield 'fetch metadata' => [['HTTP_SEC_FETCH_SITE' => 'same-origin']];
        yield 'origin header' => [['HTTP_ORIGIN' => 'http://localhost']];
    }

    public function testCrossSiteFacilityUpgradesAreForbidden(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('POST', '/api/greenhouse/facilities/glasshouse/upgrade', server: ['HTTP_SEC_FETCH_SITE' => 'cross-site']);

        self::assertResponseStatusCodeSame(403);
    }

    public function testCrossSiteReadsAreLeftToTheSession(): void
    {
        $client = self::signedInClient();

        $client->jsonRequest('GET', '/api/player', server: ['HTTP_SEC_FETCH_SITE' => 'cross-site']);

        self::assertResponseIsSuccessful();
    }
}
