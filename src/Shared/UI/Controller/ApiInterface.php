<?php

namespace App\Shared\UI\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Base de tous les contrôleurs d'API. Enveloppe de réponse homogène :
 * { code, message, data, meta?, errors[] }
 */
abstract class ApiInterface extends AbstractController
{
    protected int $statusCode = 200;
    protected string $message = 'Opération effectuée avec succès';

    public function __construct(protected readonly SerializerInterface $serializer)
    {
    }

    protected function setStatusCode(int $statusCode): static
    {
        $this->statusCode = $statusCode;
        return $this;
    }

    protected function setMessage(string $message): static
    {
        $this->message = $message;
        return $this;
    }

    /** @param string[] $groups groupes de sérialisation */
    public function responseData(mixed $data = [], array $meta = [], array $groups = []): JsonResponse
    {
        $serialized = is_object($data) || (is_array($data) && $data !== [] && is_object(reset($data)))
            ? json_decode($this->serializer->serialize($data, 'json', ['groups' => $groups ?: ['common:read']]), true)
            : $data;

        $payload = ['code' => $this->statusCode, 'message' => $this->message, 'data' => $serialized, 'errors' => []];
        if ($meta) {
            $payload['meta'] = $meta;
        }
        return new JsonResponse($payload, $this->statusCode);
    }

    public function errorResponse(string $message, int $statusCode = 400, array $errors = []): JsonResponse
    {
        return new JsonResponse(['code' => $statusCode, 'message' => $message, 'errors' => $errors ?: [$message]], $statusCode);
    }

    /** Corps JSON ou multipart, au choix de l'appelant. */
    protected function payload(Request $request): array
    {
        if (str_contains((string) $request->headers->get('Content-Type'), 'json')) {
            return json_decode($request->getContent(), true) ?? [];
        }
        return $request->request->all();
    }

    protected function fail(\Throwable $e): JsonResponse
    {
        if ($e instanceof HttpExceptionInterface) {
            return $this->errorResponse($e->getMessage(), $e->getStatusCode());
        }
        // Erreurs métier attendues (règles du domaine) : message exposable.
        if ($e instanceof \DomainException || $e instanceof \InvalidArgumentException) {
            return $this->errorResponse($e->getMessage(), 422);
        }
        // Tout le reste est un bug : on ne fuit pas les détails internes.
        return $this->errorResponse('Une erreur interne est survenue.', 500);
    }
}
