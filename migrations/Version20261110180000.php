<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Corrections du client (10/11/2026) : slogan du forum. Avantages et logos sont corrigés par app:seed-colloque. */
final class Version20261110180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'colloque.slogan';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE colloque ADD slogan VARCHAR(255) DEFAULT NULL');
        $this->addSql("UPDATE colloque SET slogan = 'La recherche se découvre, se partage et se construit ensemble !' WHERE slogan IS NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE colloque DROP slogan');
    }
}
