<?php

namespace App\Inscription\Application\UseCase;

use App\Inscription\Application\Service\InscriptionNotifierInterface;
use App\Inscription\Application\Service\RecuGeneratorInterface;
use App\Inscription\Domain\Entity\Paiement;
use App\Inscription\Domain\Repository\PaiementRepositoryInterface;
use App\Security\Domain\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ValiderPaiementUseCase
{
    public function __construct(
        private readonly PaiementRepositoryInterface $paiements,
        private readonly InscriptionNotifierInterface $notifier,
        private readonly RecuGeneratorInterface $recu,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function execute(int $paiementId, Utilisateur $admin): Paiement
    {
        $paiement = $this->paiements->findById($paiementId) ?? throw new NotFoundHttpException('Paiement introuvable.');
        $inscription = $paiement->getInscription();
        $inscription->validerPaiement($paiement, $admin->getUserIdentifier());
        $this->em->flush();
        $this->notifier->paiementValide($inscription, $this->recu->generate($paiement));

        return $paiement;
    }
}
