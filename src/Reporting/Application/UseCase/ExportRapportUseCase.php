<?php

namespace App\Reporting\Application\UseCase;

use App\Inscription\Domain\Repository\InscriptionRepositoryInterface;
use App\Inscription\Domain\Repository\PaiementRepositoryInterface;
use App\Reporting\Application\Service\TableExporterInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/** Exports comptables : liste d'émargement et rapport financier (Excel / CSV). */
class ExportRapportUseCase
{
    public function __construct(
        private readonly InscriptionRepositoryInterface $inscriptions,
        private readonly PaiementRepositoryInterface $paiements,
        private readonly TableExporterInterface $exporter,
    ) {
    }

    /** @return array{filename: string, content: string, mime: string} */
    public function execute(string $type, string $format): array
    {
        if (!in_array($format, ['xlsx', 'csv'], true)) {
            throw new BadRequestHttpException('Format invalide (xlsx ou csv).');
        }
        $sheets = match ($type) {
            'emargement' => ['Émargement' => $this->emargement()],
            'finances' => ['Paiements' => $this->paiementsSheet(), 'Par canal' => $this->parCanal()],
            default => throw new BadRequestHttpException('Type de rapport inconnu (emargement ou finances).'),
        };

        return [
            'filename' => sprintf('%s_%s.%s', $type, date('Ymd'), $format),
            'content' => $this->exporter->render($sheets, $format),
            'mime' => $format === 'csv' ? 'text/csv; charset=UTF-8' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];
    }

    private function emargement(): array
    {
        $rows = [];
        foreach ($this->inscriptions->searchAll([]) as $i => $ins) {
            if (!$ins->getStatut()->isActiveAccount()) {
                continue; // seuls les inscrits validés figurent sur la liste d'émargement
            }
            $u = $ins->getUtilisateur();
            $rows[] = [count($rows) + 1, $u->getNom(), $u->getPrenom(), $ins->getInstitution(), $ins->getCategorie()->getLibelle(),
                $ins->getTypeParticipation()->label(), $u->getEmail(), $u->getTelephone(), $ins->getStatut()->label(), $ins->hasRepas() ? 'Oui' : 'Non', $ins->hasPresence() ? 'Présent' : '', ''];
        }
        return ['headers' => ['N°', 'Nom', 'Prénom', 'Institution', 'Catégorie', 'Type', 'E-mail', 'Téléphone', 'Statut', 'Déjeuner', 'Présence', 'Signature'], 'rows' => $rows];
    }

    private function paiementsSheet(): array
    {
        $rows = [];
        foreach ($this->paiements->findAllValides() as $p) {
            $u = $p->getInscription()->getUtilisateur();
            $rows[] = [$p->getTraiteAt()?->format('d/m/Y H:i'), $u->getNomComplet(), $p->getInscription()->getCategorie()->getLibelle(), $p->getCanal()->label(), $p->getNumeroDepot() ?? '', $p->getReference() ?? '', $p->getMontant()];
        }
        return ['headers' => ['Date de validation', 'Participant', 'Catégorie', 'Canal', 'N° de dépôt', 'Référence', 'Montant (FCFA)'], 'rows' => $rows];
    }

    private function parCanal(): array
    {
        $rows = [];
        $total = 0;
        foreach ($this->paiements->totalsByCanalNumero() as $c) {
            $rows[] = [\App\Inscription\Domain\Enum\CanalPaiement::from($c['canal'])->label(), $c['numero'] ?? 'Non précisé', $c['total'], $c['montant']];
            $total += $c['montant'];
        }
        $rows[] = ['TOTAL', '', array_sum(array_column($rows, 2)), $total];
        return ['headers' => ['Canal', 'N° de dépôt', 'Nb paiements', 'Montant (FCFA)'], 'rows' => $rows];
    }
}
