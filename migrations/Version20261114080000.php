<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Finances : numéro de dépôt (Orange Money / Wave) du paiement. */
final class Version20261114080000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'inscription_paiement.numero_depot';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE inscription_paiement ADD numero_depot VARCHAR(30) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE inscription_paiement DROP numero_depot');
    }
}
