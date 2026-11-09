<?php

namespace App\Inscription\Domain\Enum;

enum TypeParticipation: string
{
    case AUDITEUR = 'AUDITEUR';
    case COMMUNICANT = 'COMMUNICANT';

    public function label(): string
    {
        return $this === self::AUDITEUR ? 'Auditeur' : 'Communicant';
    }
}
