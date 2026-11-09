<?php

namespace App\Inscription\Application\UseCase;

use App\Inscription\Domain\Entity\Paiement;
use App\Inscription\Domain\Enum\CanalPaiement;
use App\Inscription\Domain\Enum\StatutInscription;
use App\Inscription\Domain\Repository\InscriptionRepositoryInterface;
use App\Inscription\Domain\Repository\PaiementRepositoryInterface;
use App\Security\Domain\Entity\Utilisateur;
use App\Shared\Application\Service\FileStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Après un rejet, le participant renvoie une nouvelle preuve : REJECTED → PENDING_PAYMENT. */
class CorrigerPaiementUseCase
{
    public function __construct(
        private readonly InscriptionRepositoryInterface $inscriptions,
        private readonly PaiementRepositoryInterface $paiements,
        private readonly FileStorage $storage,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function execute(Utilisateur $user, array $data, ?UploadedFile $justificatif): Paiement
    {
        $inscription = $this->inscriptions->findByUtilisateur($user) ?? throw new NotFoundHttpException('Inscription introuvable.');
        if ($inscription->getStatut() !== StatutInscription::REJECTED) {
            throw new BadRequestHttpException('Seule une inscription rejetée peut être corrigée.');
        }
        $canal = CanalPaiement::tryFrom((string) ($data['canal'] ?? '')) ?? throw new BadRequestHttpException('Canal de paiement inconnu.');
        $reference = trim((string) ($data['reference'] ?? ''));
        if ($reference === '' && $justificatif === null) {
            throw new BadRequestHttpException('Indiquez la référence de la transaction ou joignez le reçu de paiement.');
        }
        if ($reference !== '' && $this->paiements->existsReference($canal, $reference)) {
            throw new ConflictHttpException('Cette référence de transaction a déjà été utilisée.');
        }

        $paiement = (new Paiement())->setCanal($canal)->setReference($reference)
            ->setMontant($inscription->getCategorie()->getMontant())
            ->setJustificatif($justificatif !== null ? $this->storage->storeJustificatif($justificatif) : null);
        $inscription->soumettreNouveauPaiement($paiement);
        $this->em->flush();

        return $paiement;
    }
}
