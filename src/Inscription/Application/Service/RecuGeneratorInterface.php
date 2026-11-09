<?php

namespace App\Inscription\Application\Service;

use App\Inscription\Domain\Entity\Paiement;

interface RecuGeneratorInterface
{
    /** @return string contenu binaire du PDF du reçu de paiement */
    public function generate(Paiement $paiement): string;
}
