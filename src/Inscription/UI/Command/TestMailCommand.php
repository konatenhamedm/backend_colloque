<?php

namespace App\Inscription\UI\Command;

use App\Attestation\Application\Service\AttestationGeneratorInterface;
use App\Attestation\Domain\Entity\Attestation;
use App\Colloque\Domain\Entity\CategorieParticipant;
use App\Colloque\Domain\Repository\ColloqueRepositoryInterface;
use App\Inscription\Application\Service\InscriptionNotifierInterface;
use App\Inscription\Application\Service\RecuGeneratorInterface;
use App\Inscription\Domain\Entity\Inscription;
use App\Inscription\Domain\Entity\Paiement;
use App\Inscription\Domain\Enum\CanalPaiement;
use App\Security\Domain\Entity\Utilisateur;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:mail:test', description: 'Envoie les 5 e-mails modèles (données fictives, rien en base) à une adresse pour vérifier SMTP et templates')]
class TestMailCommand extends Command
{
    public function __construct(
        private readonly InscriptionNotifierInterface $notifier,
        private readonly ColloqueRepositoryInterface $colloques,
        private readonly RecuGeneratorInterface $recu,
        private readonly AttestationGeneratorInterface $attestations,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('to', InputArgument::REQUIRED, 'Adresse destinataire');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $colloque = $this->colloques->findCurrent();
        if (!$colloque) {
            $io->error('Aucun colloque en base.');
            return Command::FAILURE;
        }
        $user = (new Utilisateur())->setEmail((string) $input->getArgument('to'))->setPrenom('Aya')->setNom('Koffi');
        $cat = (new CategorieParticipant())->setLibelle('Enseignants-Chercheurs et Chercheurs')->setMontant(40000);
        $ins = (new Inscription())->setUtilisateur($user)->setColloque($colloque)->setCategorie($cat)->setInstitution('Université de démonstration');
        $paiement = (new Paiement())->setCanal(CanalPaiement::WAVE)->setReference('TEST-0001')->setMontant(40000);
        $ins->addPaiement($paiement);
        $att = (new Attestation())->setInscription($ins)->setNumero('PTRCGD-TEST-00001');

        $this->notifier->lienInscription($user->getEmail(), 'jeton-de-test-non-valide');
        $this->notifier->inscriptionRecue($ins);
        $this->notifier->paiementValide($ins, $this->recu->generate($paiement));
        $this->notifier->paiementRejete($ins, 'Le montant du reçu ne correspond pas aux frais de participation.');
        $this->notifier->attestationDisponible($ins, $this->attestations->generate($att, 'https://exemple.test/verification/'.$att->getCodeVerification()));

        $io->success('5 e-mails envoyés à '.$user->getEmail().' (les échecs éventuels sont dans var/log/dev.log).');
        return Command::SUCCESS;
    }
}
