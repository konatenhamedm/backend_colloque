<?php

namespace App\Travaux\Domain\Enum;

enum StatutCommunication: string
{
    case SOUMISE = 'SOUMISE';
    case A_CORRIGER = 'A_CORRIGER';
    case ACCEPTEE = 'ACCEPTEE';
    case REJETEE = 'REJETEE';
    case VALIDEE = 'VALIDEE'; // version définitive validée par le comité

    public function label(): string
    {
        return match ($this) {
            self::SOUMISE => 'Soumise',
            self::A_CORRIGER => 'À corriger',
            self::ACCEPTEE => 'Acceptée',
            self::REJETEE => 'Rejetée',
            self::VALIDEE => 'Validée',
        };
    }
}
