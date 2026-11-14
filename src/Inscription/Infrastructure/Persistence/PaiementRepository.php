<?php

namespace App\Inscription\Infrastructure\Persistence;

use App\Inscription\Domain\Entity\Paiement;
use App\Inscription\Domain\Enum\CanalPaiement;
use App\Inscription\Domain\Enum\StatutPaiement;
use App\Inscription\Domain\Repository\PaiementRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Paiement> */
class PaiementRepository extends ServiceEntityRepository implements PaiementRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Paiement::class);
    }

    public function findById(int $id): ?Paiement
    {
        return $this->find($id);
    }

    public function existsReference(CanalPaiement $canal, string $reference): bool
    {
        if (trim($reference) === '') {
            return false;
        }
        return $this->findOneBy(['canal' => $canal, 'reference' => mb_strtoupper(trim($reference))]) !== null;
    }

    public function searchEnAttente(int $page, int $limit): Paginator
    {
        $qb = $this->createQueryBuilder('p')
            ->join('p.inscription', 'i')->addSelect('i')
            ->join('i.utilisateur', 'u')->addSelect('u')
            ->join('i.categorie', 'c')->addSelect('c')
            ->leftJoin('p.justificatif', 'f')->addSelect('f')
            ->where('p.statut = :s')->setParameter('s', StatutPaiement::EN_ATTENTE)
            ->orderBy('p.id', 'ASC') // les plus anciens d'abord : file d'attente
            ->setFirstResult(($page - 1) * $limit)->setMaxResults($limit);
        return new Paginator($qb->getQuery(), true);
    }

    public function totalsByCanal(): array
    {
        $rows = $this->createQueryBuilder('p')->select('p.canal AS canal, COUNT(p.id) AS total, SUM(p.montant) AS montant')
            ->where('p.statut = :s')->setParameter('s', StatutPaiement::VALIDE)
            ->groupBy('p.canal')->orderBy('montant', 'DESC')->getQuery()->getArrayResult();
        return array_map(fn ($r) => ['canal' => $r['canal']->value, 'total' => (int) $r['total'], 'montant' => (int) $r['montant']], $rows);
    }

    /** Encaissements validés par canal ET par numéro de dépôt (numéro vide = non précisé). */
    public function totalsByCanalNumero(): array
    {
        $rows = $this->createQueryBuilder('p')->select('p.canal AS canal, p.numeroDepot AS numero, COUNT(p.id) AS total, SUM(p.montant) AS montant')
            ->where('p.statut = :s')->setParameter('s', StatutPaiement::VALIDE)
            ->groupBy('p.canal, p.numeroDepot')->orderBy('montant', 'DESC')->getQuery()->getArrayResult();
        return array_map(fn ($r) => ['canal' => $r['canal']->value, 'numero' => $r['numero'], 'total' => (int) $r['total'], 'montant' => (int) $r['montant']], $rows);
    }

    public function findAllValides(): array
    {
        return $this->createQueryBuilder('p')->join('p.inscription', 'i')->addSelect('i')->join('i.utilisateur', 'u')->addSelect('u')
            ->where('p.statut = :s')->setParameter('s', StatutPaiement::VALIDE)->orderBy('p.traiteAt', 'ASC')->getQuery()->getResult();
    }

    public function totalEnAttente(): int
    {
        return (int) $this->createQueryBuilder('p')->select('COALESCE(SUM(p.montant), 0)')
            ->where('p.statut = :s')->setParameter('s', StatutPaiement::EN_ATTENTE)->getQuery()->getSingleScalarResult();
    }

    public function add(Paiement $paiement, bool $flush = false): void
    {
        $this->getEntityManager()->persist($paiement);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
