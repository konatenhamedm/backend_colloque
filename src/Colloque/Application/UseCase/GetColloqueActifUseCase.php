<?php

namespace App\Colloque\Application\UseCase;

use App\Colloque\Domain\Entity\Colloque;
use App\Colloque\Domain\Repository\ColloqueRepositoryInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class GetColloqueActifUseCase
{
    public function __construct(private readonly ColloqueRepositoryInterface $repository)
    {
    }

    public function execute(): Colloque
    {
        return $this->repository->findCurrent() ?? throw new NotFoundHttpException('Aucun colloque actif.');
    }
}
