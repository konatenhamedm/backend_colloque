<?php

namespace App\Attestation\Infrastructure\Persistence;

use App\Attestation\Domain\Entity\Attestation;
use App\Attestation\Domain\Repository\AttestationRepositoryInterface;
use App\Inscription\Domain\Entity\Inscription;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Attestation> */
class AttestationRepository extends ServiceEntityRepository implements AttestationRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Attestation::class);
    }

    public function findByCode(string $code): ?Attestation
    {
        return $this->findOneBy(['codeVerification' => $code]);
    }

    public function findByInscription(Inscription $inscription): ?Attestation
    {
        return $this->findOneBy(['inscription' => $inscription]);
    }

    public function add(Attestation $attestation, bool $flush = false): void
    {
        $this->getEntityManager()->persist($attestation);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
