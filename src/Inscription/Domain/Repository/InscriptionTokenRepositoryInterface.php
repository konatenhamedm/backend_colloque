<?php

namespace App\Inscription\Domain\Repository;

use App\Inscription\Domain\Entity\InscriptionToken;

interface InscriptionTokenRepositoryInterface
{
    public function findByPlainToken(string $plain): ?InscriptionToken;
    public function add(InscriptionToken $token, bool $flush = false): void;
}
