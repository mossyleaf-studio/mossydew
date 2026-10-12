<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261012181915 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Shared goals between friends and what each member contributed';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE goal_member (id UUID NOT NULL, contributed INT NOT NULL, goal_id UUID NOT NULL, user_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX goal_member_user ON goal_member (goal_id, user_id)');
        $this->addSql('CREATE INDEX IDX_EA638F3C667D1AFE ON goal_member (goal_id)');
        $this->addSql('CREATE INDEX IDX_EA638F3CA76ED395 ON goal_member (user_id)');
        $this->addSql('CREATE TABLE shared_goal (id UUID NOT NULL, title VARCHAR(80) NOT NULL, target INT NOT NULL, ends_on DATE NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, reached_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('ALTER TABLE goal_member ADD CONSTRAINT FK_EA638F3C667D1AFE FOREIGN KEY (goal_id) REFERENCES shared_goal (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE goal_member ADD CONSTRAINT FK_EA638F3CA76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE goal_member DROP CONSTRAINT FK_EA638F3C667D1AFE');
        $this->addSql('ALTER TABLE goal_member DROP CONSTRAINT FK_EA638F3CA76ED395');
        $this->addSql('DROP TABLE goal_member');
        $this->addSql('DROP TABLE shared_goal');
    }
}
