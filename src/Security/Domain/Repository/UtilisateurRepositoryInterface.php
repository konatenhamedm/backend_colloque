<?php

namespace App\Security\Domain\Repository;

use App\Security\Domain\Entity\Utilisateur;

interface UtilisateurRepositoryInterface
{
    public function findById(int $id): ?Utilisateur;
    public function findByEmail(string $email): ?Utilisateur;
    public function add(Utilisateur $user, bool $flush = false): void;
}
