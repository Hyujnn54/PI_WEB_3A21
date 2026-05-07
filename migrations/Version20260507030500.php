<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260507030500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align job application candidate foreign key delete behavior with ORM mapping';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE job_application DROP FOREIGN KEY job_application_ibfk_2');
        $this->addSql('ALTER TABLE job_application ADD CONSTRAINT job_application_ibfk_2 FOREIGN KEY (candidate_id) REFERENCES candidate (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE job_application DROP FOREIGN KEY job_application_ibfk_2');
        $this->addSql('ALTER TABLE job_application ADD CONSTRAINT job_application_ibfk_2 FOREIGN KEY (candidate_id) REFERENCES candidate (id) ON DELETE CASCADE');
    }
}
