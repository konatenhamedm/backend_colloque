<?php

namespace App\Colloque\Domain\Repository;

use App\Colloque\Domain\Entity\Colloque;

interface ColloqueRepositoryInterface
{
    public function findById(int $id): ?Colloque;
    public function findCurrent(): ?Colloque;
}
