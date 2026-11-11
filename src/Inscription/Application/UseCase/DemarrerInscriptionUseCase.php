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
        private readonly ?\Psr\Log\LoggerInterface $logger = null,
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
        $this->emettre($email);
    }

    /** Crée un jeton (1 h), purge les anciens, et envoie le lien par e-mail si $envoyer. L'échec d'envoi ne casse pas l'inscription : le lien reste récupérable au back-office. */
    public function emettre(string $email, bool $envoyer = true, ?string &$erreurEnvoi = null): InscriptionToken
    {
        $this->tokens->purgerExpires();
        $plain = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $token = new InscriptionToken($email, $plain);
        $this->tokens->add($token);
        $this->em->flush();
        if ($envoyer) {
            try {
                $this->notifier->lienInscription($email, $plain);
            } catch (\Throwable $e) {
                $erreurEnvoi = $e->getMessage();
                $this->logger?->error('Envoi du lien d\'inscription impossible', ['email' => $email, 'erreur' => $e->getMessage()]);
            }
        }
        return $token;
    }
}
