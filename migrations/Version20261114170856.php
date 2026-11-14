<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261114170856 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Every project has a position chosen by its owner, starting from today’s alphabetical order';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE project ADD position INT DEFAULT 0 NOT NULL');
        $this->addSql('UPDATE project SET position = ranked.position FROM (SELECT id, ROW_NUMBER() OVER (PARTITION BY owner_id ORDER BY name) - 1 AS position FROM project) ranked WHERE project.id = ranked.id');
        $this->addSql('ALTER TABLE project ALTER position DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE project DROP position');
    }
}
