<?php

namespace App\Attestation\Infrastructure\Pdf;

use App\Attestation\Application\Service\AttestationGeneratorInterface;
use App\Attestation\Domain\Entity\Attestation;
use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;

/**
 * Attestation A4 paysage, avec en-tête, pied de page et QR code de vérification.
 * Deux modèles : « classique » (cadre double, signatures) et « moderne » (bandeau latéral).
 * Choix via ATTESTATION_TEMPLATE. Dompdf ne gère pas flexbox : tout est en tableaux / positions absolues (1123 × 794 px).
 */
class DompdfAttestationGenerator implements AttestationGeneratorInterface
{
    public const TEMPLATES = ['classique', 'moderne'];

    public function __construct(private readonly string $attestationTemplate = 'classique')
    {
    }

    public function getDefaultTemplate(): string
    {
        return in_array($this->attestationTemplate, self::TEMPLATES, true) ? $this->attestationTemplate : 'classique';
    }

    public function generate(Attestation $attestation, string $urlVerification, ?string $template = null): string
    {
        $d = $this->data($attestation, $urlVerification);
        $html = match ($template ?? $this->attestationTemplate) {
            'moderne' => $this->moderne($d),
            default => $this->classique($d),
        };

        $options = new Options();
        $options->set('isRemoteEnabled', false); // QR et logo sont embarqués en data URI : aucune requête externe
        $options->set('defaultFont', 'DejaVu Sans');
        $pdf = new Dompdf($options);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4', 'landscape');
        $pdf->render();

        return $pdf->output();
    }

    /** @return array<string,string> toutes les valeurs sont déjà échappées pour le HTML */
    private function data(Attestation $a, string $url): array
    {
        $i = $a->getInscription();
        $c = $i->getColloque();
        $e = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES);
        $orgs = $c->getContacts()['organisateurs'] ?? [];
        $debut = $c->getDateDebut();
        $fin = $c->getDateFin();
        $dates = $debut == $fin ? 'le '.$debut->format('d/m/Y') : 'du '.$debut->format('d').' au '.$fin->format('d/m/Y');

