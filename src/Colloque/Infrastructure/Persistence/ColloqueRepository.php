<?php

namespace App\Colloque\Infrastructure\Persistence;

use App\Colloque\Domain\Entity\Colloque;
use App\Colloque\Domain\Repository\ColloqueRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Colloque> */
class ColloqueRepository extends ServiceEntityRepository implements ColloqueRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Colloque::class);
    }

    public function findById(int $id): ?Colloque
    {
        return $this->find($id);
    }

    public function findCurrent(): ?Colloque
    {
        return $this->findOneBy(['isCurrent' => true], ['id' => 'DESC']);
    }
}
