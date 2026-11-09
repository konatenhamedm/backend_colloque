<?php

namespace App\Inscription\Application\UseCase;

use App\Inscription\Domain\Entity\Inscription;
use App\Inscription\Domain\Repository\InscriptionRepositoryInterface;
use App\Security\Domain\Entity\Utilisateur;

class GetMonInscriptionUseCase
{
    public function __construct(private readonly InscriptionRepositoryInterface $inscriptions)
    {
    }

    public function execute(Utilisateur $user): ?Inscription
    {
        return $this->inscriptions->findByUtilisateur($user);
    }
}
