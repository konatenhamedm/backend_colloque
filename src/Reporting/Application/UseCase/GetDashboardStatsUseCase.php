<?php

namespace App\Reporting\Application\UseCase;

use App\Colloque\Domain\Repository\ColloqueRepositoryInterface;
use App\Inscription\Domain\Enum\CanalPaiement;
use App\Inscription\Domain\Enum\StatutInscription;
use App\Inscription\Domain\Repository\InscriptionRepositoryInterface;
use App\Inscription\Domain\Repository\PaiementRepositoryInterface;
use App\Travaux\Domain\Repository\CommunicationRepositoryInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Indicateurs temps réel du tableau de bord organisateur. */
class GetDashboardStatsUseCase
{
    public function __construct(
        private readonly ColloqueRepositoryInterface $colloques,
        private readonly InscriptionRepositoryInterface $inscriptions,
        private readonly PaiementRepositoryInterface $paiements,
        private readonly CommunicationRepositoryInterface $communications,
    ) {
    }

    /** @return array<string,mixed> */
    public function execute(): array
    {
        $colloque = $this->colloques->findCurrent() ?? throw new NotFoundHttpException('Aucun colloque actif.');
        $parStatut = $this->inscriptions->countByStatut($colloque);
        $n = static fn (StatutInscription $s): int => $parStatut[$s->value] ?? 0;

        $total = array_sum($parStatut);
        $valides = $n(StatutInscription::PAYMENT_VALIDATED) + $n(StatutInscription::ATTENDED_AND_VALIDATED) + $n(StatutInscription::CERTIFICATE_ISSUED);
        $enAttente = $n(StatutInscription::PENDING_PAYMENT);

        $parCanal = $this->paiements->totalsByCanal();
        $collecte = array_sum(array_column($parCanal, 'montant'));
        $parCanalNumero = $this->paiements->totalsByCanalNumero();

        return [
            'inscrits' => [
                'total' => $total,
                'valides' => $valides,
                'enAttente' => $enAttente,
                'rejetes' => $n(StatutInscription::REJECTED),
                'participationsValidees' => $n(StatutInscription::ATTENDED_AND_VALIDATED) + $n(StatutInscription::CERTIFICATE_ISSUED),
                'attestationsEmises' => $n(StatutInscription::CERTIFICATE_ISSUED),
            ],
            // Taux de conversion = inscrits dont le paiement est validé / inscrits totaux
            'tauxConversion' => $total > 0 ? round($valides / $total * 100, 1) : 0.0,
            'parStatut' => array_map(fn (StatutInscription $s) => ['statut' => $s->value, 'libelle' => $s->label(), 'total' => $n($s)], StatutInscription::cases()),
            'finances' => [
                'totalCollecte' => $collecte,
                'montantEnAttente' => $this->paiements->totalEnAttente(),
                'parCanal' => array_map(fn (array $c) => [
                    ...$c,
                    'libelle' => CanalPaiement::from($c['canal'])->label(),
                    'part' => $collecte > 0 ? round($c['montant'] / $collecte * 100, 1) : 0.0,
                ], $parCanal),
                'parCanalNumero' => array_map(fn (array $c) => [
                    ...$c,
                    'libelle' => CanalPaiement::from($c['canal'])->label(),
                    'part' => $collecte > 0 ? round($c['montant'] / $collecte * 100, 1) : 0.0,
                ], $parCanalNumero),
            ],
            'parCategorie' => $this->inscriptions->countByCategorie($colloque),
            'inscriptionsParJour' => $this->inscriptions->countByJour($colloque, 30),
            'communicationsASoumettre' => count($this->communications->search(['statut' => 'SOUMISE'], 1, 1)),
        ];
    }
}
