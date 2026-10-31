<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Domain\Mystery\CaseGenerator;
use App\Domain\Mystery\Clue;
use App\Domain\Mystery\Gender;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;
use Random\Randomizer;

final class Version20261031132711 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Everyone in a case has a gender and every case states its board rules; every open case is drawn again at its size with one clue per person, the victim’s clue given from the start and as many clues revealed as before';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE investigation ADD genders JSON DEFAULT '[]' NOT NULL");
        $this->addSql("ALTER TABLE investigation ADD rules JSON DEFAULT '[]' NOT NULL");
        foreach ($this->connection->fetchAllKeyValue('SELECT id, people FROM investigation WHERE solved_at IS NOT NULL') as $id => $people) {
            $this->addSql(
                'UPDATE investigation SET genders = :genders WHERE id = :id',
                ['id' => $id, 'genders' => array_map(static fn (string $name): string => CaseGenerator::genderOf($name)->value, json_decode((string) $people, true, flags: \JSON_THROW_ON_ERROR))],
                ['genders' => Types::JSON],
            );
        }
        $generator = new CaseGenerator(new Randomizer());
        foreach ($this->connection->fetchAllAssociative('SELECT id, floorplan, revealed_by FROM investigation WHERE solved_at IS NULL') as ['id' => $id, 'floorplan' => $floorplan, 'revealed_by' => $revealed]) {
            $blueprint = $generator->generate(json_decode((string) $floorplan, true, flags: \JSON_THROW_ON_ERROR)['size']);
            $this->addSql(
                'UPDATE investigation SET floorplan = :floorplan, people = :people, genders = :genders, victim = :victim, solution = :solution, clues = :clues, rules = :rules, revealed_by = :revealed, notes = :notes WHERE id = :id',
                [
                    'id' => $id,
                    'floorplan' => $blueprint->floorplan->toArray(),
                    'people' => $blueprint->people,
                    'genders' => array_map(static fn (Gender $gender): string => $gender->value, $blueprint->genders),
                    'victim' => $blueprint->victim,
                    'solution' => $blueprint->solution,
                    'clues' => array_map(static fn (Clue $clue): array => $clue->toArray(), $blueprint->clues),
                    'rules' => array_map(static fn (Clue $rule): array => $rule->toArray(), $blueprint->rules),
                    'revealed' => \array_slice(json_decode((string) $revealed, true, flags: \JSON_THROW_ON_ERROR), 1),
                    'notes' => ['placements' => array_fill(0, $blueprint->floorplan->size, null), 'crosses' => []],
                ],
                ['floorplan' => Types::JSON, 'people' => Types::JSON, 'genders' => Types::JSON, 'solution' => Types::JSON, 'clues' => Types::JSON, 'rules' => Types::JSON, 'revealed' => Types::JSON, 'notes' => Types::JSON],
            );
        }
        $this->addSql('ALTER TABLE investigation ALTER genders DROP DEFAULT');
        $this->addSql('ALTER TABLE investigation ALTER rules DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException();
    }
}
