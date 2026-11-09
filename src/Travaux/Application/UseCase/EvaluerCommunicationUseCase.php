<?php

namespace App\Travaux\Application\UseCase;

use App\Travaux\Domain\Entity\Communication;
use App\Travaux\Domain\Enum\StatutCommunication;
use App\Travaux\Domain\Repository\CommunicationRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EvaluerCommunicationUseCase
{
    public function __construct(
        private readonly CommunicationRepositoryInterface $communications,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function execute(int $id, array $data): Communication
    {
        $communication = $this->communications->findById($id) ?? throw new NotFoundHttpException('Communication introuvable.');
        $decision = StatutCommunication::tryFrom((string) ($data['decision'] ?? '')) ?? throw new BadRequestHttpException('Décision invalide.');
        $communication->evaluer($decision, $data['commentaire'] ?? null);
        $this->em->flush();

        return $communication;
    }
}
