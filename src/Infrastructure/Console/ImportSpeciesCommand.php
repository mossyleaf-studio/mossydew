<?php

declare(strict_types=1);

namespace App\Infrastructure\Console;

use App\Domain\Gamification\Herbarium\Species;
use App\Domain\Gamification\Herbarium\SpeciesCatalog;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpClient\RetryableHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(name: 'app:herbarium:import', description: 'Download a photo and the common names of every herbarium species not imported yet from iNaturalist')]
final readonly class ImportSpeciesCommand
{
    private const string API = 'https://api.inaturalist.org/v1';
    private const array OPEN_LICENCES = ['cc0', 'cc-by'];
    private const array LOCALES = ['en', 'fr'];
    private const int PAUSE_SECONDS = 1;
    private const int RETRIES = 5;
    private const string CATALOG = 'assets/vue/herbarium/species.json';

    private HttpClientInterface $httpClient;

    public function __construct(
        HttpClientInterface $httpClient,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
        private ClockInterface $clock,
    ) {
        $this->httpClient = new RetryableHttpClient($httpClient, maxRetries: self::RETRIES);
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $imported = $this->readJson(self::CATALOG);
        $importedNames = array_combine(self::LOCALES, array_map(fn (string $locale): array => $this->readJson('assets/vue/i18n/'.$locale.'/species.json'), self::LOCALES));
        $catalog = [];
        $names = array_fill_keys(self::LOCALES, []);
        $missing = [];

        foreach ($io->progressIterate(SpeciesCatalog::all()) as $species) {
            $entry = $this->isImported($species, $imported, $importedNames)
                ? [$imported[$species->slug], array_map(static fn (array $localeNames): mixed => $localeNames[$species->slug], $importedNames)]
                : $this->import($species);
            if (null === $entry) {
                $missing[] = $species->scientificName;
                continue;
            }
            [$catalog[$species->slug], $localized] = $entry;
            foreach ($localized as $locale => $name) {
                $names[$locale][$species->slug] = $name;
            }
        }

        $this->writeJson(self::CATALOG, $catalog);
        foreach ($names as $locale => $localeNames) {
            $this->writeJson('assets/vue/i18n/'.$locale.'/species.json', $localeNames);
        }

        if ([] !== $missing) {
            $io->warning('No openly licensed photo for: '.implode(', ', $missing));

            return Command::FAILURE;
        }
        $io->success(\sprintf('%d species imported.', \count($catalog)));

        return Command::SUCCESS;
    }

    /**
     * @return ?array{array<string, mixed>, array<string, string>}
     */
    private function import(Species $species): ?array
    {
        $taxon = $this->taxon($species, 'en');
        $photo = null === $taxon ? null : $this->openPhoto($taxon);
        if (null === $taxon || null === $photo) {
            return null;
        }

        $file = $this->download($species, $photo);
        $entry = [
            'scientificName' => $species->scientificName,
            'taxon' => 'https://www.inaturalist.org/taxa/'.self::int($taxon, 'id'),
            'photo' => '/herbarium/'.$file,
            'attribution' => self::string($photo, 'attribution'),
            'license' => self::string($photo, 'license_code'),
            'source' => 'https://www.inaturalist.org/photos/'.self::int($photo, 'id'),
        ];
        $names = [];
        foreach (self::LOCALES as $locale) {
            $localized = 'en' === $locale ? $taxon : $this->taxon($species, $locale);
            $names[$locale] = mb_ucfirst(self::nullableString($localized ?? [], 'preferred_common_name') ?? $species->scientificName);
        }

        return [$entry, $names];
    }

    /**
     * @return array<mixed>|null
     */
    private function taxon(Species $species, string $locale): ?array
    {
        $results = $this->get('/taxa', ['q' => $species->scientificName, 'rank' => 'species', 'locale' => $locale, 'per_page' => 10]);
        foreach ($results as $result) {
            if (\is_array($result) && $species->scientificName === ($result['name'] ?? null)) {
                $taxon = $this->get('/taxa/'.self::int($result, 'id'), ['locale' => $locale])[0] ?? null;

                return \is_array($taxon) ? $taxon : null;
            }
        }

        return null;
    }

    /**
     * @param array<mixed> $taxon
     *
     * @return array<mixed>|null
     */
    private function openPhoto(array $taxon): ?array
    {
        return self::taxonPhoto($taxon) ?? $this->observedPhoto($taxon);
    }

    /**
     * @param array<mixed> $taxon
     *
     * @return array<mixed>|null
     */
    private static function taxonPhoto(array $taxon): ?array
    {
        $taxonPhotos = \is_array($taxon['taxon_photos'] ?? null) ? $taxon['taxon_photos'] : [];
        foreach ($taxonPhotos as $taxonPhoto) {
            $photo = \is_array($taxonPhoto) && \is_array($taxonPhoto['photo'] ?? null) ? $taxonPhoto['photo'] : [];
            if (self::isOpen($photo)) {
                return $photo;
            }
        }

        return null;
    }

    /**
     * @param array<mixed> $taxon
     *
     * @return array<mixed>|null
     */
    private function observedPhoto(array $taxon): ?array
    {
        $observations = $this->get('/observations', [
            'taxon_id' => self::int($taxon, 'id'),
            'photo_license' => implode(',', self::OPEN_LICENCES),
            'quality_grade' => 'research',
            'order_by' => 'votes',
            'per_page' => 10,
        ]);
        foreach ($observations as $observation) {
            $photos = \is_array($observation) && \is_array($observation['photos'] ?? null) ? $observation['photos'] : [];
            foreach ($photos as $photo) {
                if (\is_array($photo) && self::isOpen($photo)) {
                    return $photo;
                }
            }
        }

        return null;
    }

    /**
     * @param array<mixed> $photo
     */
    private function download(Species $species, array $photo): string
    {
        $url = self::nullableString($photo, 'medium_url') ?? str_replace('/square.', '/medium.', self::string($photo, 'url'));
        $extension = strtolower(pathinfo((string) parse_url($url, \PHP_URL_PATH), \PATHINFO_EXTENSION));
        $file = $species->slug.'.'.('jpeg' === $extension ? 'jpg' : $extension);

        $directory = $this->projectDir.'/public/herbarium';
        if (!is_dir($directory)) {
            mkdir($directory, 0o755, true);
        }
        file_put_contents($directory.'/'.$file, $this->httpClient->request('GET', $url)->getContent());

        return $file;
    }

    /**
     * @param array<string, int|string> $query
     *
     * @return list<mixed>
     */
    private function get(string $path, array $query): array
    {
        $this->clock->sleep(self::PAUSE_SECONDS);
        $results = $this->httpClient->request('GET', self::API.$path, ['query' => $query])->toArray()['results'] ?? [];

        return \is_array($results) ? array_values($results) : [];
    }

    /**
     * @param array<mixed>                $imported
     * @param array<string, array<mixed>> $importedNames
     */
    private function isImported(Species $species, array $imported, array $importedNames): bool
    {
        $entry = $imported[$species->slug] ?? null;
        if (!\is_array($entry) || null === self::nullableString($entry, 'photo') || !is_file($this->projectDir.'/public'.self::string($entry, 'photo'))) {
            return false;
        }

        return array_all($importedNames, static fn (array $names): bool => \is_string($names[$species->slug] ?? null));
    }

    /**
     * @return array<mixed>
     */
    private function readJson(string $path): array
    {
        $file = $this->projectDir.'/'.$path;
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : [];

        return \is_array($data) ? $data : [];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function writeJson(string $path, array $data): void
    {
        $file = $this->projectDir.'/'.$path;
        if (!is_dir(\dirname($file))) {
            mkdir(\dirname($file), 0o755, true);
        }
        file_put_contents($file, json_encode($data, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR)."\n");
    }

    /**
     * @param array<mixed> $photo
     */
    private static function isOpen(array $photo): bool
    {
        return \in_array($photo['license_code'] ?? null, self::OPEN_LICENCES, true);
    }

    /**
     * @param array<mixed> $data
     */
    private static function int(array $data, string $key): int
    {
        $value = $data[$key] ?? null;

        return \is_int($value) ? $value : throw new \UnexpectedValueException(\sprintf('iNaturalist sent no integer "%s".', $key));
    }

    /**
     * @param array<mixed> $data
     */
    private static function string(array $data, string $key): string
    {
        return self::nullableString($data, $key) ?? throw new \UnexpectedValueException(\sprintf('iNaturalist sent no string "%s".', $key));
    }

    /**
     * @param array<mixed> $data
     */
    private static function nullableString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return \is_string($value) && '' !== $value ? $value : null;
    }
}
