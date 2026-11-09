<?php

namespace App\Inscription\Application\UseCase;

use App\Inscription\Application\Service\InscriptionNotifierInterface;
use App\Inscription\Domain\Entity\Paiement;
use App\Inscription\Domain\Repository\PaiementRepositoryInterface;
use App\Security\Domain\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RejeterPaiementUseCase
{
    public function __construct(
        private readonly PaiementRepositoryInterface $paiements,
        private readonly InscriptionNotifierInterface $notifier,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function execute(int $paiementId, string $motif, Utilisateur $admin): Paiement
    {
        $paiement = $this->paiements->findById($paiementId) ?? throw new NotFoundHttpException('Paiement introuvable.');
        $inscription = $paiement->getInscription();
        $inscription->rejeterPaiement($paiement, $motif, $admin->getUserIdentifier());
        $this->em->flush();
        $this->notifier->paiementRejete($inscription, $motif);

        return $paiement;
    }
}
