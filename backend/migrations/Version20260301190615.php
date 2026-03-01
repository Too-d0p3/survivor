<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260301190615 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE major_event (id UUID NOT NULL, type VARCHAR(20) NOT NULL, summary VARCHAR(200) NOT NULL, emotional_weight INT NOT NULL, day INT NOT NULL, hour INT NOT NULL, tick INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, game_id UUID NOT NULL, source_event_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_75C73A2AE48FD905 ON major_event (game_id)');
        $this->addSql('CREATE INDEX IDX_75C73A2A32D7DE59 ON major_event (source_event_id)');
        $this->addSql('CREATE INDEX idx_major_event_game_tick ON major_event (game_id, tick)');
        $this->addSql('CREATE INDEX idx_major_event_game_type ON major_event (game_id, type)');
        $this->addSql('CREATE TABLE major_event_participant (id UUID NOT NULL, role VARCHAR(20) NOT NULL, major_event_id UUID NOT NULL, player_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_91B66AAC73328F19 ON major_event_participant (major_event_id)');
        $this->addSql('CREATE INDEX idx_mep_player ON major_event_participant (player_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_mep_event_player ON major_event_participant (major_event_id, player_id)');
        $this->addSql('ALTER TABLE major_event ADD CONSTRAINT FK_75C73A2AE48FD905 FOREIGN KEY (game_id) REFERENCES game (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE major_event ADD CONSTRAINT FK_75C73A2A32D7DE59 FOREIGN KEY (source_event_id) REFERENCES game_event (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE major_event_participant ADD CONSTRAINT FK_91B66AAC73328F19 FOREIGN KEY (major_event_id) REFERENCES major_event (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE major_event_participant ADD CONSTRAINT FK_91B66AAC99E6F5DF FOREIGN KEY (player_id) REFERENCES player (id) NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE major_event DROP CONSTRAINT FK_75C73A2AE48FD905');
        $this->addSql('ALTER TABLE major_event DROP CONSTRAINT FK_75C73A2A32D7DE59');
        $this->addSql('ALTER TABLE major_event_participant DROP CONSTRAINT FK_91B66AAC73328F19');
        $this->addSql('ALTER TABLE major_event_participant DROP CONSTRAINT FK_91B66AAC99E6F5DF');
        $this->addSql('DROP TABLE major_event');
        $this->addSql('DROP TABLE major_event_participant');
    }
}
