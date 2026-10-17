<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261017073203 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Reopening a task takes back its XP and dew: tasks keep what they earned, players keep the highest level reached';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE player ADD highest_level INT DEFAULT 1 NOT NULL');
        $this->addSql('UPDATE player SET highest_level = xp / 150 + 1');
        $this->addSql('ALTER TABLE player ALTER highest_level DROP DEFAULT');
        $this->addSql('ALTER TABLE task ADD reward_xp INT DEFAULT NULL');
        $this->addSql('ALTER TABLE task ADD reward_dew INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE player DROP highest_level');
        $this->addSql('ALTER TABLE task DROP reward_xp');
        $this->addSql('ALTER TABLE task DROP reward_dew');
    }
}
