<?php

namespace App\Attestation\Application\UseCase;

use App\Attestation\Domain\Entity\Attestation;
use App\Attestation\Domain\Repository\AttestationRepositoryInterface;
use App\Inscription\Domain\Enum\StatutInscription;
use App\Inscription\Domain\Repository\InscriptionRepositoryInterface;
use App\Security\Domain\Entity\Utilisateur;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class GetMonAttestationUseCase
{
    public function __construct(
        private readonly InscriptionRepositoryInterface $inscriptions,
        private readonly AttestationRepositoryInterface $attestations,
    ) {
    }

    public function execute(Utilisateur $user): Attestation
    {
        $inscription = $this->inscriptions->findByUtilisateur($user);
        if ($inscription === null || $inscription->getStatut() !== StatutInscription::CERTIFICATE_ISSUED) {
            throw new NotFoundHttpException('Votre attestation n\'est pas encore disponible.');
        }

        return $this->attestations->findByInscription($inscription) ?? throw new NotFoundHttpException('Attestation introuvable.');
    }
}
