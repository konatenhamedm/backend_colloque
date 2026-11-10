<?php

namespace App\Inscription\UI\Command;

use App\Inscription\Domain\Repository\InscriptionTokenRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:purge-tokens', description: 'Supprime les liens d\'inscription expirés ou déjà utilisés (à planifier en cron).')]
class PurgeInscriptionTokensCommand extends Command
{
    public function __construct(private readonly InscriptionTokenRepositoryInterface $tokens)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln(sprintf('%d lien(s) supprimé(s).', $this->tokens->purgerExpires()));
        return Command::SUCCESS;
    }
}
