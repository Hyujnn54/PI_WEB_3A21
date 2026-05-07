<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260507042500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align interview recruiter foreign key delete behavior with ORM mapping';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE interview DROP FOREIGN KEY interview_ibfk_2');
        $this->addSql('ALTER TABLE interview ADD CONSTRAINT interview_ibfk_2 FOREIGN KEY (recruiter_id) REFERENCES recruiter (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE interview DROP FOREIGN KEY interview_ibfk_2');
        $this->addSql('ALTER TABLE interview ADD CONSTRAINT interview_ibfk_2 FOREIGN KEY (recruiter_id) REFERENCES recruiter (id) ON DELETE CASCADE');
    }
}
