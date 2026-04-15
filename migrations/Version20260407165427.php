<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260407165427 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE admin (assigned_area VARCHAR(100) NOT NULL, id BIGINT NOT NULL, PRIMARY KEY (id), CONSTRAINT FK_880E0D76BF396750 FOREIGN KEY (id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE TABLE application_status_history (id BIGINT NOT NULL, status VARCHAR(255) NOT NULL, changed_at DATETIME NOT NULL, note VARCHAR(255) NOT NULL, application_id BIGINT DEFAULT NULL, changed_by BIGINT DEFAULT NULL, PRIMARY KEY (id), CONSTRAINT FK_48A559FE3E030ACD FOREIGN KEY (application_id) REFERENCES job_application (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_48A559FE10BC6D9F FOREIGN KEY (changed_by) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_48A559FE3E030ACD ON application_status_history (application_id)');
        $this->addSql('CREATE INDEX IDX_48A559FE10BC6D9F ON application_status_history (changed_by)');
        $this->addSql('CREATE TABLE candidate (user_id BIGINT NOT NULL, location VARCHAR(255) NOT NULL, education_level VARCHAR(100) NOT NULL, experience_years INTEGER NOT NULL, cv_path VARCHAR(255) NOT NULL, id BIGINT NOT NULL, PRIMARY KEY (id), CONSTRAINT FK_C8B28E44BF396750 FOREIGN KEY (id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE TABLE candidate_skill (id BIGINT NOT NULL, skill_name VARCHAR(100) NOT NULL, level VARCHAR(255) NOT NULL, candidate_id BIGINT DEFAULT NULL, PRIMARY KEY (id), CONSTRAINT FK_66DD0F8B91BD8781 FOREIGN KEY (candidate_id) REFERENCES candidate (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_66DD0F8B91BD8781 ON candidate_skill (candidate_id)');
        $this->addSql('CREATE TABLE event_registration (id BIGINT NOT NULL, registered_at DATETIME NOT NULL, attendance_status VARCHAR(255) NOT NULL, event_id BIGINT DEFAULT NULL, candidate_id BIGINT DEFAULT NULL, PRIMARY KEY (id), CONSTRAINT FK_8FBBAD5471F7E88B FOREIGN KEY (event_id) REFERENCES recruitment_event (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_8FBBAD5491BD8781 FOREIGN KEY (candidate_id) REFERENCES candidate (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_8FBBAD5471F7E88B ON event_registration (event_id)');
        $this->addSql('CREATE INDEX IDX_8FBBAD5491BD8781 ON event_registration (candidate_id)');
        $this->addSql('CREATE TABLE event_review (id BIGINT NOT NULL, rating INTEGER NOT NULL, comment CLOB NOT NULL, created_at DATETIME NOT NULL, event_id BIGINT DEFAULT NULL, candidate_id BIGINT DEFAULT NULL, PRIMARY KEY (id), CONSTRAINT FK_4BDAF69471F7E88B FOREIGN KEY (event_id) REFERENCES recruitment_event (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_4BDAF69491BD8781 FOREIGN KEY (candidate_id) REFERENCES candidate (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_4BDAF69471F7E88B ON event_review (event_id)');
        $this->addSql('CREATE INDEX IDX_4BDAF69491BD8781 ON event_review (candidate_id)');
        $this->addSql('CREATE TABLE interview (id BIGINT NOT NULL, scheduled_at DATETIME NOT NULL, duration_minutes INTEGER NOT NULL, mode VARCHAR(255) NOT NULL, meeting_link VARCHAR(255) NOT NULL, location VARCHAR(255) NOT NULL, status VARCHAR(255) NOT NULL, notes CLOB NOT NULL, created_at DATETIME NOT NULL, reminder_sent BOOLEAN NOT NULL, application_id BIGINT DEFAULT NULL, recruiter_id BIGINT DEFAULT NULL, PRIMARY KEY (id), CONSTRAINT FK_CF1D3C343E030ACD FOREIGN KEY (application_id) REFERENCES job_application (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_CF1D3C34156BE243 FOREIGN KEY (recruiter_id) REFERENCES recruiter (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_CF1D3C343E030ACD ON interview (application_id)');
        $this->addSql('CREATE INDEX IDX_CF1D3C34156BE243 ON interview (recruiter_id)');
        $this->addSql('CREATE TABLE interview_feedback (id BIGINT NOT NULL, overall_score INTEGER NOT NULL, decision VARCHAR(255) NOT NULL, comment CLOB NOT NULL, created_at DATETIME NOT NULL, interview_id BIGINT DEFAULT NULL, recruiter_id BIGINT DEFAULT NULL, PRIMARY KEY (id), CONSTRAINT FK_BBE187BB55D69D95 FOREIGN KEY (interview_id) REFERENCES interview (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_BBE187BB156BE243 FOREIGN KEY (recruiter_id) REFERENCES recruiter (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_BBE187BB55D69D95 ON interview_feedback (interview_id)');
        $this->addSql('CREATE INDEX IDX_BBE187BB156BE243 ON interview_feedback (recruiter_id)');
        $this->addSql('CREATE TABLE job_application (id BIGINT NOT NULL, phone VARCHAR(30) NOT NULL, cover_letter CLOB NOT NULL, cv_path VARCHAR(255) NOT NULL, applied_at DATETIME NOT NULL, current_status VARCHAR(255) NOT NULL, is_archived BOOLEAN NOT NULL, offer_id BIGINT DEFAULT NULL, candidate_id BIGINT DEFAULT NULL, PRIMARY KEY (id), CONSTRAINT FK_C737C68853C674EE FOREIGN KEY (offer_id) REFERENCES job_offer (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_C737C68891BD8781 FOREIGN KEY (candidate_id) REFERENCES candidate (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_C737C68853C674EE ON job_application (offer_id)');
        $this->addSql('CREATE INDEX IDX_C737C68891BD8781 ON job_application (candidate_id)');
        $this->addSql('CREATE TABLE job_offer (id BIGINT NOT NULL, title VARCHAR(255) NOT NULL, description CLOB NOT NULL, location VARCHAR(255) NOT NULL, latitude DOUBLE PRECISION NOT NULL, longitude DOUBLE PRECISION NOT NULL, contract_type VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, deadline DATETIME NOT NULL, status VARCHAR(255) NOT NULL, quality_score INTEGER NOT NULL, ai_suggestions CLOB NOT NULL, is_flagged BOOLEAN NOT NULL, flagged_at DATETIME NOT NULL, recruiter_id BIGINT DEFAULT NULL, PRIMARY KEY (id), CONSTRAINT FK_288A3A4E156BE243 FOREIGN KEY (recruiter_id) REFERENCES recruiter (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_288A3A4E156BE243 ON job_offer (recruiter_id)');
        $this->addSql('CREATE TABLE job_offer_warning (id BIGINT NOT NULL, reason VARCHAR(255) NOT NULL, message CLOB NOT NULL, status VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, seen_at DATETIME NOT NULL, resolved_at DATETIME NOT NULL, job_offer_id BIGINT DEFAULT NULL, recruiter_id BIGINT DEFAULT NULL, admin_id BIGINT DEFAULT NULL, PRIMARY KEY (id), CONSTRAINT FK_4A9804033481D195 FOREIGN KEY (job_offer_id) REFERENCES job_offer (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_4A980403156BE243 FOREIGN KEY (recruiter_id) REFERENCES recruiter (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_4A980403642B8210 FOREIGN KEY (admin_id) REFERENCES admin (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_4A9804033481D195 ON job_offer_warning (job_offer_id)');
        $this->addSql('CREATE INDEX IDX_4A980403156BE243 ON job_offer_warning (recruiter_id)');
        $this->addSql('CREATE INDEX IDX_4A980403642B8210 ON job_offer_warning (admin_id)');
        $this->addSql('CREATE TABLE offer_skill (id BIGINT NOT NULL, skill_name VARCHAR(100) NOT NULL, level_required VARCHAR(255) NOT NULL, offer_id BIGINT DEFAULT NULL, PRIMARY KEY (id), CONSTRAINT FK_DD10999E53C674EE FOREIGN KEY (offer_id) REFERENCES job_offer (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_DD10999E53C674EE ON offer_skill (offer_id)');
        $this->addSql('CREATE TABLE recruiter (user_id BIGINT NOT NULL, company_name VARCHAR(255) NOT NULL, company_location VARCHAR(255) NOT NULL, company_description CLOB NOT NULL, id BIGINT NOT NULL, PRIMARY KEY (id), CONSTRAINT FK_DE8633D8BF396750 FOREIGN KEY (id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE TABLE recruitment_event (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, title VARCHAR(255) NOT NULL, description CLOB NOT NULL, event_type VARCHAR(255) NOT NULL, location VARCHAR(255) NOT NULL, event_date DATETIME NOT NULL, capacity INTEGER NOT NULL, meet_link VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, recruiter_id BIGINT DEFAULT NULL, CONSTRAINT FK_D1195597156BE243 FOREIGN KEY (recruiter_id) REFERENCES recruiter (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_D1195597156BE243 ON recruitment_event (recruiter_id)');
        $this->addSql('CREATE TABLE users (id BIGINT NOT NULL, email VARCHAR(255) NOT NULL, password VARCHAR(255) NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, phone VARCHAR(30) NOT NULL, is_active BOOLEAN NOT NULL, created_at DATETIME NOT NULL, forget_code VARCHAR(10) NOT NULL, forget_code_expires DATETIME NOT NULL, face_person_id VARCHAR(128) NOT NULL, face_enabled BOOLEAN NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE warning_correction (id BIGINT NOT NULL, recruiter_id BIGINT NOT NULL, correction_note CLOB NOT NULL, old_title VARCHAR(255) NOT NULL, new_title VARCHAR(255) NOT NULL, old_description CLOB NOT NULL, new_description CLOB NOT NULL, status VARCHAR(255) NOT NULL, submitted_at DATETIME NOT NULL, reviewed_at DATETIME NOT NULL, admin_note CLOB NOT NULL, warning_id BIGINT DEFAULT NULL, job_offer_id BIGINT DEFAULT NULL, PRIMARY KEY (id), CONSTRAINT FK_6C90C5ECBFF38603 FOREIGN KEY (warning_id) REFERENCES job_offer_warning (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6C90C5EC3481D195 FOREIGN KEY (job_offer_id) REFERENCES job_offer (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_6C90C5ECBFF38603 ON warning_correction (warning_id)');
        $this->addSql('CREATE INDEX IDX_6C90C5EC3481D195 ON warning_correction (job_offer_id)');
        $this->addSql('CREATE TABLE messenger_messages (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, body CLOB NOT NULL, headers CLOB NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL)');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages (queue_name, available_at, delivered_at, id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE admin');
        $this->addSql('DROP TABLE application_status_history');
        $this->addSql('DROP TABLE candidate');
        $this->addSql('DROP TABLE candidate_skill');
        $this->addSql('DROP TABLE event_registration');
        $this->addSql('DROP TABLE event_review');
        $this->addSql('DROP TABLE interview');
        $this->addSql('DROP TABLE interview_feedback');
        $this->addSql('DROP TABLE job_application');
        $this->addSql('DROP TABLE job_offer');
        $this->addSql('DROP TABLE job_offer_warning');
        $this->addSql('DROP TABLE offer_skill');
        $this->addSql('DROP TABLE recruiter');
        $this->addSql('DROP TABLE recruitment_event');
        $this->addSql('DROP TABLE users');
        $this->addSql('DROP TABLE warning_correction');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
