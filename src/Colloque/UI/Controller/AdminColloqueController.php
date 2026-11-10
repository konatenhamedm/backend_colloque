<?php

namespace App\Colloque\UI\Controller;

use App\Colloque\Application\UseCase\DeleteColloqueItemUseCase;
use App\Colloque\Application\UseCase\GetColloqueActifUseCase;
use App\Colloque\Application\UseCase\SaveColloqueItemUseCase;
use App\Colloque\Application\UseCase\UpdateColloqueUseCase;
use App\Security\Domain\Entity\Utilisateur;
use App\Shared\UI\Controller\ApiInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Écran « Paramètres » du back-office : configuration du colloque, animateurs, signatures, logos. */
#[Route('/api/admin/colloque')]
#[IsGranted(Utilisateur::ROLE_ADMIN)]
#[OA\Tag(name: 'Back-office : paramètres du colloque')]
class AdminColloqueController extends ApiInterface
{
    private const GROUPS = ['common:read', 'colloque:read', 'item:read'];

    #[Route('', name: 'api_admin_colloque_show', methods: ['GET'])]
    public function show(GetColloqueActifUseCase $useCase): JsonResponse
    {
        try {
            return $this->responseData($this->payloadOf($useCase->execute()));
        } catch (\Throwable $e) {
            return $this->fail($e);
        }
    }

    #[Route('', name: 'api_admin_colloque_update', methods: ['PUT', 'PATCH'])]
    public function update(Request $request, UpdateColloqueUseCase $useCase): JsonResponse
    {
        try {
            return $this->setMessage('Configuration enregistrée.')->responseData($this->payloadOf($useCase->execute($this->payload($request))));
        } catch (\Throwable $e) {
            return $this->fail($e);
        }
    }

    /** Création (multipart : type, nom, titre, role, details, ordre, image). */
    #[Route('/items', name: 'api_admin_colloque_item_create', methods: ['POST'])]
    public function createItem(Request $request, SaveColloqueItemUseCase $useCase): JsonResponse
    {
        try {
            $item = $useCase->execute(null, $this->payload($request), $request->files->get('image'));
            return $this->setStatusCode(201)->setMessage('Élément ajouté.')->responseData($item, [], ['common:read', 'item:read']);
        } catch (\Throwable $e) {
            return $this->fail($e);
        }
    }

    /** Modification en POST (PHP ne lit pas le multipart sur PUT). removeImage=1 retire l'image. */
    #[Route('/items/{id}', name: 'api_admin_colloque_item_update', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function updateItem(int $id, Request $request, SaveColloqueItemUseCase $useCase): JsonResponse
    {
        try {
            $d = $this->payload($request);
            $item = $useCase->execute($id, $d, $request->files->get('image'), filter_var($d['removeImage'] ?? false, FILTER_VALIDATE_BOOLEAN));
            return $this->setMessage('Élément enregistré.')->responseData($item, [], ['common:read', 'item:read']);
        } catch (\Throwable $e) {
            return $this->fail($e);
        }
    }

    #[Route('/items/{id}', name: 'api_admin_colloque_item_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function deleteItem(int $id, DeleteColloqueItemUseCase $useCase): JsonResponse
    {
        try {
            $useCase->execute($id);
            return $this->setMessage('Élément supprimé.')->responseData(null);
        } catch (\Throwable $e) {
            return $this->fail($e);
        }
    }

    /** @return array{colloque: mixed, items: mixed} */
    private function payloadOf(\App\Colloque\Domain\Entity\Colloque $c): array
    {
        $decode = fn (mixed $v, array $g) => json_decode($this->serializer->serialize($v, 'json', ['groups' => $g]), true);
        return [
            'colloque' => $decode($c, ['common:read', 'colloque:read']),
            'items' => $decode($c->getItems()->toArray(), ['common:read', 'item:read']),
        ];
    }
}
