<?php

namespace App\Inscription\Application\Service;

use App\Inscription\Domain\Entity\Inscription;

/** Port de notification : l'implémentation (e-mail) vit dans Infrastructure. */
interface InscriptionNotifierInterface
{
    public function inscriptionRecue(Inscription $inscription): void;
    /** Prévient l'organisateur (si ADMIN_NOTIFICATION_EMAIL est défini) qu'une inscription attend sa vérification. */
    public function nouvelleInscription(Inscription $inscription): void;
    /** @param string $recuPdf reçu de paiement (PDF) joint à l'e-mail : à imprimer et présenter le jour J */
    public function paiementValide(Inscription $inscription, string $recuPdf): void;
    public function paiementRejete(Inscription $inscription, string $motif): void;
    /** @param string $attestationPdf attestation jointe : le participant l'imprime lui-même */
    public function attestationDisponible(Inscription $inscription, string $attestationPdf): void;
}
