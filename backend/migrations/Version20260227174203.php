<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260227174203 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE game_event (id UUID NOT NULL, type VARCHAR(30) NOT NULL, day INT NOT NULL, hour INT NOT NULL, tick INT NOT NULL, narrative TEXT DEFAULT NULL, metadata JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, game_id UUID NOT NULL, player_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_99D7328E48FD905 ON game_event (game_id)');
        $this->addSql('CREATE INDEX IDX_99D732899E6F5DF ON game_event (player_id)');
        $this->addSql('CREATE INDEX idx_game_event_game_tick ON game_event (game_id, tick)');
        $this->addSql('ALTER TABLE game_event ADD CONSTRAINT FK_99D7328E48FD905 FOREIGN KEY (game_id) REFERENCES game (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE game_event ADD CONSTRAINT FK_99D732899E6F5DF FOREIGN KEY (player_id) REFERENCES player (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE game ADD current_day INT DEFAULT NULL');
        $this->addSql('ALTER TABLE game ADD current_hour INT DEFAULT NULL');
        $this->addSql('ALTER TABLE game ADD current_tick INT DEFAULT NULL');
        $this->addSql('ALTER TABLE game ADD started_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE game_event DROP CONSTRAINT FK_99D7328E48FD905');
        $this->addSql('ALTER TABLE game_event DROP CONSTRAINT FK_99D732899E6F5DF');
        $this->addSql('DROP TABLE game_event');
        $this->addSql('ALTER TABLE game DROP current_day');
        $this->addSql('ALTER TABLE game DROP current_hour');
        $this->addSql('ALTER TABLE game DROP current_tick');
        $this->addSql('ALTER TABLE game DROP started_at');
    }
}
