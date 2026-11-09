<?php

namespace App\Travaux\Infrastructure\Persistence;

use App\Inscription\Domain\Entity\Inscription;
use App\Travaux\Domain\Entity\Communication;
use App\Travaux\Domain\Enum\StatutCommunication;
use App\Travaux\Domain\Repository\CommunicationRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Communication> */
class CommunicationRepository extends ServiceEntityRepository implements CommunicationRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Communication::class);
    }

    public function findById(int $id): ?Communication
    {
        return $this->find($id);
    }

    public function findByInscription(Inscription $inscription): array
    {
        return $this->findBy(['inscription' => $inscription], ['id' => 'DESC']);
    }

    public function hasValidated(Inscription $inscription): bool
    {
        return $this->count(['inscription' => $inscription, 'statut' => StatutCommunication::VALIDEE]) > 0;
    }

    public function search(array $filters, int $page, int $limit): Paginator
    {
        $qb = $this->createQueryBuilder('c')
            ->join('c.inscription', 'i')->addSelect('i')
            ->join('i.utilisateur', 'u')->addSelect('u')
            ->join('c.axe', 'a')->addSelect('a')
            ->orderBy('c.id', 'DESC')
            ->setFirstResult(($page - 1) * $limit)->setMaxResults($limit);
        if (!empty($filters['statut'])) {
            $qb->andWhere('c.statut = :s')->setParameter('s', $filters['statut']);
        }
        if (!empty($filters['axe'])) {
            $qb->andWhere('a.id = :a')->setParameter('a', (int) $filters['axe']);
        }
        if (!empty($filters['q'])) {
            $qb->andWhere('LOWER(c.titre) LIKE :q OR LOWER(u.nom) LIKE :q OR LOWER(u.prenom) LIKE :q')
                ->setParameter('q', '%'.mb_strtolower(trim($filters['q'])).'%');
        }
        return new Paginator($qb->getQuery(), true);
    }

    public function add(Communication $communication, bool $flush = false): void
    {
        $this->getEntityManager()->persist($communication);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
