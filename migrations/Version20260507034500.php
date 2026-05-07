<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260507034500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align event review candidate foreign key delete behavior with ORM mapping';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event_review DROP FOREIGN KEY fk_event_review_candidate');
        $this->addSql('ALTER TABLE event_review ADD CONSTRAINT fk_event_review_candidate FOREIGN KEY (candidate_id) REFERENCES candidate (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event_review DROP FOREIGN KEY fk_event_review_candidate');
        $this->addSql('ALTER TABLE event_review ADD CONSTRAINT fk_event_review_candidate FOREIGN KEY (candidate_id) REFERENCES candidate (id) ON DELETE CASCADE');
    }
}
