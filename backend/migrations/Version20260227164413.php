<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260227164413 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE relationship (id UUID NOT NULL, trust INT NOT NULL, affinity INT NOT NULL, respect INT NOT NULL, threat INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, source_id UUID NOT NULL, target_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_200444A0953C1C61 ON relationship (source_id)');
        $this->addSql('CREATE INDEX IDX_200444A0158E0B66 ON relationship (target_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_relationship_source_target ON relationship (source_id, target_id)');
        $this->addSql('ALTER TABLE relationship ADD CONSTRAINT FK_200444A0953C1C61 FOREIGN KEY (source_id) REFERENCES player (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE relationship ADD CONSTRAINT FK_200444A0158E0B66 FOREIGN KEY (target_id) REFERENCES player (id) NOT DEFERRABLE');
        $this->addSql('DROP INDEX idx_game_status');
        $this->addSql('ALTER TABLE game ALTER status DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE relationship DROP CONSTRAINT FK_200444A0953C1C61');
        $this->addSql('ALTER TABLE relationship DROP CONSTRAINT FK_200444A0158E0B66');
        $this->addSql('DROP TABLE relationship');
        $this->addSql('ALTER TABLE game ALTER status SET DEFAULT \'setup\'');
        $this->addSql('CREATE INDEX idx_game_status ON game (status)');
    }
}