        return [
            'nom' => $e($i->getUtilisateur()->getNomComplet()),
            'institution' => $e($i->getInstitution()),
            'qualite' => $i->isCommunicant() ? 'communicant(e)' : 'participant(e)',
            'titre' => $e($c->getTitre()),
            'theme' => $e($c->getTheme()),
            'dates' => $e($dates),
            'lieu' => $e($c->getLieu()),
            'numero' => $e($a->getNumero()),
            'code' => $e($a->getCodeVerification()),
            'emise' => $a->getEmiseAt()->format('d/m/Y'),
            'email' => $e((string) ($c->getContacts()['email'] ?? '')),
            'org1' => $e((string) ($orgs[0] ?? 'Organisateur')),
            'org2' => $e((string) ($orgs[1] ?? '')),
            'qr' => (new Builder(writer: new PngWriter(), data: $url, size: 240, margin: 0))->build()->getDataUri(),
            'logo' => 'data:image/svg+xml;base64,'.base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" rx="14" fill="#1b2a6b"/><path d="M14 44V22l18-8 18 8v22" fill="none" stroke="#fff" stroke-width="4" stroke-linejoin="round"/><path d="M22 44V30h20v14" fill="#d9730d"/><rect x="28" y="36" width="8" height="8" fill="#1f8a3b"/></svg>'),
        ];
    }

    private function classique(array $d): string
    {
        return <<<HTML
<html><head><meta charset="utf-8"><style>
@page { margin: 0; }
body { margin: 0; font-family: DejaVu Sans, sans-serif; color: #12214f; }
.abs { position: absolute; }
.tri { height: 12px; }
.tri td { height: 12px; padding: 0; }
.serif { font-family: DejaVu Serif, serif; }
.cap { font-size: 9px; letter-spacing: 2.4px; text-transform: uppercase; color: #5b6b86; }
.sig { border-top: 1px solid #1b2a6b; padding-top: 6px; font-size: 10px; color: #5b6b86; text-align: center; }
</style></head><body>
<table class="tri abs" style="top:0;left:0;width:1123px;border-collapse:collapse"><tr><td style="width:40%;background:#1b2a6b"></td><td style="width:30%;background:#d9730d"></td><td style="width:30%;background:#1f8a3b"></td></tr></table>
<div class="abs" style="top:26px;left:26px;width:1069px;height:738px;border:3px solid #1b2a6b"></div>
<div class="abs" style="top:37px;left:37px;width:1047px;height:716px;border:1px solid #d9730d"></div>

<!-- EN-TÊTE -->
<table class="abs" style="top:62px;left:78px;width:967px"><tr>
  <td style="width:90px"><img src="{$d['logo']}" width="68" height="68"></td>
  <td style="vertical-align:middle"><div style="font-size:15px;font-weight:bold;color:#1b2a6b;letter-spacing:.5px">{$d['org1']}</div><div class="cap" style="margin-top:4px">{$d['org2']}</div></td>
  <td style="text-align:right;vertical-align:middle"><div class="cap">Attestation n°</div><div style="font-size:15px;font-weight:bold;color:#d9730d">{$d['numero']}</div></td>
</tr></table>
<div class="abs" style="top:140px;left:78px;width:967px;height:1px;background:#e0e5f2"></div>

<!-- CORPS -->
<div class="abs" style="top:152px;left:78px;width:967px;text-align:center">
  <div class="serif" style="font-size:40px;letter-spacing:6px;color:#1b2a6b;font-weight:bold">ATTESTATION</div>
  <div style="margin-top:6px;font-size:12px;letter-spacing:7px;text-transform:uppercase;color:#d9730d;font-weight:bold">de participation</div>
  <div style="margin-top:20px;font-size:13px;color:#5b6b86">Il est attesté que</div>
  <div class="serif" style="margin-top:8px;font-size:34px;font-weight:bold;color:#12214f">{$d['nom']}</div>
  <div style="width:300px;height:2px;background:#d9730d;margin:8px auto 8px"></div>
  <div style="font-size:13px;color:#5b6b86">{$d['institution']}</div>
  <div style="margin:14px 60px 0;font-size:13.5px;line-height:1.65">a pris part, en qualité de <strong>{$d['qualite']}</strong>, au<br><strong style="color:#1b2a6b">{$d['titre']}</strong>,<br>qui s'est tenu {$d['dates']} à {$d['lieu']}.</div>
  <div class="serif" style="margin:10px 90px 0;font-size:13px;font-style:italic;color:#b5232a">« {$d['theme']} »</div>
</div>

<!-- PIED DE PAGE -->
<table class="abs" style="top:588px;left:78px;width:967px"><tr>
  <td style="width:290px;vertical-align:bottom"><div style="height:30px"></div><div class="sig">Le Coordonnateur du PTRC-GD</div></td>
  <td style="text-align:center;vertical-align:bottom"><img src="{$d['qr']}" width="80" height="80"></td>
  <td style="width:290px;vertical-align:bottom"><div style="height:30px"></div><div class="sig">Le Président du Groupe ICK</div></td>
</tr></table>
<div class="abs" style="top:696px;left:78px;width:967px;text-align:center;font-size:9px;color:#5b6b86;line-height:1.6">
  Délivrée le {$d['emise']} · Authenticité : scannez le QR code ou saisissez le code&nbsp;<strong style="color:#1b2a6b">{$d['code']}</strong> sur la page de vérification<br>
  {$d['email']}
</div>
</body></html>
HTML;
    }

    private function moderne(array $d): string
    {
        return <<<HTML
<html><head><meta charset="utf-8"><style>
@page { margin: 0; }
body { margin: 0; font-family: DejaVu Sans, sans-serif; color: #12214f; }
.abs { position: absolute; }
.serif { font-family: DejaVu Serif, serif; }
.lab { font-size: 9px; letter-spacing: 2px; text-transform: uppercase; color: #f0a35a; }
.val { font-size: 13px; color: #ffffff; margin: 3px 0 18px; line-height: 1.45; }
</style></head><body>
<!-- PANNEAU LATÉRAL (en-tête) -->
<div class="abs" style="top:0;left:0;width:330px;height:794px;background:#1b2a6b"></div>
<div class="abs" style="top:0;left:330px;width:8px;height:794px;background:#d9730d"></div>
<div class="abs" style="top:0;left:338px;width:4px;height:794px;background:#1f8a3b"></div>
<div class="abs" style="top:48px;left:44px;width:250px">
  <img src="{$d['logo']}" width="62" height="62">
  <div style="margin-top:18px;font-size:15px;font-weight:bold;color:#fff;line-height:1.35">{$d['org1']}</div>
  <div style="margin-top:4px;font-size:10px;letter-spacing:1.5px;text-transform:uppercase;color:#aab6e4">{$d['org2']}</div>
  <div style="width:46px;height:3px;background:#d9730d;margin:30px 0 30px"></div>
  <div class="lab">Événement</div><div class="val">{$d['titre']}</div>
  <div class="lab">Date</div><div class="val">{$d['dates']}</div>
  <div class="lab">Lieu</div><div class="val">{$d['lieu']}</div>
</div>

<!-- CORPS -->
<div class="abs" style="top:78px;left:408px;width:660px">
  <div style="font-size:11px;letter-spacing:6px;text-transform:uppercase;color:#d9730d;font-weight:bold">Attestation de participation</div>
  <div class="serif" style="margin-top:34px;font-size:15px;color:#5b6b86;font-style:italic">Nous attestons que</div>
  <div class="serif" style="margin-top:12px;font-size:40px;font-weight:bold;line-height:1.15;color:#1b2a6b">{$d['nom']}</div>
  <div style="width:90px;height:4px;background:#d9730d;margin:20px 0 16px"></div>
  <div style="font-size:13px;color:#5b6b86">{$d['institution']}</div>
  <div style="margin-top:28px;font-size:14px;line-height:1.85">a pris part, en qualité de <strong>{$d['qualite']}</strong>, à cet événement et a contribué par sa présence à la réussite des échanges.</div>
  <div class="serif" style="margin-top:20px;padding-left:16px;border-left:4px solid #1f8a3b;font-size:14px;font-style:italic;color:#12214f;line-height:1.6">{$d['theme']}</div>
</div>

<!-- PIED DE PAGE -->
<div class="abs" style="top:630px;left:408px;width:660px;height:1px;background:#e0e5f2"></div>
<table class="abs" style="top:650px;left:408px;width:660px"><tr>
  <td style="width:96px;vertical-align:middle"><img src="{$d['qr']}" width="84" height="84"></td>
  <td style="vertical-align:middle;font-size:10px;color:#5b6b86;line-height:1.7">
    <strong style="color:#1b2a6b;font-size:12px">N° {$d['numero']}</strong><br>
    Délivrée le {$d['emise']}<br>
    Vérification : scannez le QR code ou saisissez le code <strong style="color:#1b2a6b">{$d['code']}</strong>
  </td>
  <td style="width:200px;vertical-align:bottom;text-align:center"><div style="height:34px"></div><div style="border-top:1px solid #1b2a6b;padding-top:5px;font-size:10px;color:#5b6b86">Le Coordonnateur</div></td>
</tr></table>
</body></html>
HTML;
    }
}
