<?php

namespace App\Inscription\Infrastructure\Pdf;

use App\Colloque\Domain\Entity\ColloqueItem;
use App\Inscription\Application\Service\RecuGeneratorInterface;
use App\Inscription\Domain\Entity\Paiement;
use App\Shared\Infrastructure\Service\ImageEmbedder;
use Dompdf\Dompdf;
use Dompdf\Options;

/** Reçu A5 imprimable : logos des organisateurs en en-tête, signatures en pied. À présenter à l'accueil le jour de l'événement. */
class DompdfRecuGenerator implements RecuGeneratorInterface
{
    public function __construct(private readonly ImageEmbedder $images)
    {
    }

    public function generate(Paiement $paiement): string
    {
        $ins = $paiement->getInscription();
        $col = $ins->getColloque();
        $u = $ins->getUtilisateur();
        $e = static fn (?string $v): string => htmlspecialchars((string) $v, ENT_QUOTES);
        $numero = sprintf('REC-%d-%05d', $col->getAnnee(), $paiement->getId() ?? 0);
        $f = fn (int $n) => number_format($n, 0, ',', ' ').' FCFA';
        $detail = 'Frais de participation : '.$f($ins->getCategorie()->getMontant()).($ins->hasRepas() ? '<br>Repas : '.$f($col->getPrixRepas() ?? 0) : '');
        $montant = number_format($paiement->getMontant(), 0, ',', ' ').' FCFA';
        $date = ($paiement->getTraiteAt() ?? new \DateTimeImmutable())->format('d/m/Y');
        $quand = $col->getDateDebut() == $col->getDateFin() ? $col->getDateDebut()->format('d/m/Y') : $col->getDateDebut()->format('d/m/Y').' au '.$col->getDateFin()->format('d/m/Y');
        if ($col->getHeures()) {
            $quand .= ' · '.$col->getHeures();
        }

        // En-tête : logos des organisateurs (s'il y en a)
        $logos = '';
        foreach ($col->getItemsByType(ColloqueItem::PARTENAIRE) as $p) {
            if ($uri = $this->images->dataUri($p->getImage())) {
                $logos .= '<img src="'.$uri.'" style="height:34px;margin-right:12px" alt="'.$e($p->getRole() ?? $p->getNom()).'">';
            }
        }
        $logosHtml = $logos !== '' ? '<div style="margin-bottom:12px">'.$logos.'</div>' : '';

        // Signatures
        $sigs = $col->getItemsByType(ColloqueItem::SIGNATAIRE);
        $sigHtml = '';
        if ($sigs !== []) {
            $w = (int) floor(100 / min(count($sigs), 3));
            $sigHtml = '<table width="100%" style="margin-top:20px"><tr>';
            foreach (array_slice($sigs, 0, 3) as $s) {
                $uri = $this->images->dataUri($s->getImage());
                $sigHtml .= '<td width="'.$w.'%" style="vertical-align:top;text-align:center;padding:0 6px">'
                    .'<div style="font-size:9px;color:#5b6b86;text-transform:uppercase;letter-spacing:1px">'.$e($s->getRole()).'</div>'
                    .'<div style="height:54px;margin:2px 0">'.($uri ? '<img src="'.$uri.'" style="max-height:52px;max-width:150px">' : '').'</div>'
                    .'<div style="border-top:1px solid #1b2a6b;padding-top:4px;font-size:10px"><strong>'.$e($s->getNom()).'</strong>'
                    .($s->getTitre() ? '<br><span style="color:#5b6b86">'.$e($s->getTitre()).'</span>' : '')
                    .($s->getDetails() ? '<br><span style="color:#5b6b86;font-size:9px">'.$e($s->getDetails()).'</span>' : '').'</div></td>';
            }
            $sigHtml .= '</tr></table>';
        }

        $avantages = $col->getAvantages() !== [] ? 'Ces frais donnent droit à : '.$e(implode(', ', array_map('mb_strtolower', $col->getAvantages()))).'. ' : '';
        $titre = $e($col->getTitre()).($col->getSousTitre() ? ' — '.$e($col->getSousTitre()) : '');

        $html = <<<HTML
<html><head><meta charset="utf-8"><style>
@page { margin: 0; }
body { margin: 0; font-family: DejaVu Sans, sans-serif; color: #12214f; font-size: 12px; }
.band { height: 12px; background: #1b2a6b; }
.band i { display: block; float: left; height: 12px; }
.wrap { padding: 20px 30px; }
h1 { font-size: 22px; margin: 0; color: #1b2a6b; letter-spacing: 1px; }
.num { color: #d9730d; font-weight: bold; font-size: 13px; margin-top: 4px; }
.evt { margin: 12px 0 14px; padding: 10px 12px; background: #f3f5fb; border-left: 4px solid #d9730d; }
table.rows { width: 100%; border-collapse: collapse; }
table.rows td { padding: 6px 0; border-bottom: 1px solid #e3e7f2; vertical-align: top; }
table.rows td:first-child { color: #5b6b86; width: 38%; }
.total { margin-top: 12px; text-align: right; font-size: 18px; font-weight: bold; color: #1f8a3b; }
.paid { display: inline-block; border: 2px solid #1f8a3b; color: #1f8a3b; padding: 3px 12px; font-weight: bold; letter-spacing: 2px; margin-top: 8px; }
.foot { margin-top: 14px; font-size: 9px; color: #5b6b86; line-height: 1.5; }
</style></head><body>
<div class="band"><i style="width:40%;background:#1b2a6b"></i><i style="width:30%;background:#d9730d"></i><i style="width:30%;background:#1f8a3b"></i></div>
<div class="wrap">
  {$logosHtml}
  <h1>REÇU DE PAIEMENT</h1>
  <div class="num">N° {$e($numero)}</div>
  <div class="evt"><strong>{$titre}</strong><br>{$e($quand)} · {$e($col->getLieu())}</div>
  <table class="rows">
    <tr><td>Participant</td><td><strong>{$e($u->getNomComplet())}</strong></td></tr>
    <tr><td>Institution</td><td>{$e($ins->getInstitution())}</td></tr>
    <tr><td>Catégorie</td><td>{$e($ins->getCategorie()->getLibelle())}</td></tr>
    <tr><td>Détail</td><td>{$detail}</td></tr>
    <tr><td>Mode de paiement</td><td>{$e($paiement->getCanal()->label())}</td></tr>
    <tr><td>Référence transaction</td><td>{$e($paiement->getReference() ?? '—')}</td></tr>
    <tr><td>Paiement validé le</td><td>{$e($date)}</td></tr>
  </table>
  <div class="total">{$e($montant)}</div>
  <div style="text-align:right"><span class="paid">PAYÉ</span></div>
  {$sigHtml}
  <div class="foot">{$avantages}Document à imprimer et à présenter à l'accueil le jour de l'événement. Les frais de participation ne sont pas remboursables.</div>
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
