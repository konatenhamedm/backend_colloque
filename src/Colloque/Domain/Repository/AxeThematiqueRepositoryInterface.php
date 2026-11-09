<?php

namespace App\Colloque\Domain\Repository;

use App\Colloque\Domain\Entity\AxeThematique;

interface AxeThematiqueRepositoryInterface
{
    public function findById(int $id): ?AxeThematique;

}
