<?php

namespace App\Shared\Infrastructure\Repository;

use App\Shared\Domain\Entity\Fichier;
use App\Shared\Domain\Repository\FichierRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Fichier> */
class FichierRepository extends ServiceEntityRepository implements FichierRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Fichier::class);
    }

    public function findByUuid(string $uuid): ?Fichier
    {
        return $this->findOneBy(['uuid' => $uuid]);
    }

    public function add(Fichier $fichier, bool $flush = false): void
    {
        $this->getEntityManager()->persist($fichier);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Fichier $fichier, bool $flush = false): void
    {
        $this->getEntityManager()->remove($fichier);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
