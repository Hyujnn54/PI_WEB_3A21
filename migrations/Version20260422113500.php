<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260422113500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add job offer comment moderation and analyzer storage';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE job_offer_comment (id BIGINT NOT NULL, job_offer_id BIGINT NOT NULL, candidate_id BIGINT NOT NULL, comment_text LONGTEXT NOT NULL, toxicity_score DOUBLE PRECISION NOT NULL, spam_score DOUBLE PRECISION NOT NULL, sentiment VARCHAR(32) NOT NULL, labels LONGTEXT NOT NULL, moderation_status VARCHAR(32) NOT NULL, visibility_status VARCHAR(16) NOT NULL, is_auto_flagged TINYINT(1) NOT NULL, analyzer_source VARCHAR(32) NOT NULL, created_at DATETIME NOT NULL, analyzed_at DATETIME DEFAULT NULL, moderated_at DATETIME DEFAULT NULL, moderator_id BIGINT DEFAULT NULL, moderator_action_note LONGTEXT DEFAULT NULL, INDEX IDX_1830D5A43481D195 (job_offer_id), INDEX IDX_1830D5A491BD8781 (candidate_id), INDEX IDX_1830D5A43C03F15C (moderator_id), INDEX IDX_1830D5A486537A6E (moderation_status), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE job_offer_comment ADD CONSTRAINT FK_1830D5A43481D195 FOREIGN KEY (job_offer_id) REFERENCES job_offer (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE job_offer_comment ADD CONSTRAINT FK_1830D5A491BD8781 FOREIGN KEY (candidate_id) REFERENCES candidate (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE job_offer_comment ADD CONSTRAINT FK_1830D5A43C03F15C FOREIGN KEY (moderator_id) REFERENCES admin (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE job_offer_comment DROP FOREIGN KEY FK_1830D5A43481D195');
        $this->addSql('ALTER TABLE job_offer_comment DROP FOREIGN KEY FK_1830D5A491BD8781');
        $this->addSql('ALTER TABLE job_offer_comment DROP FOREIGN KEY FK_1830D5A43C03F15C');
        $this->addSql('DROP TABLE job_offer_comment');
    }
}
