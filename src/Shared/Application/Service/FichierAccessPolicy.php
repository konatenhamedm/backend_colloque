<?php

namespace App\Shared\Application\Service;

use App\Attestation\Domain\Entity\Attestation;
use App\Inscription\Domain\Entity\Paiement;
use App\Security\Domain\Entity\Utilisateur;
use App\Shared\Domain\Entity\Fichier;
use App\Travaux\Domain\Entity\Communication;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Qui a le droit de lire un fichier privé ?
 *  - le comité / les admins : tout (vérification des preuves, évaluation des travaux) ;
 *  - un participant : uniquement les fichiers rattachés à SA propre inscription.
 */
class FichierAccessPolicy
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function canRead(Utilisateur $user, Fichier $fichier): bool
    {
        if (!$fichier->isPrive() || $user->isStaff()) {
            return true;
        }
        foreach ([
            [Paiement::class, 'justificatif'],
            [Communication::class, 'fichier'],
            [Attestation::class, 'fichier'],
        ] as [$class, $field]) {
            $count = (int) $this->em->createQuery("SELECT COUNT(e.id) FROM $class e JOIN e.inscription i WHERE e.$field = :f AND i.utilisateur = :u")
                ->setParameters(['f' => $fichier, 'u' => $user])->getSingleScalarResult();
            if ($count > 0) {
                return true;
            }
        }
        return false;
    }
}
