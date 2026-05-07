<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260507031500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align job offer warning admin foreign key delete behavior with ORM mapping';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE job_offer_warning DROP FOREIGN KEY fk_warn_admin');
        $this->addSql('ALTER TABLE job_offer_warning ADD CONSTRAINT fk_warn_admin FOREIGN KEY (admin_id) REFERENCES admin (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE job_offer_warning DROP FOREIGN KEY fk_warn_admin');
        $this->addSql('ALTER TABLE job_offer_warning ADD CONSTRAINT fk_warn_admin FOREIGN KEY (admin_id) REFERENCES admin (id) ON DELETE CASCADE ON UPDATE CASCADE');
    }
}
