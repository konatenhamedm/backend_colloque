<?php

namespace App\Attestation\Application\UseCase;

use App\Attestation\Domain\Repository\AttestationRepositoryInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Route publique (QR code). N'expose que le strict nécessaire : jamais d'e-mail ni de téléphone. */
class VerifierAttestationUseCase
{
    public function __construct(private readonly AttestationRepositoryInterface $attestations)
    {
    }

    /** @return array<string,mixed> */
    public function execute(string $code): array
    {
        $a = $this->attestations->findByCode($code) ?? throw new NotFoundHttpException('Aucune attestation ne correspond à ce code.');
        $i = $a->getInscription();

        return [
            'valide' => true,
            'numero' => $a->getNumero(),
            'participant' => $i->getUtilisateur()->getNomComplet(),
            'institution' => $i->getInstitution(),
            'qualite' => $i->getTypeParticipation()->label(),
            'colloque' => $i->getColloque()->getTitre(),
            'lieu' => $i->getColloque()->getLieu(),
            'dateDebut' => $i->getColloque()->getDateDebut()->format('Y-m-d'),
            'dateFin' => $i->getColloque()->getDateFin()->format('Y-m-d'),
            'emiseLe' => $a->getEmiseAt()->format('Y-m-d'),
        ];
    }
}
