<?php

namespace App\Shared\Domain\Repository;

use App\Shared\Domain\Entity\Fichier;

interface FichierRepositoryInterface
{
    public function findByUuid(string $uuid): ?Fichier;
    public function add(Fichier $fichier, bool $flush = false): void;
    public function remove(Fichier $fichier, bool $flush = false): void;
}
