<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261028172328 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Users choose the classic or cozy design and the Lucide or Phosphor icons';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user ADD design VARCHAR(10) DEFAULT \'classic\' NOT NULL');
        $this->addSql('ALTER TABLE app_user ADD icon_set VARCHAR(10) DEFAULT \'lucide\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user DROP design');
        $this->addSql('ALTER TABLE app_user DROP icon_set');
    }
}
