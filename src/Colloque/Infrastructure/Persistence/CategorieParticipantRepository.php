<?php

namespace App\Colloque\Infrastructure\Persistence;

use App\Colloque\Domain\Entity\CategorieParticipant;
use App\Colloque\Domain\Repository\CategorieParticipantRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<CategorieParticipant> */
class CategorieParticipantRepository extends ServiceEntityRepository implements CategorieParticipantRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CategorieParticipant::class);
    }

    public function findById(int $id): ?CategorieParticipant
    {
        return $this->find($id);
    }

}
