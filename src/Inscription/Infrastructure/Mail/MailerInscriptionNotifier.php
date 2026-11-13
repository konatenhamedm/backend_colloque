<?php

namespace App\Inscription\Infrastructure\Mail;

use App\Colloque\Domain\Repository\ColloqueRepositoryInterface;
use App\Inscription\Application\Service\InscriptionNotifierInterface;
use App\Inscription\Domain\Entity\Inscription;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

/** Envoie les e-mails à partir des templates Twig de templates/email/. Aucun HTML n'est écrit ici. */
class MailerInscriptionNotifier implements InscriptionNotifierInterface
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        private readonly ColloqueRepositoryInterface $colloques,
        private readonly string $frontUrl,
        private readonly string $mailerFromAddress,
        private readonly string $mailerFromName,
        private readonly string $adminNotificationEmail = '',
    ) {
    }

    public function inscriptionRecue(Inscription $inscription): void
    {
        $this->send($inscription->getUtilisateur()->getEmail(), 'Félicitations, votre inscription est enregistrée', 'email/inscription_recue.html.twig', ['inscription' => $inscription], $inscription);
    }

    public function nouvelleInscription(Inscription $inscription): void
    {
        if ($this->adminNotificationEmail === '') {
            return;
        }
        $this->send($this->adminNotificationEmail, 'Nouvelle inscription à vérifier', 'email/admin_nouvelle_inscription.html.twig', ['inscription' => $inscription, 'paiement' => $inscription->getDernierPaiement()], $inscription);
    }

    public function paiementValide(Inscription $inscription, string $recuPdf): void
    {
        $this->send($inscription->getUtilisateur()->getEmail(), 'Inscription validée — votre reçu de paiement', 'email/paiement_valide.html.twig', ['inscription' => $inscription], $inscription, ['recu-paiement.pdf' => $recuPdf]);
    }

    public function paiementRejete(Inscription $inscription, string $motif): void
    {
        $this->send($inscription->getUtilisateur()->getEmail(), 'Votre preuve de paiement n\'a pas pu être validée', 'email/paiement_rejete.html.twig', ['inscription' => $inscription, 'motif' => $motif], $inscription);
    }

    public function attestationDisponible(Inscription $inscription, string $attestationPdf): void
    {
        $this->send($inscription->getUtilisateur()->getEmail(), 'Votre attestation de participation', 'email/attestation.html.twig', ['inscription' => $inscription], $inscription, ['attestation.pdf' => $attestationPdf]);
    }

    /**
     * Un e-mail qui échoue ne doit jamais faire échouer l'opération métier déjà enregistrée.
     * @param array<string,mixed> $context
     * @param array<string,string> $pieces nom => contenu PDF
     */
    private function send(string $to, string $subject, string $template, array $context, ?Inscription $inscription = null, array $pieces = []): void
    {
        try {
            $mail = (new TemplatedEmail())
                ->from(new Address($this->mailerFromAddress, $this->mailerFromName))
                ->to($to)
                ->subject($subject)
                ->htmlTemplate($template)
                ->context($context + ['frontUrl' => rtrim($this->frontUrl, '/'), 'colloque' => $inscription?->getColloque() ?? $this->colloques->findCurrent()]);
            foreach ($pieces as $nom => $contenu) {
                $mail->attach($contenu, $nom, 'application/pdf');
            }
            $this->mailer->send($mail);
        } catch (\Throwable $e) {
            $this->logger->error('Échec d\'envoi d\'e-mail', ['to' => $to, 'template' => $template, 'error' => $e->getMessage()]);
        }
    }
}
