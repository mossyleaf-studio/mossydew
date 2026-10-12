<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261012180619 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Injust Release link: access tokens, the link with its last feed, the journal of events, and the revision of each synced task';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE access_token (id UUID NOT NULL, hash VARCHAR(64) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, owner_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B6A2DD68D1B862B8 ON access_token (hash)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B6A2DD687E3C61F9 ON access_token (owner_id)');
        $this->addSql('CREATE TABLE injust_event (id UUID NOT NULL, seq INT NOT NULL, item VARCHAR(120) NOT NULL, type VARCHAR(8) NOT NULL, at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, owner_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX injust_event_owner_seq ON injust_event (owner_id, seq)');
        $this->addSql('CREATE INDEX IDX_8AD11A387E3C61F9 ON injust_event (owner_id)');
        $this->addSql('CREATE TABLE injust_link (id UUID NOT NULL, instance VARCHAR(64) DEFAULT NULL, feed_seq INT DEFAULT NULL, feed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, last_event_seq INT NOT NULL, owner_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_425522CA7E3C61F9 ON injust_link (owner_id)');
        $this->addSql('ALTER TABLE access_token ADD CONSTRAINT FK_B6A2DD687E3C61F9 FOREIGN KEY (owner_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE injust_event ADD CONSTRAINT FK_8AD11A387E3C61F9 FOREIGN KEY (owner_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE injust_link ADD CONSTRAINT FK_425522CA7E3C61F9 FOREIGN KEY (owner_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE task ADD external_revision VARCHAR(16) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE access_token DROP CONSTRAINT FK_B6A2DD687E3C61F9');
        $this->addSql('ALTER TABLE injust_event DROP CONSTRAINT FK_8AD11A387E3C61F9');
        $this->addSql('ALTER TABLE injust_link DROP CONSTRAINT FK_425522CA7E3C61F9');
        $this->addSql('DROP TABLE access_token');
        $this->addSql('DROP TABLE injust_event');
        $this->addSql('DROP TABLE injust_link');
        $this->addSql('ALTER TABLE task DROP external_revision');
    }
}
