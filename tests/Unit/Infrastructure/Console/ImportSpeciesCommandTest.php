<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Console;

use App\Domain\Gamification\Herbarium\Species;
use App\Domain\Gamification\Herbarium\SpeciesCatalog;
use App\Infrastructure\Console\ImportSpeciesCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ImportSpeciesCommandTest extends TestCase
{
    private const string PHOTOS = 'https://static.inaturalist.org/photos';

    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir().'/herbarium-'.bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->projectDir);
    }

    public function testKeepsTheImportedSpeciesWithoutAskingINaturalist(): void
    {
        $catalog = $this->imported(SpeciesCatalog::all());

        $tester = $this->import(new MockHttpClient(static fn (): never => self::fail('iNaturalist was asked.')));

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString(\sprintf('%d species imported.', \count($catalog)), $tester->getDisplay());
        self::assertSame($catalog, $this->json('assets/vue/herbarium/species.json'));
        self::assertSame(array_map(static fn (array $entry): string => 'Name of '.$entry['scientificName'], $catalog), $this->json('assets/vue/i18n/fr/species.json'));
    }

    public function testImportsAnOpenPhotoAndTheCommonNamesAndListsTheSpeciesWithoutOne(): void
    {
        [$fromTaxon, $fromObservation, $unknown] = SpeciesCatalog::all();
        $kept = $this->imported(array_values(array_filter(SpeciesCatalog::all(), static fn (Species $one): bool => !\in_array($one->slug, [$fromTaxon->slug, $unknown->slug], true))));
        unlink($this->projectDir.'/public/herbarium/'.$fromObservation->slug.'.jpg');

        $tester = $this->import(new MockHttpClient(static fn (string $method, string $url): MockResponse => self::inaturalist($url)));

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('No openly licensed photo for: '.$unknown->scientificName, $tester->getDisplay());
        $catalog = $this->json('assets/vue/herbarium/species.json');
        self::assertSame([
            'scientificName' => $fromTaxon->scientificName,
            'taxon' => 'https://www.inaturalist.org/taxa/68293',
            'photo' => '/herbarium/'.$fromTaxon->slug.'.jpg',
            'attribution' => '(c) Micki',
            'license' => 'cc-by',
            'source' => 'https://www.inaturalist.org/photos/7',
        ], $catalog[$fromTaxon->slug]);
        self::assertSame([
            'scientificName' => $fromObservation->scientificName,
            'taxon' => 'https://www.inaturalist.org/taxa/68286',
            'photo' => '/herbarium/'.$fromObservation->slug.'.png',
            'attribution' => '(c) Zoe',
            'license' => 'cc0',
            'source' => 'https://www.inaturalist.org/photos/9',
        ], $catalog[$fromObservation->slug]);
        self::assertArrayNotHasKey($unknown->slug, $catalog);
        self::assertCount(\count($kept) + 1, $catalog);
        self::assertSame('jpeg bytes', file_get_contents($this->projectDir.'/public/herbarium/'.$fromTaxon->slug.'.jpg'));
        self::assertSame('png bytes', file_get_contents($this->projectDir.'/public/herbarium/'.$fromObservation->slug.'.png'));
        self::assertSame(['Common haircap', $fromObservation->scientificName], [$this->json('assets/vue/i18n/en/species.json')[$fromTaxon->slug], $this->json('assets/vue/i18n/en/species.json')[$fromObservation->slug]]);
        self::assertSame(['Polytric commun', $fromObservation->scientificName], [$this->json('assets/vue/i18n/fr/species.json')[$fromTaxon->slug], $this->json('assets/vue/i18n/fr/species.json')[$fromObservation->slug]]);
    }

    private static function inaturalist(string $url): MockResponse
    {
        $path = (string) parse_url($url, \PHP_URL_PATH);
        parse_str((string) parse_url($url, \PHP_URL_QUERY), $query);
        $french = 'fr' === ($query['locale'] ?? null);

        return match ($path) {
            '/photos/7/medium.jpeg' => new MockResponse('jpeg bytes'),
            '/photos/9/medium.png' => new MockResponse('png bytes'),
            '/v1/taxa' => self::results(match ($query['q'] ?? null) {
                'Polytrichum commune' => [['name' => 'Polytrichum commune strictum', 'id' => 1], ['name' => 'Polytrichum commune', 'id' => 68293]],
                'Polytrichum juniperinum' => [['name' => 'Polytrichum juniperinum', 'id' => 68286]],
                default => [],
            }),
            '/v1/taxa/68293' => self::results([[
                'id' => 68293,
                'preferred_common_name' => $french ? 'polytric commun' : 'common haircap',
                'taxon_photos' => [['photo' => ['id' => 6, 'license_code' => 'cc-by-nc']], ['photo' => ['id' => 7, 'license_code' => 'cc-by', 'attribution' => '(c) Micki', 'medium_url' => self::PHOTOS.'/7/medium.jpeg']]],
            ]]),
            '/v1/taxa/68286' => self::results([['id' => 68286, 'preferred_common_name' => '', 'taxon_photos' => [['photo' => ['id' => 8, 'license_code' => null]]]]]),
            '/v1/observations' => self::results('68286' === ($query['taxon_id'] ?? null) ? [
                ['photos' => [['id' => 8, 'license_code' => 'cc-by-nc']]],
                'not an observation',
                ['photos' => [['id' => 9, 'license_code' => 'cc0', 'attribution' => '(c) Zoe', 'url' => self::PHOTOS.'/9/square.png']]],
            ] : []),
            default => self::fail('Unexpected request to '.$url),
        };
    }

    /**
     * @param list<mixed> $results
     */
    private static function results(array $results): MockResponse
    {
        return new MockResponse(json_encode(['results' => $results], \JSON_THROW_ON_ERROR));
    }

    private function import(MockHttpClient $httpClient): CommandTester
    {
        $application = new Application();
        $application->addCommand(new ImportSpeciesCommand($httpClient, $this->projectDir, new MockClock()));
        $tester = new CommandTester($application->find('app:herbarium:import'));
        $tester->execute([]);

        return $tester;
    }

    /**
     * @param list<Species> $species
     *
     * @return array<string, array{scientificName: string, taxon: string, photo: string, attribution: string, license: string, source: string}>
     */
    private function imported(array $species): array
    {
        $filesystem = new Filesystem();
        $catalog = [];
        foreach ($species as $one) {
            $catalog[$one->slug] = [
                'scientificName' => $one->scientificName,
                'taxon' => 'https://www.inaturalist.org/taxa/1',
                'photo' => '/herbarium/'.$one->slug.'.jpg',
                'attribution' => '(c) Someone',
                'license' => 'cc0',
                'source' => 'https://www.inaturalist.org/photos/1',
            ];
            $filesystem->dumpFile($this->projectDir.'/public/herbarium/'.$one->slug.'.jpg', 'photo');
        }
        $names = array_map(static fn (array $entry): string => 'Name of '.$entry['scientificName'], $catalog);
        $filesystem->dumpFile($this->projectDir.'/assets/vue/herbarium/species.json', json_encode($catalog, \JSON_THROW_ON_ERROR));
        $filesystem->dumpFile($this->projectDir.'/assets/vue/i18n/en/species.json', json_encode($names, \JSON_THROW_ON_ERROR));
        $filesystem->dumpFile($this->projectDir.'/assets/vue/i18n/fr/species.json', json_encode($names, \JSON_THROW_ON_ERROR));

        return $catalog;
    }

    /**
     * @return array<mixed>
     */
    private function json(string $path): array
    {
        $data = json_decode((string) file_get_contents($this->projectDir.'/'.$path), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }
}
