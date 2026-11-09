<?php

namespace App\Inscription\Application\UseCase;

use App\Inscription\Domain\Entity\Inscription;
use App\Inscription\Domain\Enum\StatutInscription;
use App\Inscription\Domain\Repository\InscriptionRepositoryInterface;
use App\Travaux\Domain\Repository\CommunicationRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Validation unitaire (ids) ou globale (ids = null) de la participation :
 * présence effective + travaux validés pour les communicants.
 */
class ValiderParticipationUseCase
{
    public function __construct(
        private readonly InscriptionRepositoryInterface $inscriptions,
        private readonly CommunicationRepositoryInterface $communications,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /** @param int[]|null $ids @return array{valides: int[], ignores: array<int,array{id:int,raison:string}>} */
    public function execute(?array $ids): array
    {
        /** @var Inscription[] $cibles */
        $cibles = $ids === null
            ? $this->inscriptions->findByStatut(StatutInscription::PAYMENT_VALIDATED->value)
            : $this->inscriptions->findByIds(array_map('intval', $ids));

        $valides = $ignores = [];
        foreach ($cibles as $inscription) {
            if ($inscription->getStatut() !== StatutInscription::PAYMENT_VALIDATED) {
                $ignores[] = ['id' => $inscription->getId(), 'raison' => 'Statut « '.$inscription->getStatut()->label().' »'];
                continue;
            }
            if ($inscription->isCommunicant() && !$this->communications->hasValidated($inscription)) {
                $ignores[] = ['id' => $inscription->getId(), 'raison' => 'Communication non validée par le comité'];
                continue;
            }
            $inscription->validerParticipation();
            $valides[] = $inscription->getId();
        }
        $this->em->flush();

        return ['valides' => $valides, 'ignores' => $ignores];
    }
}
