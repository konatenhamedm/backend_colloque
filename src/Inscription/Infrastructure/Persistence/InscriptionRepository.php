<?php

namespace App\Inscription\Infrastructure\Persistence;

use App\Colloque\Domain\Entity\Colloque;
use App\Inscription\Domain\Entity\Inscription;
use App\Inscription\Domain\Repository\InscriptionRepositoryInterface;
use App\Security\Domain\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Inscription> */
class InscriptionRepository extends ServiceEntityRepository implements InscriptionRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Inscription::class);
    }

    public function findById(int $id): ?Inscription
    {
        return $this->find($id);
    }

    public function findByUtilisateur(Utilisateur $user): ?Inscription
    {
        return $this->findOneBy(['utilisateur' => $user], ['id' => 'DESC']);
    }

    public function search(array $filters, int $page, int $limit): Paginator
    {
        $qb = $this->filtered($filters)->orderBy('i.id', 'DESC')
            ->setFirstResult(($page - 1) * $limit)->setMaxResults($limit);
        return new Paginator($qb->getQuery(), true);
    }

    public function searchAll(array $filters): array
    {
        return $this->filtered($filters)->orderBy('u.nom', 'ASC')->addOrderBy('u.prenom', 'ASC')->getQuery()->getResult();
    }

    public function findByIds(array $ids): array
    {
        return $ids === [] ? [] : $this->filtered([])->andWhere('i.id IN (:ids)')->setParameter('ids', $ids)->getQuery()->getResult();
    }

    public function findByStatut(string $statut): array
    {
        return $this->filtered(['statut' => $statut])->getQuery()->getResult();
    }

    public function countByStatut(Colloque $colloque): array
    {
        $rows = $this->createQueryBuilder('i')->select('i.statut AS statut, COUNT(i.id) AS total')
            ->where('i.colloque = :c')->setParameter('c', $colloque)->groupBy('i.statut')->getQuery()->getArrayResult();
        $out = [];
        foreach ($rows as $r) {
            $out[$r['statut']->value] = (int) $r['total'];
        }
        return $out;
    }

    public function countByCategorie(Colloque $colloque): array
    {
        return array_map(fn ($r) => ['categorie' => $r['categorie'], 'total' => (int) $r['total']],
            $this->createQueryBuilder('i')->select('c.libelle AS categorie, COUNT(i.id) AS total')
                ->join('i.categorie', 'c')->where('i.colloque = :c')->setParameter('c', $colloque)
                ->groupBy('c.id, c.libelle')->orderBy('c.libelle')->getQuery()->getArrayResult());
    }

    public function countByJour(Colloque $colloque, int $days = 30): array
    {
        $sql = "SELECT to_char(created_at, 'YYYY-MM-DD') AS jour, COUNT(*) AS total FROM inscription
                WHERE colloque_id = :c AND created_at >= NOW() - (:d || ' days')::interval GROUP BY 1 ORDER BY 1";
        return array_map(fn ($r) => ['jour' => $r['jour'], 'total' => (int) $r['total']],
            $this->getEntityManager()->getConnection()->executeQuery($sql, ['c' => $colloque->getId(), 'd' => $days])->fetchAllAssociative());
    }

    public function add(Inscription $inscription, bool $flush = false): void
    {
        $this->getEntityManager()->persist($inscription);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    private function filtered(array $f): QueryBuilder
    {
        $qb = $this->createQueryBuilder('i')
            ->join('i.utilisateur', 'u')->addSelect('u')
            ->join('i.categorie', 'cat')->addSelect('cat')
            ->leftJoin('i.paiements', 'p')->addSelect('p');
        if (!empty($f['statut'])) {
            $qb->andWhere('i.statut = :statut')->setParameter('statut', $f['statut']);
        }
        if (!empty($f['type'])) {
            $qb->andWhere('i.typeParticipation = :type')->setParameter('type', $f['type']);
        }
        if (!empty($f['categorie'])) {
            $qb->andWhere('cat.id = :cat')->setParameter('cat', (int) $f['categorie']);
        }
        if (!empty($f['q'])) {
            $qb->andWhere('LOWER(u.nom) LIKE :q OR LOWER(u.prenom) LIKE :q OR LOWER(u.email) LIKE :q OR LOWER(i.institution) LIKE :q')
                ->setParameter('q', '%'.mb_strtolower(trim($f['q'])).'%');
        }
        return $qb;
    }
}
