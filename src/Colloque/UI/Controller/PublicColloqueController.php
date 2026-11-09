<?php

namespace App\Colloque\UI\Controller;

use App\Colloque\Application\UseCase\GetColloqueActifUseCase;
use App\Shared\UI\Controller\ApiInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/public')]
#[OA\Tag(name: 'Public')]
class PublicColloqueController extends ApiInterface
{
    /** Alimente la landing page et le formulaire d'inscription (axes, tarifs, canaux de paiement). */
    #[Route('/colloque', name: 'api_public_colloque', methods: ['GET'])]
    public function colloque(GetColloqueActifUseCase $useCase): JsonResponse
    {
        try {
            return $this->responseData($useCase->execute(), [], ['colloque:read']);
        } catch (\Throwable $e) {
            return $this->fail($e);
        }
    }
}
