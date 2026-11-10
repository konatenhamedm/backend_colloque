<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Repas facultatif : prix sur le colloque, choix sur l'inscription. */
final class Version20261110140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Option repas (colloque.prix_repas, inscription.repas)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE colloque ADD prix_repas INT DEFAULT NULL');
        $this->addSql('ALTER TABLE inscription ADD repas BOOLEAN DEFAULT false NOT NULL');
        // Information du client : 2 000 FCFA pour le repas facultatif du FJCG déjà en base.
        // Le repas n'est plus inclus : les frais (10 000 FCFA) donnent droit à l'attestation de participation. (Lignes encore au contenu du seed uniquement.)
        $this->addSql("UPDATE colloque SET prix_repas = 2000, avantages = '[\"Attestation de participation\"]' WHERE seed_key = 'fjcg-2026-v1'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE colloque DROP prix_repas');
        $this->addSql('ALTER TABLE inscription DROP repas');
    }
}
