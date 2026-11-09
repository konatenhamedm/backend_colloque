<?php

namespace App\Security\UI\Command;

use App\Security\Domain\Entity\Utilisateur;
use App\Security\Domain\Repository\UtilisateurRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:create-admin', description: 'Crée un compte organisateur (admin) ou membre du comité scientifique')]
class CreateAdminCommand extends Command
{
    public function __construct(
        private readonly UtilisateurRepositoryInterface $users,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED)
            ->addArgument('password', InputArgument::REQUIRED)
            ->addOption('nom', null, InputOption::VALUE_REQUIRED, 'Nom', 'Admin')
            ->addOption('prenom', null, InputOption::VALUE_REQUIRED, 'Prénom', 'Colloque')
            ->addOption('comite', null, InputOption::VALUE_NONE, 'Rôle comité scientifique au lieu d\'admin');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if ($this->users->findByEmail($input->getArgument('email'))) {
            $io->error('Un compte existe déjà avec cet e-mail.');
            return Command::FAILURE;
        }
        if (strlen($input->getArgument('password')) < 8) {
            $io->error('Mot de passe : 8 caractères minimum.');
            return Command::FAILURE;
        }
        $user = (new Utilisateur())->setEmail($input->getArgument('email'))->setNom($input->getOption('nom'))->setPrenom($input->getOption('prenom'))
            ->setRoles([$input->getOption('comite') ? Utilisateur::ROLE_COMITE : Utilisateur::ROLE_ADMIN]);
        $user->setPassword($this->hasher->hashPassword($user, $input->getArgument('password')));
        $this->users->add($user, true);
        $io->success('Compte créé : '.$user->getEmail());
        return Command::SUCCESS;
    }
}
