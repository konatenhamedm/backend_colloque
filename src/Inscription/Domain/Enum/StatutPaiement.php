<?php

namespace App\Inscription\Domain\Enum;

enum StatutPaiement: string
{
    case EN_ATTENTE = 'EN_ATTENTE';
    case VALIDE = 'VALIDE';
    case REJETE = 'REJETE';
}
