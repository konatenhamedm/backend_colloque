<?php

namespace App\Travaux\Application\UseCase;

use App\Inscription\Application\Service\InscriptionAccessChecker;
use App\Security\Domain\Entity\Utilisateur;
use App\Travaux\Domain\Repository\CommunicationRepositoryInterface;

class ListMesCommunicationsUseCase
{
    public function __construct(
        private readonly InscriptionAccessChecker $access,
        private readonly CommunicationRepositoryInterface $communications,
    ) {
    }

    public function execute(Utilisateur $user): array
    {
        return $this->communications->findByInscription($this->access->requireActive($user));
    }
}
