<?php

namespace App\Inscription\Infrastructure\Pdf;

use App\Inscription\Application\Service\RecuGeneratorInterface;
use App\Inscription\Domain\Entity\Paiement;
use Dompdf\Dompdf;
use Dompdf\Options;

/** Reçu A5 imprimable : preuve d'inscription à présenter à l'accueil le jour de l'événement. */
class DompdfRecuGenerator implements RecuGeneratorInterface
{
    public function generate(Paiement $paiement): string
    {
        $ins = $paiement->getInscription();
        $col = $ins->getColloque();
        $u = $ins->getUtilisateur();
        $e = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES);
        $numero = sprintf('REC-%d-%05d', $col->getAnnee(), $paiement->getId());
        $montant = number_format($paiement->getMontant(), 0, ',', ' ').' FCFA';
        $date = ($paiement->getTraiteAt() ?? new \DateTimeImmutable())->format('d/m/Y');
        $dateEvt = $col->getDateDebut()->format('d/m/Y');

        $html = <<<HTML
<html><head><meta charset="utf-8"><style>
@page { margin: 0; }
body { margin: 0; font-family: DejaVu Sans, sans-serif; color: #12214f; font-size: 12px; }
.band { height: 12px; background: #1b2a6b; }
.band i { display: block; float: left; height: 12px; }
.wrap { padding: 26px 34px; }
h1 { font-size: 22px; margin: 0; color: #1b2a6b; letter-spacing: 1px; }
.num { color: #d9730d; font-weight: bold; font-size: 13px; margin-top: 4px; }
.evt { margin: 14px 0 18px; padding: 10px 12px; background: #f3f5fb; border-left: 4px solid #d9730d; }
table.rows { width: 100%; border-collapse: collapse; }
table.rows td { padding: 7px 0; border-bottom: 1px solid #e3e7f2; vertical-align: top; }
table.rows td:first-child { color: #5b6b86; width: 38%; }
.total { margin-top: 16px; text-align: right; font-size: 18px; font-weight: bold; color: #1f8a3b; }
.paid { display: inline-block; border: 2px solid #1f8a3b; color: #1f8a3b; padding: 3px 12px; font-weight: bold; letter-spacing: 2px; margin-top: 12px; }
.foot { margin-top: 22px; font-size: 10px; color: #5b6b86; line-height: 1.5; }
</style></head><body>
<div class="band"><i style="width:40%;background:#1b2a6b"></i><i style="width:30%;background:#d9730d"></i><i style="width:30%;background:#1f8a3b"></i></div>
<div class="wrap">
  <h1>REÇU DE PAIEMENT</h1>
  <div class="num">N° {$e($numero)}</div>
  <div class="evt"><strong>{$e($col->getTitre())}</strong><br>{$e($dateEvt)} · {$e($col->getLieu())}</div>
  <table class="rows">
    <tr><td>Participant</td><td><strong>{$e($u->getNomComplet())}</strong></td></tr>
    <tr><td>Institution</td><td>{$e($ins->getInstitution())}</td></tr>
    <tr><td>Catégorie</td><td>{$e($ins->getCategorie()->getLibelle())}</td></tr>
    <tr><td>Mode de paiement</td><td>{$e($paiement->getCanal()->label())}</td></tr>
    <tr><td>Référence transaction</td><td>{$e($paiement->getReference() ?? '—')}</td></tr>
    <tr><td>Paiement validé le</td><td>{$e($date)}</td></tr>
  </table>
  <div class="total">{$e($montant)}</div>
  <div style="text-align:right"><span class="paid">PAYÉ</span></div>
  <div class="foot">Document à imprimer et à présenter à l'accueil le jour de l'événement. Les frais de participation ne sont pas remboursables.</div>
</div>
</body></html>
HTML;

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $pdf = new Dompdf($options);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A5', 'portrait');
        $pdf->render();

        return $pdf->output();
    }
}
