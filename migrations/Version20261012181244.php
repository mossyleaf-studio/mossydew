<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261012181244 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Friends: single-use invitation links and friendships between two users';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE friend_invite (id UUID NOT NULL, hash VARCHAR(64) NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, inviter_id UUID NOT NULL, accepted_by_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_16F9E7BED1B862B8 ON friend_invite (hash)');
        $this->addSql('CREATE INDEX IDX_16F9E7BEB79F4F04 ON friend_invite (inviter_id)');
        $this->addSql('CREATE INDEX IDX_16F9E7BE20F699D9 ON friend_invite (accepted_by_id)');
        $this->addSql('CREATE TABLE friendship (id UUID NOT NULL, since TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, first_id UUID NOT NULL, second_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX friendship_pair ON friendship (first_id, second_id)');
        $this->addSql('CREATE INDEX IDX_7234A45FE84D625F ON friendship (first_id)');
        $this->addSql('CREATE INDEX IDX_7234A45FFF961BCC ON friendship (second_id)');
        $this->addSql('ALTER TABLE friend_invite ADD CONSTRAINT FK_16F9E7BEB79F4F04 FOREIGN KEY (inviter_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE friend_invite ADD CONSTRAINT FK_16F9E7BE20F699D9 FOREIGN KEY (accepted_by_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE friendship ADD CONSTRAINT FK_7234A45FE84D625F FOREIGN KEY (first_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE friendship ADD CONSTRAINT FK_7234A45FFF961BCC FOREIGN KEY (second_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE friend_invite DROP CONSTRAINT FK_16F9E7BEB79F4F04');
        $this->addSql('ALTER TABLE friend_invite DROP CONSTRAINT FK_16F9E7BE20F699D9');
        $this->addSql('ALTER TABLE friendship DROP CONSTRAINT FK_7234A45FE84D625F');
        $this->addSql('ALTER TABLE friendship DROP CONSTRAINT FK_7234A45FFF961BCC');
        $this->addSql('DROP TABLE friend_invite');
        $this->addSql('DROP TABLE friendship');
    }
}
