<?php

namespace App\Colloque\Domain\Repository;

use App\Colloque\Domain\Entity\CategorieParticipant;

interface CategorieParticipantRepositoryInterface
{
    public function findById(int $id): ?CategorieParticipant;

}
