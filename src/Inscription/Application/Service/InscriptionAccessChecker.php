<?php

namespace App\Inscription\Application\Service;

use App\Inscription\Domain\Entity\Inscription;
use App\Inscription\Domain\Repository\InscriptionRepositoryInterface;
use App\Security\Domain\Entity\Utilisateur;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Compte « restreint » : tant que le paiement n'est pas validé, aucune fonctionnalité métier
 * (programme, documents, travaux). Les staffs ne sont pas concernés.
 */
class InscriptionAccessChecker
{
    public function __construct(private readonly InscriptionRepositoryInterface $inscriptions)
    {
    }

    public function requireActive(Utilisateur $user): Inscription
    {
        $inscription = $this->inscriptions->findByUtilisateur($user)
            ?? throw new AccessDeniedHttpException('Aucune inscription associée à ce compte.');
        if (!$inscription->getStatut()->isActiveAccount()) {
            throw new AccessDeniedHttpException('Votre inscription est en attente de validation : cette fonctionnalité sera accessible dès la validation de votre paiement.');
        }
        return $inscription;
    }
}
