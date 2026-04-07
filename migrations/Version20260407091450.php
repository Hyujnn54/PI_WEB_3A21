<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260407091450 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE event_review (id BIGINT NOT NULL, rating INT NOT NULL, comment LONGTEXT NOT NULL, created_at DATETIME NOT NULL, event_id BIGINT DEFAULT NULL, candidate_id BIGINT DEFAULT NULL, INDEX IDX_4BDAF69471F7E88B (event_id), INDEX IDX_4BDAF69491BD8781 (candidate_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE job_offer_warning (id BIGINT NOT NULL, reason VARCHAR(255) NOT NULL, message LONGTEXT NOT NULL, status VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, seen_at DATETIME NOT NULL, resolved_at DATETIME NOT NULL, job_offer_id BIGINT DEFAULT NULL, recruiter_id BIGINT DEFAULT NULL, admin_id BIGINT DEFAULT NULL, INDEX IDX_4A9804033481D195 (job_offer_id), INDEX IDX_4A980403156BE243 (recruiter_id), INDEX IDX_4A980403642B8210 (admin_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE warning_correction (id BIGINT NOT NULL, recruiter_id BIGINT NOT NULL, correction_note LONGTEXT NOT NULL, old_title VARCHAR(255) NOT NULL, new_title VARCHAR(255) NOT NULL, old_description LONGTEXT NOT NULL, new_description LONGTEXT NOT NULL, status VARCHAR(255) NOT NULL, submitted_at DATETIME NOT NULL, reviewed_at DATETIME NOT NULL, admin_note LONGTEXT NOT NULL, warning_id BIGINT DEFAULT NULL, job_offer_id BIGINT DEFAULT NULL, INDEX IDX_6C90C5ECBFF38603 (warning_id), INDEX IDX_6C90C5EC3481D195 (job_offer_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE event_review ADD CONSTRAINT FK_4BDAF69471F7E88B FOREIGN KEY (event_id) REFERENCES recruitment_event (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE event_review ADD CONSTRAINT FK_4BDAF69491BD8781 FOREIGN KEY (candidate_id) REFERENCES candidate (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE job_offer_warning ADD CONSTRAINT FK_4A9804033481D195 FOREIGN KEY (job_offer_id) REFERENCES job_offer (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE job_offer_warning ADD CONSTRAINT FK_4A980403156BE243 FOREIGN KEY (recruiter_id) REFERENCES recruiter (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE job_offer_warning ADD CONSTRAINT FK_4A980403642B8210 FOREIGN KEY (admin_id) REFERENCES admin (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE warning_correction ADD CONSTRAINT FK_6C90C5ECBFF38603 FOREIGN KEY (warning_id) REFERENCES job_offer_warning (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE warning_correction ADD CONSTRAINT FK_6C90C5EC3481D195 FOREIGN KEY (job_offer_id) REFERENCES job_offer (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE admin CHANGE assigned_area assigned_area VARCHAR(100) NOT NULL');
        $this->addSql('ALTER TABLE application_status_history DROP FOREIGN KEY `application_status_history_ibfk_2`');
        $this->addSql('ALTER TABLE application_status_history DROP FOREIGN KEY `application_status_history_ibfk_1`');
        $this->addSql('ALTER TABLE application_status_history DROP FOREIGN KEY `application_status_history_ibfk_2`');
        $this->addSql('ALTER TABLE application_status_history CHANGE id id BIGINT NOT NULL, CHANGE application_id application_id BIGINT DEFAULT NULL, CHANGE status status VARCHAR(255) NOT NULL, CHANGE changed_at changed_at DATETIME NOT NULL, CHANGE changed_by changed_by BIGINT DEFAULT NULL, CHANGE note note VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE application_status_history ADD CONSTRAINT FK_48A559FE10BC6D9F FOREIGN KEY (changed_by) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('DROP INDEX application_id ON application_status_history');
        $this->addSql('CREATE INDEX IDX_48A559FE3E030ACD ON application_status_history (application_id)');
        $this->addSql('DROP INDEX changed_by ON application_status_history');
        $this->addSql('CREATE INDEX IDX_48A559FE10BC6D9F ON application_status_history (changed_by)');
        $this->addSql('ALTER TABLE application_status_history ADD CONSTRAINT `application_status_history_ibfk_1` FOREIGN KEY (application_id) REFERENCES job_application (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE application_status_history ADD CONSTRAINT `application_status_history_ibfk_2` FOREIGN KEY (changed_by) REFERENCES users (id)');
        $this->addSql('ALTER TABLE candidate ADD user_id BIGINT NOT NULL, CHANGE location location VARCHAR(255) NOT NULL, CHANGE education_level education_level VARCHAR(100) NOT NULL, CHANGE experience_years experience_years INT NOT NULL, CHANGE cv_path cv_path VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE candidate_skill DROP FOREIGN KEY `candidate_skill_ibfk_1`');
        $this->addSql('ALTER TABLE candidate_skill CHANGE id id BIGINT NOT NULL, CHANGE candidate_id candidate_id BIGINT DEFAULT NULL, CHANGE level level VARCHAR(255) NOT NULL');
        $this->addSql('DROP INDEX candidate_id ON candidate_skill');
        $this->addSql('CREATE INDEX IDX_66DD0F8B91BD8781 ON candidate_skill (candidate_id)');
        $this->addSql('ALTER TABLE candidate_skill ADD CONSTRAINT `candidate_skill_ibfk_1` FOREIGN KEY (candidate_id) REFERENCES candidate (id) ON DELETE CASCADE');
        $this->addSql('DROP INDEX event_id ON event_registration');
        $this->addSql('ALTER TABLE event_registration DROP FOREIGN KEY `event_registration_ibfk_2`');
        $this->addSql('ALTER TABLE event_registration CHANGE id id BIGINT NOT NULL, CHANGE event_id event_id BIGINT DEFAULT NULL, CHANGE candidate_id candidate_id BIGINT DEFAULT NULL, CHANGE registered_at registered_at DATETIME NOT NULL, CHANGE attendance_status attendance_status VARCHAR(255) NOT NULL');
        $this->addSql('DROP INDEX candidate_id ON event_registration');
        $this->addSql('CREATE INDEX IDX_8FBBAD5491BD8781 ON event_registration (candidate_id)');
        $this->addSql('ALTER TABLE event_registration ADD CONSTRAINT `event_registration_ibfk_2` FOREIGN KEY (candidate_id) REFERENCES candidate (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE interview DROP FOREIGN KEY `interview_ibfk_1`');
        $this->addSql('ALTER TABLE interview DROP FOREIGN KEY `interview_ibfk_2`');
        $this->addSql('ALTER TABLE interview ADD reminder_sent TINYINT NOT NULL, CHANGE id id BIGINT NOT NULL, CHANGE application_id application_id BIGINT DEFAULT NULL, CHANGE recruiter_id recruiter_id BIGINT DEFAULT NULL, CHANGE mode mode VARCHAR(255) NOT NULL, CHANGE meeting_link meeting_link VARCHAR(255) NOT NULL, CHANGE location location VARCHAR(255) NOT NULL, CHANGE status status VARCHAR(255) NOT NULL, CHANGE notes notes LONGTEXT NOT NULL, CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('DROP INDEX application_id ON interview');
        $this->addSql('CREATE INDEX IDX_CF1D3C343E030ACD ON interview (application_id)');
        $this->addSql('DROP INDEX recruiter_id ON interview');
        $this->addSql('CREATE INDEX IDX_CF1D3C34156BE243 ON interview (recruiter_id)');
        $this->addSql('ALTER TABLE interview ADD CONSTRAINT `interview_ibfk_1` FOREIGN KEY (application_id) REFERENCES job_application (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE interview ADD CONSTRAINT `interview_ibfk_2` FOREIGN KEY (recruiter_id) REFERENCES recruiter (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE interview_feedback DROP INDEX interview_id, ADD INDEX IDX_BBE187BB55D69D95 (interview_id)');
        $this->addSql('ALTER TABLE interview_feedback DROP FOREIGN KEY `interview_feedback_ibfk_2`');
        $this->addSql('ALTER TABLE interview_feedback CHANGE id id BIGINT NOT NULL, CHANGE interview_id interview_id BIGINT DEFAULT NULL, CHANGE recruiter_id recruiter_id BIGINT DEFAULT NULL, CHANGE overall_score overall_score INT NOT NULL, CHANGE decision decision VARCHAR(255) NOT NULL, CHANGE comment comment LONGTEXT NOT NULL, CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('DROP INDEX recruiter_id ON interview_feedback');
        $this->addSql('CREATE INDEX IDX_BBE187BB156BE243 ON interview_feedback (recruiter_id)');
        $this->addSql('ALTER TABLE interview_feedback ADD CONSTRAINT `interview_feedback_ibfk_2` FOREIGN KEY (recruiter_id) REFERENCES recruiter (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE job_application DROP FOREIGN KEY `job_application_ibfk_1`');
        $this->addSql('ALTER TABLE job_application DROP FOREIGN KEY `job_application_ibfk_2`');
        $this->addSql('ALTER TABLE job_application ADD is_archived TINYINT NOT NULL, CHANGE id id BIGINT NOT NULL, CHANGE offer_id offer_id BIGINT DEFAULT NULL, CHANGE candidate_id candidate_id BIGINT DEFAULT NULL, CHANGE phone phone VARCHAR(30) NOT NULL, CHANGE cover_letter cover_letter LONGTEXT NOT NULL, CHANGE cv_path cv_path VARCHAR(255) NOT NULL, CHANGE applied_at applied_at DATETIME NOT NULL, CHANGE current_status current_status VARCHAR(255) NOT NULL');
        $this->addSql('DROP INDEX offer_id ON job_application');
        $this->addSql('CREATE INDEX IDX_C737C68853C674EE ON job_application (offer_id)');
        $this->addSql('DROP INDEX candidate_id ON job_application');
        $this->addSql('CREATE INDEX IDX_C737C68891BD8781 ON job_application (candidate_id)');
        $this->addSql('ALTER TABLE job_application ADD CONSTRAINT `job_application_ibfk_1` FOREIGN KEY (offer_id) REFERENCES job_offer (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE job_application ADD CONSTRAINT `job_application_ibfk_2` FOREIGN KEY (candidate_id) REFERENCES candidate (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE job_offer DROP FOREIGN KEY `job_offer_ibfk_1`');
        $this->addSql('ALTER TABLE job_offer ADD latitude DOUBLE PRECISION NOT NULL, ADD longitude DOUBLE PRECISION NOT NULL, ADD quality_score INT NOT NULL, ADD ai_suggestions LONGTEXT NOT NULL, ADD is_flagged TINYINT NOT NULL, ADD flagged_at DATETIME NOT NULL, CHANGE id id BIGINT NOT NULL, CHANGE recruiter_id recruiter_id BIGINT DEFAULT NULL, CHANGE description description LONGTEXT NOT NULL, CHANGE location location VARCHAR(255) NOT NULL, CHANGE contract_type contract_type VARCHAR(255) NOT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE deadline deadline DATETIME NOT NULL, CHANGE status status VARCHAR(255) NOT NULL');
        $this->addSql('DROP INDEX recruiter_id ON job_offer');
        $this->addSql('CREATE INDEX IDX_288A3A4E156BE243 ON job_offer (recruiter_id)');
        $this->addSql('ALTER TABLE job_offer ADD CONSTRAINT `job_offer_ibfk_1` FOREIGN KEY (recruiter_id) REFERENCES recruiter (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE offer_skill DROP FOREIGN KEY `offer_skill_ibfk_1`');
        $this->addSql('ALTER TABLE offer_skill CHANGE id id BIGINT NOT NULL, CHANGE offer_id offer_id BIGINT DEFAULT NULL, CHANGE level_required level_required VARCHAR(255) NOT NULL');
        $this->addSql('DROP INDEX offer_id ON offer_skill');
        $this->addSql('CREATE INDEX IDX_DD10999E53C674EE ON offer_skill (offer_id)');
        $this->addSql('ALTER TABLE offer_skill ADD CONSTRAINT `offer_skill_ibfk_1` FOREIGN KEY (offer_id) REFERENCES job_offer (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE recruiter ADD user_id BIGINT NOT NULL, ADD company_description LONGTEXT NOT NULL, CHANGE company_location company_location VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE recruitment_event DROP FOREIGN KEY `recruitment_event_ibfk_1`');
        $this->addSql('ALTER TABLE recruitment_event ADD meet_link VARCHAR(255) NOT NULL, CHANGE id id BIGINT NOT NULL, CHANGE recruiter_id recruiter_id BIGINT DEFAULT NULL, CHANGE description description LONGTEXT NOT NULL, CHANGE event_type event_type VARCHAR(255) NOT NULL, CHANGE location location VARCHAR(255) NOT NULL, CHANGE capacity capacity INT NOT NULL, CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('DROP INDEX recruiter_id ON recruitment_event');
        $this->addSql('CREATE INDEX IDX_D1195597156BE243 ON recruitment_event (recruiter_id)');
        $this->addSql('ALTER TABLE recruitment_event ADD CONSTRAINT `recruitment_event_ibfk_1` FOREIGN KEY (recruiter_id) REFERENCES recruiter (id) ON DELETE CASCADE');
        $this->addSql('DROP INDEX email ON users');
        $this->addSql('ALTER TABLE users CHANGE id id BIGINT NOT NULL, CHANGE first_name first_name VARCHAR(100) NOT NULL, CHANGE last_name last_name VARCHAR(100) NOT NULL, CHANGE phone phone VARCHAR(30) NOT NULL, CHANGE is_active is_active TINYINT NOT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE forget_code forget_code VARCHAR(10) NOT NULL, CHANGE forget_code_expires forget_code_expires DATETIME NOT NULL, CHANGE face_person_id face_person_id VARCHAR(128) NOT NULL, CHANGE face_enabled face_enabled TINYINT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE event_review DROP FOREIGN KEY FK_4BDAF69471F7E88B');
        $this->addSql('ALTER TABLE event_review DROP FOREIGN KEY FK_4BDAF69491BD8781');
        $this->addSql('ALTER TABLE job_offer_warning DROP FOREIGN KEY FK_4A9804033481D195');
        $this->addSql('ALTER TABLE job_offer_warning DROP FOREIGN KEY FK_4A980403156BE243');
        $this->addSql('ALTER TABLE job_offer_warning DROP FOREIGN KEY FK_4A980403642B8210');
        $this->addSql('ALTER TABLE warning_correction DROP FOREIGN KEY FK_6C90C5ECBFF38603');
        $this->addSql('ALTER TABLE warning_correction DROP FOREIGN KEY FK_6C90C5EC3481D195');
        $this->addSql('DROP TABLE event_review');
        $this->addSql('DROP TABLE job_offer_warning');
        $this->addSql('DROP TABLE warning_correction');
        $this->addSql('DROP TABLE messenger_messages');
        $this->addSql('ALTER TABLE admin CHANGE assigned_area assigned_area VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE application_status_history DROP FOREIGN KEY FK_48A559FE10BC6D9F');
        $this->addSql('ALTER TABLE application_status_history DROP FOREIGN KEY FK_48A559FE3E030ACD');
        $this->addSql('ALTER TABLE application_status_history DROP FOREIGN KEY FK_48A559FE10BC6D9F');
        $this->addSql('ALTER TABLE application_status_history CHANGE id id BIGINT AUTO_INCREMENT NOT NULL, CHANGE status status ENUM(\'SUBMITTED\', \'IN_REVIEW\', \'SHORTLISTED\', \'REJECTED\', \'INTERVIEW\', \'HIRED\') NOT NULL, CHANGE changed_at changed_at DATETIME DEFAULT CURRENT_TIMESTAMP, CHANGE note note VARCHAR(255) DEFAULT NULL, CHANGE application_id application_id BIGINT NOT NULL, CHANGE changed_by changed_by BIGINT NOT NULL');
        $this->addSql('ALTER TABLE application_status_history ADD CONSTRAINT `application_status_history_ibfk_2` FOREIGN KEY (changed_by) REFERENCES users (id)');
        $this->addSql('DROP INDEX idx_48a559fe10bc6d9f ON application_status_history');
        $this->addSql('CREATE INDEX changed_by ON application_status_history (changed_by)');
        $this->addSql('DROP INDEX idx_48a559fe3e030acd ON application_status_history');
        $this->addSql('CREATE INDEX application_id ON application_status_history (application_id)');
        $this->addSql('ALTER TABLE application_status_history ADD CONSTRAINT FK_48A559FE3E030ACD FOREIGN KEY (application_id) REFERENCES job_application (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE application_status_history ADD CONSTRAINT FK_48A559FE10BC6D9F FOREIGN KEY (changed_by) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE candidate DROP user_id, CHANGE location location VARCHAR(255) DEFAULT NULL, CHANGE education_level education_level VARCHAR(100) DEFAULT NULL, CHANGE experience_years experience_years INT DEFAULT NULL, CHANGE cv_path cv_path VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE candidate_skill DROP FOREIGN KEY FK_66DD0F8B91BD8781');
        $this->addSql('ALTER TABLE candidate_skill CHANGE id id BIGINT AUTO_INCREMENT NOT NULL, CHANGE level level ENUM(\'BEGINNER\', \'INTERMEDIATE\', \'ADVANCED\') NOT NULL, CHANGE candidate_id candidate_id BIGINT NOT NULL');
        $this->addSql('DROP INDEX idx_66dd0f8b91bd8781 ON candidate_skill');
        $this->addSql('CREATE INDEX candidate_id ON candidate_skill (candidate_id)');
        $this->addSql('ALTER TABLE candidate_skill ADD CONSTRAINT FK_66DD0F8B91BD8781 FOREIGN KEY (candidate_id) REFERENCES candidate (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE event_registration DROP FOREIGN KEY FK_8FBBAD5491BD8781');
        $this->addSql('ALTER TABLE event_registration CHANGE id id BIGINT AUTO_INCREMENT NOT NULL, CHANGE registered_at registered_at DATETIME DEFAULT CURRENT_TIMESTAMP, CHANGE attendance_status attendance_status ENUM(\'REGISTERED\', \'ATTENDED\', \'CANCELLED\', \'NO_SHOW\') DEFAULT \'REGISTERED\', CHANGE event_id event_id BIGINT NOT NULL, CHANGE candidate_id candidate_id BIGINT NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX event_id ON event_registration (event_id, candidate_id)');
        $this->addSql('DROP INDEX idx_8fbbad5491bd8781 ON event_registration');
        $this->addSql('CREATE INDEX candidate_id ON event_registration (candidate_id)');
        $this->addSql('ALTER TABLE event_registration ADD CONSTRAINT FK_8FBBAD5491BD8781 FOREIGN KEY (candidate_id) REFERENCES candidate (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE interview DROP FOREIGN KEY FK_CF1D3C343E030ACD');
        $this->addSql('ALTER TABLE interview DROP FOREIGN KEY FK_CF1D3C34156BE243');
        $this->addSql('ALTER TABLE interview DROP reminder_sent, CHANGE id id BIGINT AUTO_INCREMENT NOT NULL, CHANGE mode mode ENUM(\'ONLINE\', \'ON_SITE\') NOT NULL, CHANGE meeting_link meeting_link VARCHAR(255) DEFAULT NULL, CHANGE location location VARCHAR(255) DEFAULT NULL, CHANGE status status ENUM(\'SCHEDULED\', \'CANCELLED\', \'DONE\') DEFAULT \'SCHEDULED\', CHANGE notes notes TEXT DEFAULT NULL, CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP, CHANGE application_id application_id BIGINT NOT NULL, CHANGE recruiter_id recruiter_id BIGINT NOT NULL');
        $this->addSql('DROP INDEX idx_cf1d3c34156be243 ON interview');
        $this->addSql('CREATE INDEX recruiter_id ON interview (recruiter_id)');
        $this->addSql('DROP INDEX idx_cf1d3c343e030acd ON interview');
        $this->addSql('CREATE INDEX application_id ON interview (application_id)');
        $this->addSql('ALTER TABLE interview ADD CONSTRAINT FK_CF1D3C343E030ACD FOREIGN KEY (application_id) REFERENCES job_application (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE interview ADD CONSTRAINT FK_CF1D3C34156BE243 FOREIGN KEY (recruiter_id) REFERENCES recruiter (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE interview_feedback DROP INDEX IDX_BBE187BB55D69D95, ADD UNIQUE INDEX interview_id (interview_id)');
        $this->addSql('ALTER TABLE interview_feedback DROP FOREIGN KEY FK_BBE187BB156BE243');
        $this->addSql('ALTER TABLE interview_feedback CHANGE id id BIGINT AUTO_INCREMENT NOT NULL, CHANGE overall_score overall_score INT DEFAULT NULL, CHANGE decision decision ENUM(\'ACCEPTED\', \'REJECTED\') NOT NULL, CHANGE comment comment TEXT DEFAULT NULL, CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP, CHANGE interview_id interview_id BIGINT NOT NULL, CHANGE recruiter_id recruiter_id BIGINT NOT NULL');
        $this->addSql('DROP INDEX idx_bbe187bb156be243 ON interview_feedback');
        $this->addSql('CREATE INDEX recruiter_id ON interview_feedback (recruiter_id)');
        $this->addSql('ALTER TABLE interview_feedback ADD CONSTRAINT FK_BBE187BB156BE243 FOREIGN KEY (recruiter_id) REFERENCES recruiter (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE job_application DROP FOREIGN KEY FK_C737C68853C674EE');
        $this->addSql('ALTER TABLE job_application DROP FOREIGN KEY FK_C737C68891BD8781');
        $this->addSql('ALTER TABLE job_application DROP is_archived, CHANGE id id BIGINT AUTO_INCREMENT NOT NULL, CHANGE phone phone VARCHAR(30) DEFAULT NULL, CHANGE cover_letter cover_letter TEXT DEFAULT NULL, CHANGE cv_path cv_path VARCHAR(255) DEFAULT NULL, CHANGE applied_at applied_at DATETIME DEFAULT CURRENT_TIMESTAMP, CHANGE current_status current_status ENUM(\'SUBMITTED\', \'IN_REVIEW\', \'SHORTLISTED\', \'REJECTED\', \'INTERVIEW\', \'HIRED\') DEFAULT \'SUBMITTED\', CHANGE offer_id offer_id BIGINT NOT NULL, CHANGE candidate_id candidate_id BIGINT NOT NULL');
        $this->addSql('DROP INDEX idx_c737c68891bd8781 ON job_application');
        $this->addSql('CREATE INDEX candidate_id ON job_application (candidate_id)');
        $this->addSql('DROP INDEX idx_c737c68853c674ee ON job_application');
        $this->addSql('CREATE INDEX offer_id ON job_application (offer_id)');
        $this->addSql('ALTER TABLE job_application ADD CONSTRAINT FK_C737C68853C674EE FOREIGN KEY (offer_id) REFERENCES job_offer (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE job_application ADD CONSTRAINT FK_C737C68891BD8781 FOREIGN KEY (candidate_id) REFERENCES candidate (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE job_offer DROP FOREIGN KEY FK_288A3A4E156BE243');
        $this->addSql('ALTER TABLE job_offer DROP latitude, DROP longitude, DROP quality_score, DROP ai_suggestions, DROP is_flagged, DROP flagged_at, CHANGE id id BIGINT AUTO_INCREMENT NOT NULL, CHANGE description description TEXT NOT NULL, CHANGE location location VARCHAR(255) DEFAULT NULL, CHANGE contract_type contract_type ENUM(\'CDI\', \'CDD\', \'INTERNSHIP\', \'FREELANCE\', \'PART_TIME\', \'FULL_TIME\') NOT NULL, CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP, CHANGE deadline deadline DATETIME DEFAULT NULL, CHANGE status status ENUM(\'OPEN\', \'CLOSED\') DEFAULT \'OPEN\', CHANGE recruiter_id recruiter_id BIGINT NOT NULL');
        $this->addSql('DROP INDEX idx_288a3a4e156be243 ON job_offer');
        $this->addSql('CREATE INDEX recruiter_id ON job_offer (recruiter_id)');
        $this->addSql('ALTER TABLE job_offer ADD CONSTRAINT FK_288A3A4E156BE243 FOREIGN KEY (recruiter_id) REFERENCES recruiter (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE offer_skill DROP FOREIGN KEY FK_DD10999E53C674EE');
        $this->addSql('ALTER TABLE offer_skill CHANGE id id BIGINT AUTO_INCREMENT NOT NULL, CHANGE level_required level_required ENUM(\'BEGINNER\', \'INTERMEDIATE\', \'ADVANCED\') NOT NULL, CHANGE offer_id offer_id BIGINT NOT NULL');
        $this->addSql('DROP INDEX idx_dd10999e53c674ee ON offer_skill');
        $this->addSql('CREATE INDEX offer_id ON offer_skill (offer_id)');
        $this->addSql('ALTER TABLE offer_skill ADD CONSTRAINT FK_DD10999E53C674EE FOREIGN KEY (offer_id) REFERENCES job_offer (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE recruiter DROP user_id, DROP company_description, CHANGE company_location company_location VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE recruitment_event DROP FOREIGN KEY FK_D1195597156BE243');
        $this->addSql('ALTER TABLE recruitment_event DROP meet_link, CHANGE id id BIGINT AUTO_INCREMENT NOT NULL, CHANGE description description TEXT DEFAULT NULL, CHANGE event_type event_type ENUM(\'JOB_FAIR\', \'WEBINAR\', \'INTERVIEW_DAY\') NOT NULL, CHANGE location location VARCHAR(255) DEFAULT NULL, CHANGE capacity capacity INT DEFAULT 0, CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP, CHANGE recruiter_id recruiter_id BIGINT NOT NULL');
        $this->addSql('DROP INDEX idx_d1195597156be243 ON recruitment_event');
        $this->addSql('CREATE INDEX recruiter_id ON recruitment_event (recruiter_id)');
        $this->addSql('ALTER TABLE recruitment_event ADD CONSTRAINT FK_D1195597156BE243 FOREIGN KEY (recruiter_id) REFERENCES recruiter (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE users CHANGE id id BIGINT AUTO_INCREMENT NOT NULL, CHANGE first_name first_name VARCHAR(100) DEFAULT NULL, CHANGE last_name last_name VARCHAR(100) DEFAULT NULL, CHANGE phone phone VARCHAR(30) DEFAULT NULL, CHANGE is_active is_active TINYINT DEFAULT 1, CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP, CHANGE forget_code forget_code VARCHAR(10) DEFAULT NULL, CHANGE forget_code_expires forget_code_expires DATETIME DEFAULT NULL, CHANGE face_person_id face_person_id VARCHAR(128) DEFAULT NULL, CHANGE face_enabled face_enabled TINYINT DEFAULT 0 NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX email ON users (email)');
    }
}
