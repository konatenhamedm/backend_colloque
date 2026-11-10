<?php

namespace App\Inscription\Infrastructure\Persistence;

use App\Inscription\Domain\Entity\InscriptionToken;
use App\Inscription\Domain\Repository\InscriptionTokenRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<InscriptionToken> */
class InscriptionTokenRepository extends ServiceEntityRepository implements InscriptionTokenRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InscriptionToken::class);
    }

    public function findByPlainToken(string $plain): ?InscriptionToken
    {
        return $this->findOneBy(['tokenHash' => InscriptionToken::hash($plain)]);
    }

    public function findActifs(): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.usedAt IS NULL')->andWhere('t.expiresAt > :now')->andWhere('t.plainToken IS NOT NULL')
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()->getResult();
    }

    public function purgerExpires(): int
    {
        return (int) $this->createQueryBuilder('t')->delete()
            ->where('t.expiresAt <= :now')->orWhere('t.usedAt IS NOT NULL')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()->execute();
    }

    public function add(InscriptionToken $token, bool $flush = false): void
    {
        $this->getEntityManager()->persist($token);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
