<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260226214409 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Redesign Game (isSandbox → status) and Player (isUserControlled → user relation)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE game ADD status VARCHAR(20) NOT NULL DEFAULT 'setup'");
        $this->addSql('ALTER TABLE game DROP is_sandbox');
        $this->addSql('CREATE INDEX idx_game_status ON game (status)');
        $this->addSql('ALTER TABLE player ADD user_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE player DROP is_user_controlled');
        $this->addSql('ALTER TABLE player ADD CONSTRAINT FK_98197A65A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_98197A65A76ED395 ON player (user_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE game ADD is_sandbox BOOLEAN NOT NULL');
        $this->addSql('ALTER TABLE game DROP status');
        $this->addSql('ALTER TABLE player DROP CONSTRAINT FK_98197A65A76ED395');
        $this->addSql('DROP INDEX IDX_98197A65A76ED395');
        $this->addSql('ALTER TABLE player ADD is_user_controlled BOOLEAN NOT NULL');
        $this->addSql('ALTER TABLE player DROP user_id');
    }
}
