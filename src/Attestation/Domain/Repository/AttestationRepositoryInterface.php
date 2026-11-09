<?php

namespace App\Attestation\Domain\Repository;

use App\Attestation\Domain\Entity\Attestation;
use App\Inscription\Domain\Entity\Inscription;

interface AttestationRepositoryInterface
{
    public function findByCode(string $code): ?Attestation;
    public function findByInscription(Inscription $inscription): ?Attestation;
    public function add(Attestation $attestation, bool $flush = false): void;
}
