<?php

namespace App\Inscription\Domain\Repository;

use App\Colloque\Domain\Entity\Colloque;
use App\Inscription\Domain\Entity\Inscription;
use App\Security\Domain\Entity\Utilisateur;
use Doctrine\ORM\Tools\Pagination\Paginator;

interface InscriptionRepositoryInterface
{
    public function findById(int $id): ?Inscription;
    public function findByUtilisateur(Utilisateur $user): ?Inscription;
    /** @param array{statut?:string,q?:string,type?:string,categorie?:int} $filters */
    public function search(array $filters, int $page, int $limit): Paginator;
    /** @param array{statut?:string,q?:string,type?:string,categorie?:int} $filters  @return Inscription[] */
    public function searchAll(array $filters): array;
    /** @param int[] $ids @return Inscription[] */
    public function findByIds(array $ids): array;
    /** @return Inscription[] */
    public function findByStatut(string $statut): array;
    /** @return array<string,int> statut => nombre */
    public function countByStatut(Colloque $colloque): array;
    /** @return array<int,array{categorie:string,total:int}> */
    public function countByCategorie(Colloque $colloque): array;
    /** @return array<int,array{jour:string,total:int}> */
    public function countByJour(Colloque $colloque, int $days = 30): array;
    public function add(Inscription $inscription, bool $flush = false): void;
}
