<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261031094947 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Players opt into Moss Mysteries, logic puzzles whose clues are revealed by completed tasks';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE investigation (id UUID NOT NULL, floorplan JSON NOT NULL, people JSON NOT NULL, victim SMALLINT NOT NULL, solution JSON NOT NULL, clues JSON NOT NULL, revealed_by JSON NOT NULL, notes JSON NOT NULL, opened_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, solved_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, owner_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX investigation_owner_open ON investigation (owner_id) WHERE (solved_at IS NULL)');
        $this->addSql('CREATE INDEX IDX_C3A58AA67E3C61F9 ON investigation (owner_id)');
        $this->addSql('ALTER TABLE investigation ADD CONSTRAINT FK_C3A58AA67E3C61F9 FOREIGN KEY (owner_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE app_user ADD plays_mysteries BOOLEAN DEFAULT false NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE investigation DROP CONSTRAINT FK_C3A58AA67E3C61F9');
        $this->addSql('DROP TABLE investigation');
        $this->addSql('ALTER TABLE app_user DROP plays_mysteries');
    }
}
