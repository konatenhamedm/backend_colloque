<?php

namespace App\Inscription\Application\UseCase;

use App\Attestation\Domain\Repository\AttestationRepositoryInterface;
use App\Inscription\Domain\Repository\InscriptionRepositoryInterface;
use App\Shared\Application\Service\FileStorage;
use App\Travaux\Domain\Repository\CommunicationRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Supprime définitivement un inscrit : inscription, paiements, communications, attestation, fichiers
 * (justificatifs, PDF) et son compte participant — l'adresse e-mail redevient libre pour une nouvelle inscription.
 * Un compte du staff (admin / comité) n'est jamais supprimé, seule son inscription l'est.
 */
class SupprimerInscriptionUseCase
{
    public function __construct(
        private readonly InscriptionRepositoryInterface $inscriptions,
        private readonly AttestationRepositoryInterface $attestations,
        private readonly CommunicationRepositoryInterface $communications,
        private readonly FileStorage $storage,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /** @return string e-mail du participant supprimé */
    public function execute(int $id): string
    {
        $inscription = $this->inscriptions->findById($id) ?? throw new NotFoundHttpException('Inscription introuvable.');
        $user = $inscription->getUtilisateur();
        $email = $user->getEmail();
        $fichiers = [];

        $this->em->wrapInTransaction(function () use ($inscription, $user, &$fichiers) {
            foreach ($this->communications->findByInscription($inscription) as $c) {
                $this->em->remove($c);
            }
            if (($att = $this->attestations->findByInscription($inscription)) !== null) {
                $fichiers[] = $att->getFichier();
                $this->em->remove($att);
            }
            foreach ($inscription->getPaiements() as $p) {
                $fichiers[] = $p->getJustificatif();
                $this->em->remove($p);
            }
            $this->em->flush();
            $this->em->remove($inscription);
            $this->em->flush();
            if (!$user->isStaff()) {
                $this->em->remove($user);
                $this->em->flush();
            }
        });

        // Fichiers physiques supprimés après la transaction (un échec disque ne doit pas annuler la suppression en base).
        foreach (array_filter($fichiers) as $f) {
            try {
                $this->storage->remove($f);
            } catch (\Throwable) {
            }
        }
        return $email;
    }
}
