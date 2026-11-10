<?php

namespace App\Attestation\Application\UseCase;

use App\Attestation\Application\Service\AttestationGeneratorInterface;
use App\Attestation\Domain\Entity\Attestation;
use App\Attestation\Domain\Repository\AttestationRepositoryInterface;
use App\Inscription\Application\Service\InscriptionNotifierInterface;
use App\Inscription\Domain\Enum\StatutInscription;
use App\Inscription\Domain\Repository\InscriptionRepositoryInterface;
use App\Shared\Application\Service\FileStorage;
use Doctrine\ORM\EntityManagerInterface;

/** Émission unitaire (ids) ou globale (ids = null) des attestations des participations validées. */
class EmettreAttestationsUseCase
{
    public function __construct(
        private readonly InscriptionRepositoryInterface $inscriptions,
        private readonly AttestationRepositoryInterface $attestations,
        private readonly AttestationGeneratorInterface $generator,
        private readonly FileStorage $storage,
        private readonly InscriptionNotifierInterface $notifier,
        private readonly EntityManagerInterface $em,
        private readonly string $frontUrl,
    ) {
    }

    /** @param int[]|null $ids @return array{emises: int[], ignorees: array<int,array{id:int,raison:string}>} */
    public function execute(?array $ids, ?string $template = null): array
    {
        $cibles = $ids === null
            ? $this->inscriptions->findByStatut(StatutInscription::ATTENDED_AND_VALIDATED->value)
            : $this->inscriptions->findByIds(array_map('intval', $ids));

        $emises = $ignorees = $pdfs = [];
        foreach ($cibles as $inscription) {
            if ($inscription->getStatut() !== StatutInscription::ATTENDED_AND_VALIDATED) {
                $ignorees[] = ['id' => $inscription->getId(), 'raison' => 'Participation non validée (statut « '.$inscription->getStatut()->label().' »)'];
                continue;
            }
            $attestation = (new Attestation())->setInscription($inscription)
                ->setNumero(sprintf('%s-%d-%05d', $inscription->getColloque()->getSigle(), $inscription->getColloque()->getAnnee(), $inscription->getId()));
            $pdf = $this->generator->generate($attestation, rtrim($this->frontUrl, '/').'/verification/'.$attestation->getCodeVerification(), $template);
            $attestation->setFichier($this->storage->storeGenerated($pdf, $attestation->getNumero().'.pdf', 'application/pdf', 'attestations'));
            $this->attestations->add($attestation);
            $inscription->marquerAttestationEmise();
            $emises[] = $inscription->getId();
            $pdfs[$inscription->getId()] = $pdf;
        }
        $this->em->flush();

        foreach ($cibles as $inscription) {
            if (in_array($inscription->getId(), $emises, true)) {
                $this->notifier->attestationDisponible($inscription, $pdfs[$inscription->getId()]);
            }
        }

        return ['emises' => $emises, 'ignorees' => $ignorees];
    }
}
