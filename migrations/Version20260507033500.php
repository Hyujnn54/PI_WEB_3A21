<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260507033500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align event registration candidate foreign key delete behavior with ORM mapping';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event_registration DROP FOREIGN KEY event_registration_ibfk_2');
        $this->addSql('ALTER TABLE event_registration ADD CONSTRAINT event_registration_ibfk_2 FOREIGN KEY (candidate_id) REFERENCES candidate (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event_registration DROP FOREIGN KEY event_registration_ibfk_2');
        $this->addSql('ALTER TABLE event_registration ADD CONSTRAINT event_registration_ibfk_2 FOREIGN KEY (candidate_id) REFERENCES candidate (id) ON DELETE CASCADE');
    }
}
