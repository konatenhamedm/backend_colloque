<?php

namespace App\Security\Infrastructure\Persistence;

use App\Security\Domain\Entity\Utilisateur;
use App\Security\Domain\Repository\UtilisateurRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Utilisateur> */
class UtilisateurRepository extends ServiceEntityRepository implements UtilisateurRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Utilisateur::class);
    }

    public function findById(int $id): ?Utilisateur
    {
        return $this->find($id);
    }

    public function findByEmail(string $email): ?Utilisateur
    {
        return $this->findOneBy(['email' => mb_strtolower(trim($email))]);
    }

    public function add(Utilisateur $user, bool $flush = false): void
    {
        $this->getEntityManager()->persist($user);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
