<?php

namespace App\Attestation\Application\UseCase;

use App\Attestation\Application\Service\AttestationGeneratorInterface;
use App\Attestation\Domain\Entity\Attestation;
use App\Colloque\Domain\Entity\CategorieParticipant;
use App\Colloque\Domain\Repository\ColloqueRepositoryInterface;
use App\Inscription\Domain\Entity\Inscription;
use App\Inscription\Domain\Repository\InscriptionRepositoryInterface;
use App\Security\Domain\Entity\Utilisateur;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Génère un PDF d'aperçu SANS rien enregistrer : ni attestation, ni fichier, ni changement de statut. */
class ApercuAttestationUseCase
{
    public function __construct(
        private readonly AttestationGeneratorInterface $generator,
        private readonly InscriptionRepositoryInterface $inscriptions,
        private readonly ColloqueRepositoryInterface $colloques,
        private readonly string $frontUrl,
    ) {
    }

    /** @param int|null $inscriptionId null = exemple fictif (aperçu du modèle) */
    public function execute(?int $inscriptionId, ?string $template): string
    {
        if ($inscriptionId !== null) {
            $inscription = $this->inscriptions->findById($inscriptionId) ?? throw new NotFoundHttpException('Inscription introuvable.');
        } else {
            $colloque = $this->colloques->findCurrent() ?? throw new NotFoundHttpException('Aucun colloque actif.');
            $user = (new Utilisateur())->setPrenom('Prénom')->setNom('NOM du participant');
            $inscription = (new Inscription())->setUtilisateur($user)->setColloque($colloque)->setInstitution('Institution du participant')
                ->setCategorie((new CategorieParticipant())->setLibelle('Exemple'));
        }
        $attestation = (new Attestation())->setInscription($inscription)
            ->setNumero(sprintf('%s-%d-%05d', $inscription->getColloque()->getSigle(), $inscription->getColloque()->getAnnee(), $inscription->getId() ?? 0));

        return $this->generator->generate($attestation, rtrim($this->frontUrl, '/').'/verification/'.$attestation->getCodeVerification(), $template);
    }
}
