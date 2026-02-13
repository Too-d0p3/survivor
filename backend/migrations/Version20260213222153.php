<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260213222153 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE INDEX idx_ai_log_action_name ON ai_log (action_name)');
        $this->addSql('CREATE INDEX idx_ai_log_status ON ai_log (status)');
        $this->addSql('CREATE INDEX idx_ai_log_created_at ON ai_log (created_at)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX idx_ai_log_action_name');
        $this->addSql('DROP INDEX idx_ai_log_status');
        $this->addSql('DROP INDEX idx_ai_log_created_at');
    }
}
