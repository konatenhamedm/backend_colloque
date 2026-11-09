<?php

namespace App\Travaux\UI\Controller;

use App\Security\Domain\Entity\Utilisateur;
use App\Shared\UI\Controller\ApiInterface;
use App\Travaux\Application\UseCase\EvaluerCommunicationUseCase;
use App\Travaux\Application\UseCase\ListCommunicationsUseCase;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Accessible au comité scientifique (ROLE_COMITE) et aux administrateurs. */
#[Route('/api/admin/communications')]
#[IsGranted(Utilisateur::ROLE_COMITE)]
#[OA\Tag(name: 'Back-office : travaux')]
class AdminTravauxController extends ApiInterface
{
    private const GROUPS = ['common:read', 'communication:read', 'fichier:read'];

    #[Route('', name: 'api_admin_communications', methods: ['GET'])]
    public function index(Request $request, ListCommunicationsUseCase $useCase): JsonResponse
    {
        $q = $request->query;
        $r = $useCase->execute(['statut' => $q->get('statut'), 'axe' => $q->get('axe'), 'q' => $q->get('q')], $q->getInt('page', 1), $q->getInt('limit', 20));
        return $this->responseData($r['data'], $r['meta'], self::GROUPS);
    }

    #[Route('/{id}/evaluer', name: 'api_admin_communication_evaluer', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function evaluer(int $id, Request $request, EvaluerCommunicationUseCase $useCase): JsonResponse
    {
        try {
            return $this->setMessage('Évaluation enregistrée.')->responseData($useCase->execute($id, $this->payload($request)), [], self::GROUPS);
        } catch (\Throwable $e) {
            return $this->fail($e);
        }
    }
}
