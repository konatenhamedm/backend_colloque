<?php

namespace App\Inscription\UI\Controller;

use App\Inscription\Application\UseCase\ListInscriptionsUseCase;
use App\Inscription\Application\UseCase\ListPaiementsEnAttenteUseCase;
use App\Inscription\Application\UseCase\ListPaiementsValidesUseCase;
use App\Inscription\Application\UseCase\ModifierNumeroDepotUseCase;
use App\Inscription\Application\UseCase\RejeterPaiementUseCase;
use App\Inscription\Application\UseCase\SupprimerInscriptionUseCase;
use App\Inscription\Application\UseCase\ValiderPaiementUseCase;
use App\Inscription\Application\UseCase\ValiderParticipationUseCase;
use App\Inscription\Domain\Repository\InscriptionRepositoryInterface;
use App\Security\Domain\Entity\Utilisateur;
use App\Shared\UI\Controller\ApiInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin')]
#[IsGranted(Utilisateur::ROLE_ADMIN)]
#[OA\Tag(name: 'Back-office : inscriptions & paiements')]
class AdminInscriptionController extends ApiInterface
{
    private const GROUPS = ['common:read', 'inscription:read', 'user:read', 'fichier:read'];

    #[Route('/inscriptions', name: 'api_admin_inscriptions', methods: ['GET'])]
    public function index(Request $request, ListInscriptionsUseCase $useCase): JsonResponse
    {
        $q = $request->query;
        $result = $useCase->execute(
            ['statut' => $q->get('statut'), 'type' => $q->get('type'), 'categorie' => $q->get('categorie'), 'q' => $q->get('q')],
            $q->getInt('page', 1), $q->getInt('limit', 20),
        );
        return $this->responseData($result['data'], $result['meta'], self::GROUPS);
    }

    #[Route('/inscriptions/{id}', name: 'api_admin_inscription_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id, InscriptionRepositoryInterface $repository): JsonResponse
    {
        $inscription = $repository->findById($id);
        return $inscription ? $this->responseData($inscription, [], self::GROUPS) : $this->errorResponse('Inscription introuvable.', 404);
    }

    /** Suppression définitive (inscription, paiements, fichiers, attestation et compte participant). */
    #[Route('/inscriptions/{id}', name: 'api_admin_inscription_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function supprimer(int $id, SupprimerInscriptionUseCase $useCase): JsonResponse
    {
        try {
            $email = $useCase->execute($id);
            return $this->setMessage('Inscription de '.$email.' supprimée.')->responseData(null);
        } catch (\Throwable $e) {
            return $this->fail($e);
        }
    }

    /** File d'attente de vérification : paiements EN_ATTENTE, les plus anciens d'abord. */
    #[Route('/paiements/en-attente', name: 'api_admin_paiements_attente', methods: ['GET'])]
    public function fileAttente(Request $request, ListPaiementsEnAttenteUseCase $useCase): JsonResponse
    {
        $result = $useCase->execute($request->query->getInt('page', 1), $request->query->getInt('limit', 20));
        return $this->responseData($result['data'], $result['meta'], ['common:read', 'paiement:read', 'fichier:read']);
    }

    /** Paiements validés (les plus récents d'abord) : suivi financier et correction du numéro de dépôt. */
    #[Route('/paiements/valides', name: 'api_admin_paiements_valides', methods: ['GET'])]
    public function valides(Request $request, ListPaiementsValidesUseCase $useCase): JsonResponse
    {
        $result = $useCase->execute($request->query->getInt('page', 1), $request->query->getInt('limit', 20));
        return $this->responseData($result['data'], $result['meta'], ['common:read', 'paiement:read', 'fichier:read']);
    }

    /** Corrige le numéro de dépôt d'un paiement : { "numeroDepot": "07 07 65 69 25" } (vide = non précisé). */
    #[Route('/paiements/{id}/numero-depot', name: 'api_admin_paiement_numero_depot', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    public function numeroDepot(int $id, Request $request, ModifierNumeroDepotUseCase $useCase): JsonResponse
    {
        try {
            $p = $useCase->execute($id, isset($this->payload($request)['numeroDepot']) ? (string) $this->payload($request)['numeroDepot'] : null);
            return $this->setMessage('Numéro de dépôt mis à jour.')->responseData($p, [], ['common:read', 'paiement:read', 'fichier:read']);
        } catch (\Throwable $e) {
            return $this->fail($e);
        }
    }

    #[Route('/paiements/{id}/valider', name: 'api_admin_paiement_valider', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function valider(int $id, ValiderPaiementUseCase $useCase): JsonResponse
    {
        try {
            $p = $useCase->execute($id, $this->admin());
            return $this->setMessage('Paiement validé, le participant a été notifié.')->responseData($p, [], ['common:read', 'paiement:read', 'fichier:read']);
        } catch (\Throwable $e) {
            return $this->fail($e);
        }
    }

    #[Route('/paiements/{id}/rejeter', name: 'api_admin_paiement_rejeter', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function rejeter(int $id, Request $request, RejeterPaiementUseCase $useCase): JsonResponse
    {
        try {
            $p = $useCase->execute($id, (string) ($this->payload($request)['motif'] ?? ''), $this->admin());
            return $this->setMessage('Paiement rejeté, le participant a été notifié.')->responseData($p, [], ['common:read', 'paiement:read', 'fichier:read']);
        } catch (\Throwable $e) {
            return $this->fail($e);
        }
    }

    /** Validation de la participation : { "ids": [1,2] } pour l'unitaire, sans "ids" pour la validation globale. */
    #[Route('/participations/valider', name: 'api_admin_participations_valider', methods: ['POST'])]
    public function validerParticipations(Request $request, ValiderParticipationUseCase $useCase): JsonResponse
    {
        try {
            $ids = $this->payload($request)['ids'] ?? null;
            return $this->responseData($useCase->execute(is_array($ids) ? $ids : null));
        } catch (\Throwable $e) {
            return $this->fail($e);
        }
    }

    private function admin(): Utilisateur
    {
        /** @var Utilisateur */
        return $this->getUser();
    }
}
