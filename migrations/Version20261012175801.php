<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261012175801 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Tasks remember the id they were imported or synced from (Super Productivity, Injust Release)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE task ADD external_id VARCHAR(120) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX task_owner_external ON task (owner_id, external_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX task_owner_external');
        $this->addSql('ALTER TABLE task DROP external_id');
    }
}
