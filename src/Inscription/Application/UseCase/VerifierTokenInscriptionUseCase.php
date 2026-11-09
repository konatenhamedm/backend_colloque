<?php

namespace App\Inscription\Application\UseCase;

use App\Inscription\Domain\Entity\InscriptionToken;
use App\Inscription\Domain\Repository\InscriptionTokenRepositoryInterface;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;

class VerifierTokenInscriptionUseCase
{
    public function __construct(private readonly InscriptionTokenRepositoryInterface $tokens)
    {
    }

    public function execute(string $plain): InscriptionToken
    {
        $token = $this->tokens->findByPlainToken($plain);
        if ($token === null || !$token->isUsable()) {
            throw new GoneHttpException('Ce lien est invalide ou a expiré. Recommencez votre inscription pour recevoir un nouveau lien.');
        }
        return $token;
    }
}
