<?php

namespace App\Colloque\Infrastructure\Persistence;

use App\Colloque\Domain\Entity\AxeThematique;
use App\Colloque\Domain\Repository\AxeThematiqueRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AxeThematique> */
class AxeThematiqueRepository extends ServiceEntityRepository implements AxeThematiqueRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AxeThematique::class);
    }

    public function findById(int $id): ?AxeThematique
    {
        return $this->find($id);
    }

}
