<?php

namespace App\Inscription\UI\Controller;

use App\Inscription\Application\UseCase\SoumettreInscriptionUseCase;
use App\Security\Infrastructure\Security\JwtService;
use App\Shared\UI\Controller\ApiInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/auth')]
#[OA\Tag(name: 'Inscription')]
class InscriptionPublicController extends ApiInterface
{
    /**
     * Inscription directe : formulaire d'inscription (multipart/form-data) : crée le compte, l'inscription et le paiement à vérifier.
     * Le compte est créé, le participant est connecté (accès restreint jusqu'à validation du paiement).
     */
    #[Route('/inscription', name: 'api_auth_inscription', methods: ['POST'])]
    #[OA\RequestBody(content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(properties: [
        new OA\Property(property: 'nom', type: 'string'), new OA\Property(property: 'prenom', type: 'string'),
        new OA\Property(property: 'email', type: 'string'), new OA\Property(property: 'password', type: 'string'), new OA\Property(property: 'telephone', type: 'string'),
        new OA\Property(property: 'institution', type: 'string'), new OA\Property(property: 'pays', type: 'string'),
        new OA\Property(property: 'categorieId', type: 'integer'), new OA\Property(property: 'typeParticipation', type: 'string', enum: ['AUDITEUR', 'COMMUNICANT']),
        new OA\Property(property: 'canal', type: 'string'), new OA\Property(property: 'reference', type: 'string'),
        new OA\Property(property: 'justificatif', type: 'string', format: 'binary'),
    ])))]
    public function inscription(Request $request, SoumettreInscriptionUseCase $useCase, JwtService $jwt, RateLimiterFactoryInterface $inscriptionLimiter): JsonResponse
    {
        if (!$inscriptionLimiter->create($request->getClientIp() ?? 'anon')->consume()->isAccepted()) {
            return $this->errorResponse('Trop de tentatives, réessayez dans quelques minutes.', 429);
        }
        try {
            $result = $useCase->execute($this->payload($request), $request->files->get('justificatif'));
            $this->setStatusCode(201)->setMessage('Inscription enregistrée. Un e-mail de confirmation vous a été envoyé.');
            return $this->responseData([
                'token' => $jwt->generate(['sub' => $result['utilisateur']->getUserIdentifier()]),
                'user' => json_decode($this->serializer->serialize($result['utilisateur'], 'json', ['groups' => ['user:read']]), true),
                'inscription' => json_decode($this->serializer->serialize($result['inscription'], 'json', ['groups' => ['common:read', 'inscription:read', 'user:read', 'fichier:read']]), true),
            ]);
        } catch (\Throwable $e) {
            return $this->fail($e);
        }
    }
}
