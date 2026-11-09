<?php

namespace App\Security\UI\Controller;

use App\Inscription\Application\UseCase\GetMonInscriptionUseCase;
use App\Security\Application\UseCase\ChangePasswordUseCase;
use App\Security\Application\UseCase\LoginUseCase;
use App\Security\Domain\Entity\Utilisateur;
use App\Shared\UI\Controller\ApiInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/auth')]
#[OA\Tag(name: 'Authentification')]
class AuthController extends ApiInterface
{
    #[Route('/login', name: 'api_auth_login', methods: ['POST'])]
    public function login(Request $request, LoginUseCase $useCase, GetMonInscriptionUseCase $monInscription): JsonResponse
    {
        try {
            $result = $useCase->execute($this->payload($request));
            return $this->responseData([
                'token' => $result['token'],
                'user' => json_decode($this->serializer->serialize($result['user'], 'json', ['groups' => ['user:read']]), true),
                'inscription' => $this->serializeInscription($monInscription->execute($result['user'])),
            ]);
        } catch (\Throwable $e) {
            return $this->fail($e);
        }
    }

    #[Route('/me', name: 'api_auth_me', methods: ['GET'])]
    public function me(GetMonInscriptionUseCase $monInscription): JsonResponse
    {
        /** @var Utilisateur $user */
        $user = $this->getUser();
        return $this->responseData([
            'user' => json_decode($this->serializer->serialize($user, 'json', ['groups' => ['user:read']]), true),
            'roles' => $user->getRoles(),
            'inscription' => $this->serializeInscription($monInscription->execute($user)),
        ]);
    }

    #[Route('/change-password', name: 'api_auth_change_password', methods: ['POST'])]
    public function changePassword(Request $request, ChangePasswordUseCase $useCase): JsonResponse
    {
        try {
            $useCase->execute($this->getUser(), $this->payload($request));
            return $this->setMessage('Mot de passe modifié.')->responseData(null);
        } catch (\Throwable $e) {
            return $this->fail($e);
        }
    }

    private function serializeInscription(mixed $inscription): mixed
    {
        return $inscription === null ? null : json_decode($this->serializer->serialize($inscription, 'json', ['groups' => ['inscription:read']]), true);
    }
}
