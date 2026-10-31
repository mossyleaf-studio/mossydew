<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261031120103 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The first case of every player is a gift with every clue revealed, and so becomes every case already open';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE investigation ADD gift BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('UPDATE investigation SET gift = true WHERE solved_at IS NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE investigation DROP gift');
    }
}
