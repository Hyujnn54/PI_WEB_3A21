<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260507032500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align candidate skill candidate foreign key delete behavior with ORM mapping';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE candidate_skill DROP FOREIGN KEY candidate_skill_ibfk_1');
        $this->addSql('ALTER TABLE candidate_skill ADD CONSTRAINT candidate_skill_ibfk_1 FOREIGN KEY (candidate_id) REFERENCES candidate (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE candidate_skill DROP FOREIGN KEY candidate_skill_ibfk_1');
        $this->addSql('ALTER TABLE candidate_skill ADD CONSTRAINT candidate_skill_ibfk_1 FOREIGN KEY (candidate_id) REFERENCES candidate (id) ON DELETE CASCADE');
    }
}
