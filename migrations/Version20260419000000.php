<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260419000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Google Authenticator fields for two-factor authentication.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD google_authenticator_secret VARCHAR(255) DEFAULT NULL, ADD google_authenticator_enabled TINYINT(1) NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP google_authenticator_secret, DROP google_authenticator_enabled');
    }
}