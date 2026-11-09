<?php

namespace App\Inscription\Domain\Enum;

/**
 * Machine à états du parcours participant.
 *
 *  PENDING_PAYMENT ──valider──► PAYMENT_VALIDATED ──présence+travaux──► ATTENDED_AND_VALIDATED ──émettre──► CERTIFICATE_ISSUED
 *        │  ▲
 *     rejeter  corriger
 *        ▼  │
 *      REJECTED
 */
enum StatutInscription: string
{
    case PENDING_PAYMENT = 'PENDING_PAYMENT';
    case PAYMENT_VALIDATED = 'PAYMENT_VALIDATED';
    case ATTENDED_AND_VALIDATED = 'ATTENDED_AND_VALIDATED';
    case CERTIFICATE_ISSUED = 'CERTIFICATE_ISSUED';
    case REJECTED = 'REJECTED';

    public function label(): string
    {
        return match ($this) {
            self::PENDING_PAYMENT => 'En attente de validation',
            self::PAYMENT_VALIDATED => 'Inscription validée',
            self::ATTENDED_AND_VALIDATED => 'Participation & travaux validés',
            self::CERTIFICATE_ISSUED => 'Diplôme disponible',
            self::REJECTED => 'Inscription rejetée',
        };
    }

    /** @return self[] */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::PENDING_PAYMENT => [self::PAYMENT_VALIDATED, self::REJECTED],
            self::REJECTED => [self::PENDING_PAYMENT],
            self::PAYMENT_VALIDATED => [self::ATTENDED_AND_VALIDATED],
            self::ATTENDED_AND_VALIDATED => [self::CERTIFICATE_ISSUED, self::PAYMENT_VALIDATED],
            self::CERTIFICATE_ISSUED => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /** Le compte donne accès aux fonctionnalités métier (programme, documents, travaux). */
    public function isActiveAccount(): bool
    {
        return in_array($this, [self::PAYMENT_VALIDATED, self::ATTENDED_AND_VALIDATED, self::CERTIFICATE_ISSUED], true);
    }
}
