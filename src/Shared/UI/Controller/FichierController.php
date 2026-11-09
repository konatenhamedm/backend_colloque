<?php

namespace App\Shared\UI\Controller;

use App\Security\Domain\Entity\Utilisateur;
use App\Shared\Application\Service\FichierAccessPolicy;
use App\Shared\Domain\Repository\FichierRepositoryInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

/** Les fichiers privés ne sont jamais dans public/ : ils sont servis ici, après contrôle des droits. */
#[Route('/api/fichiers')]
#[OA\Tag(name: 'Fichiers')]
class FichierController extends ApiInterface
{
    #[Route('/{uuid}', name: 'api_fichier_show', requirements: ['uuid' => '[0-9a-fA-F\-]{36}'], methods: ['GET'])]
    public function show(string $uuid, FichierRepositoryInterface $repository, FichierAccessPolicy $policy): BinaryFileResponse|JsonResponse
    {
        /** @var Utilisateur $user */
        $user = $this->getUser();
        $fichier = $repository->findByUuid($uuid);
        // 404 aussi quand l'accès est refusé : on ne révèle pas l'existence d'un fichier.
        if ($fichier === null || !$policy->canRead($user, $fichier) || !is_file($fichier->getAbsolutePath())) {
            return $this->errorResponse('Fichier introuvable.', 404);
        }
        $response = new BinaryFileResponse($fichier->getAbsolutePath());
        $response->headers->set('Content-Type', $fichier->getMimeType() ?? 'application/octet-stream');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $fichier->getOriginalName() ?? 'fichier');
        return $response;
    }
}
