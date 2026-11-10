<?php

namespace App\Inscription\Domain\Repository;

use App\Inscription\Domain\Entity\InscriptionToken;

interface InscriptionTokenRepositoryInterface
{
    public function findByPlainToken(string $plain): ?InscriptionToken;
    /** @return InscriptionToken[] liens encore valides (non utilisés, non expirés), les plus récents d'abord */
    public function findActifs(): array;
    /** Supprime les jetons expirés ou utilisés ; retourne le nombre de lignes supprimées. */
    public function purgerExpires(): int;
    public function add(InscriptionToken $token, bool $flush = false): void;
}
