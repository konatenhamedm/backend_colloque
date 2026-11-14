<?php

namespace App\Inscription\Domain\Repository;

use App\Inscription\Domain\Entity\Paiement;
use App\Inscription\Domain\Enum\CanalPaiement;
use Doctrine\ORM\Tools\Pagination\Paginator;

interface PaiementRepositoryInterface
{
    public function findById(int $id): ?Paiement;
    public function existsReference(CanalPaiement $canal, string $reference): bool;
    public function searchEnAttente(int $page, int $limit): Paginator;
    /** Paiements validés, les plus récents d'abord. */
    public function searchValides(int $page, int $limit): Paginator;
    /** @return array<int,array{canal:string,total:int,montant:int}> paiements VALIDES regroupés par canal */
    public function totalsByCanal(): array;
    /** @return array<int,array{canal:string,numero:?string,total:int,montant:int}> */
    public function totalsByCanalNumero(): array;
    /** @return Paiement[] */
    public function findAllValides(): array;
    /** Somme des paiements en attente de vérification (FCFA). */
    public function totalEnAttente(): int;
    public function add(Paiement $paiement, bool $flush = false): void;
}
