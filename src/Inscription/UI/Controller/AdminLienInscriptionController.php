<?php

namespace App\Inscription\UI\Controller;

use App\Inscription\Application\UseCase\DemarrerInscriptionUseCase;
use App\Inscription\Domain\Entity\InscriptionToken;
use App\Inscription\Domain\Repository\InscriptionTokenRepositoryInterface;
use App\Security\Domain\Entity\Utilisateur;
use App\Security\Domain\Repository\UtilisateurRepositoryInterface;
use App\Shared\UI\Controller\ApiInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Liens d'inscription en cours de validité (1 h). Permet à l'équipe de retrouver un lien quand l'e-mail
 * n'est pas arrivé, ou d'en générer un nouveau pour une adresse donnée.
 */
#[Route('/api/admin/liens-inscription')]
#[IsGranted(Utilisateur::ROLE_ADMIN)]
#[OA\Tag(name: 'Back-office : liens d\'inscription')]
class AdminLienInscriptionController extends ApiInterface
{
    public function __construct(private readonly string $frontUrl)
    {
    }

    #[Route('', name: 'api_admin_liens_index', methods: ['GET'])]
    public function index(InscriptionTokenRepositoryInterface $tokens): JsonResponse
    {
        $tokens->purgerExpires();
        return $this->responseData(array_map($this->vue(...), $tokens->findActifs()));
    }

    /** { "email": "...", "envoyer": true } → nouveau lien (les précédents de la même adresse restent valides jusqu'à expiration). */
    #[Route('', name: 'api_admin_liens_create', methods: ['POST'])]
    public function create(Request $request, DemarrerInscriptionUseCase $useCase, UtilisateurRepositoryInterface $users): JsonResponse
    {
        $data = $this->payload($request);
        $email = mb_strtolower(trim((string) ($data['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->errorResponse('Adresse e-mail invalide.', 422);
        }
        if ($users->findByEmail($email) !== null) {
            return $this->errorResponse('Cette adresse a déjà un compte : le participant peut se connecter.', 422);
        }
        $erreur = null;
        $token = $useCase->emettre($email, (bool) ($data['envoyer'] ?? false), $erreur);
        return $this->setMessage('Lien généré.')->responseData($this->vue($token) + ['erreurEnvoi' => $erreur]);
    }

    private function vue(InscriptionToken $t): array
    {
        return [
            'email' => $t->getEmail(),
            'lien' => rtrim($this->frontUrl, '/').'/inscription?token='.$t->getPlainToken(),
            'creeLe' => $t->getCreatedAt()->format(\DATE_ATOM),
            'expireLe' => $t->getExpiresAt()->format(\DATE_ATOM),
        ];
    }
}
