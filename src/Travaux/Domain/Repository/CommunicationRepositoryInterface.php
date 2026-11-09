<?php

namespace App\Travaux\Domain\Repository;

use App\Inscription\Domain\Entity\Inscription;
use App\Travaux\Domain\Entity\Communication;
use Doctrine\ORM\Tools\Pagination\Paginator;

interface CommunicationRepositoryInterface
{
    public function findById(int $id): ?Communication;
    /** @return Communication[] */
    public function findByInscription(Inscription $inscription): array;
    public function hasValidated(Inscription $inscription): bool;
    /** @param array{statut?:string,axe?:int,q?:string} $filters */
    public function search(array $filters, int $page, int $limit): Paginator;
    public function add(Communication $communication, bool $flush = false): void;
}
