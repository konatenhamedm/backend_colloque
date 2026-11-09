<?php

namespace App\Attestation\UI\Command;

use App\Attestation\Domain\Entity\Attestation;
use App\Attestation\Infrastructure\Pdf\DompdfAttestationGenerator;
use App\Colloque\Domain\Entity\CategorieParticipant;
use App\Colloque\Domain\Repository\ColloqueRepositoryInterface;
use App\Inscription\Domain\Entity\Inscription;
use App\Security\Domain\Entity\Utilisateur;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:attestation:preview', description: 'Génère un PDF d\'exemple de chaque modèle d\'attestation (rien n\'est enregistré en base)')]
class PreviewAttestationCommand extends Command
{
    public function __construct(
        private readonly DompdfAttestationGenerator $generator,
        private readonly ColloqueRepositoryInterface $colloques,
        private readonly string $privateDirectory = '',
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('nom', null, InputOption::VALUE_REQUIRED, 'Nom affiché', 'Aya KOFFI')
            ->addOption('out', null, InputOption::VALUE_REQUIRED, 'Dossier de sortie', 'var/previews');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $colloque = $this->colloques->findCurrent();
        if (!$colloque) {
            $io->error('Aucun colloque en base (chargez les fixtures).');
            return Command::FAILURE;
        }
        [$prenom, $nom] = array_pad(explode(' ', (string) $input->getOption('nom'), 2), 2, '');
        $user = (new Utilisateur())->setPrenom($prenom)->setNom($nom);
        $ins = (new Inscription())->setUtilisateur($user)->setColloque($colloque)->setInstitution('Université Internationale ICK de Bouaké')
            ->setCategorie((new CategorieParticipant())->setLibelle('Exemple'));
        $att = (new Attestation())->setInscription($ins)->setNumero(sprintf('PTRCGD-%d-00001', $colloque->getAnnee()));

        $dir = rtrim((string) $input->getOption('out'), '/');
        @mkdir($dir, 0775, true);
        foreach (DompdfAttestationGenerator::TEMPLATES as $tpl) {
            $file = "$dir/attestation-$tpl.pdf";
            file_put_contents($file, $this->generator->generate($att, 'https://exemple.test/verification/'.$att->getCodeVerification(), $tpl));
            $io->writeln("✔ $file");
        }
        return Command::SUCCESS;
    }
}
