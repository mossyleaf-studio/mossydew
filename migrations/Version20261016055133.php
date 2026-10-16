<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261016055133 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Android and iPad apps: device tokens that open the home-screen widget API, several per user';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE device_token (id UUID NOT NULL, hash VARCHAR(64) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, owner_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_99B2415CD1B862B8 ON device_token (hash)');
        $this->addSql('CREATE INDEX IDX_99B2415C7E3C61F9 ON device_token (owner_id)');
        $this->addSql('ALTER TABLE device_token ADD CONSTRAINT FK_99B2415C7E3C61F9 FOREIGN KEY (owner_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE device_token DROP CONSTRAINT FK_99B2415C7E3C61F9');
        $this->addSql('DROP TABLE device_token');
    }
}
