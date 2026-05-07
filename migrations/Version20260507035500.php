<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260507035500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align interview feedback interview foreign key delete behavior with ORM mapping';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE interview_feedback DROP FOREIGN KEY interview_feedback_ibfk_1');
        $this->addSql('ALTER TABLE interview_feedback ADD CONSTRAINT interview_feedback_ibfk_1 FOREIGN KEY (interview_id) REFERENCES interview (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE interview_feedback DROP FOREIGN KEY interview_feedback_ibfk_1');
        $this->addSql('ALTER TABLE interview_feedback ADD CONSTRAINT interview_feedback_ibfk_1 FOREIGN KEY (interview_id) REFERENCES interview (id) ON DELETE CASCADE');
    }
}
