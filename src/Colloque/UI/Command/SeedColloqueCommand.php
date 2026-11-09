<?php

namespace App\Colloque\UI\Command;

use App\Colloque\Domain\Repository\ColloqueRepositoryInterface;
use App\Colloque\Infrastructure\Seed\ColloqueSeeder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:seed-colloque', description: 'Production : crée le colloque initial (axes, tarifs, calendrier, canaux…) s\'il n\'existe pas. N\'efface rien, ne crée ni utilisateur ni inscrit.')]
class SeedColloqueCommand extends Command
{
    public function __construct(
        private readonly ColloqueRepositoryInterface $colloques,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if ($this->colloques->findCurrent() !== null) {
            $io->writeln('Un colloque existe déjà : rien à faire.');
            return Command::SUCCESS;
        }
        $this->em->persist((new ColloqueSeeder())->build());
        $this->em->flush();
        $io->success('Colloque initial créé.');
        return Command::SUCCESS;
    }
}
