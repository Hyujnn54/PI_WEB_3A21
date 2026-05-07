<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260507040500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align application status history application foreign key delete behavior with ORM mapping';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE application_status_history DROP FOREIGN KEY application_status_history_ibfk_1');
        $this->addSql('ALTER TABLE application_status_history ADD CONSTRAINT application_status_history_ibfk_1 FOREIGN KEY (application_id) REFERENCES job_application (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE application_status_history DROP FOREIGN KEY application_status_history_ibfk_1');
        $this->addSql('ALTER TABLE application_status_history ADD CONSTRAINT application_status_history_ibfk_1 FOREIGN KEY (application_id) REFERENCES job_application (id) ON DELETE CASCADE');
    }
}
