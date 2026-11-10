<?php

namespace App\Colloque\UI\Command;

use App\Colloque\Domain\Repository\ColloqueRepositoryInterface;
use App\Colloque\Infrastructure\Seed\AnimateurPatches;
use App\Colloque\Infrastructure\Seed\ColloqueSeeder;
use App\Colloque\Infrastructure\Seed\SeedImageImporter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:seed-colloque', description: 'Production : crée le colloque initial, ou remplace un ancien contenu initial NON modifié et sans inscription. N\'efface jamais de données saisies.')]
class SeedColloqueCommand extends Command
{
    public function __construct(
        private readonly ColloqueRepositoryInterface $colloques,
        private readonly EntityManagerInterface $em,
        private readonly SeedImageImporter $photos,
        private readonly AnimateurPatches $patches,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $current = $this->colloques->findCurrent();

        if ($current === null) {
            $nouveau = (new ColloqueSeeder())->build();
            $this->photos->importPhotos($nouveau);
            $this->em->persist($nouveau);
            $this->em->flush();
            $io->success('Colloque initial créé.');
            return Command::SUCCESS;
        }

        // Corrections de contenu ponctuelles (idempotentes), même si la configuration a été modifiée depuis les Paramètres.
        foreach ($this->patches->appliquer($current) as $fait) {
            $io->writeln('✔ '.$fait);
        }
        $this->em->flush();

        $key = $current->getSeedKey();
        // « custom » : un administrateur a modifié la configuration -> jamais touché.
        // Même version : rien à faire.
        if ($key === 'custom') {
            $io->writeln('Colloque déjà configuré : rien à faire.');
            return Command::SUCCESS;
        }
        if ($key === ColloqueSeeder::SEED_KEY) {
            // Contenu initial à jour, pas encore modifié par un admin : on complète seulement les photos manquantes.
            $n = $this->photos->importPhotos($current);
            $this->em->flush();
            $io->writeln($n > 0 ? sprintf('%d photo(s) d\'animateur importée(s).', $n) : 'Colloque déjà configuré : rien à faire.');
            return Command::SUCCESS;
        }

        // Ancien contenu initial (jamais modifié à la main) : remplaçable uniquement s'il n'y a AUCUNE inscription.
        $inscriptions = (int) $this->em->createQuery('SELECT COUNT(i.id) FROM App\Inscription\Domain\Entity\Inscription i WHERE i.colloque = :c')
            ->setParameter('c', $current)->getSingleScalarResult();
        if ($inscriptions > 0) {
            $io->warning(sprintf('Ancien contenu conservé : %d inscription(s) y sont rattachées.', $inscriptions));
            return Command::SUCCESS;
        }

        $this->em->wrapInTransaction(function () use ($current) {
            $this->em->remove($current);
            $this->em->flush();
            $nouveau = (new ColloqueSeeder())->build();
            $this->photos->importPhotos($nouveau);
            $this->em->persist($nouveau);
        });
        $io->success('Ancien contenu initial remplacé par « '.ColloqueSeeder::SEED_KEY.' » (aucune inscription n\'était concernée).');
        return Command::SUCCESS;
    }
}
