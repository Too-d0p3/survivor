<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260213215341 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rewrite AiLog entity with new fields for status tracking and token usage';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ai_log ADD temperature DOUBLE PRECISION NOT NULL');
        $this->addSql('ALTER TABLE ai_log ADD status VARCHAR(20) NOT NULL');
        $this->addSql('ALTER TABLE ai_log ADD prompt_token_count INT DEFAULT NULL');
        $this->addSql('ALTER TABLE ai_log ADD candidates_token_count INT DEFAULT NULL');
        $this->addSql('ALTER TABLE ai_log ADD total_token_count INT DEFAULT NULL');
        $this->addSql('ALTER TABLE ai_log ADD duration_ms INT DEFAULT NULL');
        $this->addSql('ALTER TABLE ai_log ADD model_version VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE ai_log ADD finish_reason VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE ai_log ADD error_message TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE ai_log DROP api_url');
        $this->addSql('ALTER TABLE ai_log DROP duration');
        $this->addSql('ALTER TABLE ai_log ALTER model_name TYPE VARCHAR(100)');
        $this->addSql('ALTER TABLE ai_log ALTER action_name SET NOT NULL');
        $this->addSql('ALTER TABLE ai_log ALTER user_prompt SET NOT NULL');
        $this->addSql('ALTER TABLE ai_log ALTER system_prompt SET NOT NULL');
        $this->addSql('ALTER TABLE ai_log ALTER request_json SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ai_log ADD api_url VARCHAR(512) DEFAULT NULL');
        $this->addSql('ALTER TABLE ai_log ADD duration INT DEFAULT NULL');
        $this->addSql('ALTER TABLE ai_log DROP temperature');
        $this->addSql('ALTER TABLE ai_log DROP status');
        $this->addSql('ALTER TABLE ai_log DROP prompt_token_count');
        $this->addSql('ALTER TABLE ai_log DROP candidates_token_count');
        $this->addSql('ALTER TABLE ai_log DROP total_token_count');
        $this->addSql('ALTER TABLE ai_log DROP duration_ms');
        $this->addSql('ALTER TABLE ai_log DROP model_version');
        $this->addSql('ALTER TABLE ai_log DROP finish_reason');
        $this->addSql('ALTER TABLE ai_log DROP error_message');
        $this->addSql('ALTER TABLE ai_log ALTER model_name TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE ai_log ALTER action_name DROP NOT NULL');
        $this->addSql('ALTER TABLE ai_log ALTER system_prompt DROP NOT NULL');
        $this->addSql('ALTER TABLE ai_log ALTER user_prompt DROP NOT NULL');
        $this->addSql('ALTER TABLE ai_log ALTER request_json DROP NOT NULL');
    }
}
