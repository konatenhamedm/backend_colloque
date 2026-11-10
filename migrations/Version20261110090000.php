<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Conserve le lien d'inscription en clair pendant sa validité (1 h) pour le retrouver au back-office. */
final class Version20261110090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'inscription_token : plain_token + created_at';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE inscription_token ADD plain_token VARCHAR(64) DEFAULT NULL');
        // Lignes existantes en production : date de création inconnue → on prend « maintenant », puis la valeur par défaut est retirée.
        $this->addSql('ALTER TABLE inscription_token ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NOW() NOT NULL');
        $this->addSql('ALTER TABLE inscription_token ALTER created_at DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE inscription_token DROP plain_token');
        $this->addSql('ALTER TABLE inscription_token DROP created_at');
    }
}
