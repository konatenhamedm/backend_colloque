<?php

namespace App\Attestation\Application\Service;

use App\Attestation\Domain\Entity\Attestation;

interface AttestationGeneratorInterface
{
    /** @return string contenu binaire du PDF */
    public function generate(Attestation $attestation, string $urlVerification, ?string $template = null): string;
}
