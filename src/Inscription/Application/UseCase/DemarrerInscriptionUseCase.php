<?php

namespace App\Inscription\Application\UseCase;

use App\Colloque\Domain\Repository\ColloqueRepositoryInterface;
use App\Inscription\Application\Service\InscriptionNotifierInterface;
use App\Inscription\Domain\Entity\InscriptionToken;
use App\Inscription\Domain\Repository\InscriptionTokenRepositoryInterface;
use App\Security\Domain\Repository\UtilisateurRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/** Étape 1 : le visiteur saisit son e-mail, on lui envoie un lien contenant un jeton à usage unique. */
class DemarrerInscriptionUseCase
{
    public function __construct(
        private readonly UtilisateurRepositoryInterface $users,
        private readonly ColloqueRepositoryInterface $colloques,
        private readonly InscriptionTokenRepositoryInterface $tokens,
        private readonly InscriptionNotifierInterface $notifier,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function execute(string $email): void
    {
        $email = mb_strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new BadRequestHttpException('Adresse e-mail invalide.');
        }
        if ($this->colloques->findCurrent() === null) {
            throw new BadRequestHttpException('Les inscriptions ne sont pas ouvertes.');
        }
        // Réponse identique que l'e-mail soit connu ou non (pas d'énumération) : on n'envoie rien s'il a déjà un compte.
        if ($this->users->findByEmail($email) !== null) {
            return;
        }
        $plain = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $this->tokens->add(new InscriptionToken($email, $plain));
        $this->em->flush();
        $this->notifier->lienInscription($email, $plain);
    }
}
