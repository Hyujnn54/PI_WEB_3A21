<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260507021600 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename application status history changed_by foreign key to changed_by_id';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE application_status_history DROP FOREIGN KEY application_status_history_ibfk_2');
        $this->addSql('DROP INDEX changed_by ON application_status_history');
        $this->addSql('ALTER TABLE application_status_history CHANGE changed_by changed_by_id BIGINT NOT NULL');
        $this->addSql('CREATE INDEX changed_by_id ON application_status_history (changed_by_id)');
        $this->addSql('ALTER TABLE application_status_history ADD CONSTRAINT FK_APPLICATION_STATUS_HISTORY_CHANGED_BY_ID FOREIGN KEY (changed_by_id) REFERENCES users (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE application_status_history DROP FOREIGN KEY FK_APPLICATION_STATUS_HISTORY_CHANGED_BY_ID');
        $this->addSql('DROP INDEX changed_by_id ON application_status_history');
        $this->addSql('ALTER TABLE application_status_history CHANGE changed_by_id changed_by BIGINT NOT NULL');
        $this->addSql('CREATE INDEX changed_by ON application_status_history (changed_by)');
        $this->addSql('ALTER TABLE application_status_history ADD CONSTRAINT application_status_history_ibfk_2 FOREIGN KEY (changed_by) REFERENCES users (id) ON DELETE CASCADE');
    }
}
