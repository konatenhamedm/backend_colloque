<?php

namespace App\Inscription\UI\Controller;

use App\Attestation\Application\UseCase\GetMonAttestationUseCase;
use App\Inscription\Application\Service\InscriptionAccessChecker;
use App\Inscription\Application\Service\RecuGeneratorInterface;
use App\Inscription\Domain\Enum\StatutPaiement;
use App\Inscription\Application\UseCase\CorrigerPaiementUseCase;
use App\Inscription\Application\UseCase\GetMonInscriptionUseCase;
use App\Security\Domain\Entity\Utilisateur;
use App\Shared\UI\Controller\ApiInterface;
use App\Travaux\Application\UseCase\ListMesCommunicationsUseCase;
use App\Travaux\Application\UseCase\SoumettreCommunicationUseCase;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

/** Portail membre. Tant que le paiement n'est pas validé, tout sauf /inscription et /paiement/corriger renvoie 403. */
#[Route('/api/espace')]
#[OA\Tag(name: 'Espace participant')]
class EspaceParticipantController extends ApiInterface
{
    private const GROUPS = ['common:read', 'inscription:read', 'user:read', 'fichier:read'];

    #[Route('/inscription', name: 'api_espace_inscription', methods: ['GET'])]
    public function inscription(GetMonInscriptionUseCase $useCase): JsonResponse
    {
        return $this->responseData($useCase->execute($this->me()), [], self::GROUPS);
    }

    #[Route('/paiement/corriger', name: 'api_espace_paiement_corriger', methods: ['POST'])]
    public function corriger(Request $request, CorrigerPaiementUseCase $useCase, GetMonInscriptionUseCase $mon): JsonResponse
    {
        try {
            $useCase->execute($this->me(), $this->payload($request), $request->files->get('justificatif'));
            return $this->setMessage('Nouvelle preuve envoyée, elle sera vérifiée prochainement.')->responseData($mon->execute($this->me()), [], self::GROUPS);
        } catch (\Throwable $e) {
            return $this->fail($e);
        }
    }

    /** Programme, documents et calendrier : réservés aux inscrits validés. */
    #[Route('/ressources', name: 'api_espace_ressources', methods: ['GET'])]
    public function ressources(InscriptionAccessChecker $access): JsonResponse
    {
        try {
            $colloque = $access->requireActive($this->me())->getColloque();
            return $this->responseData([
                'calendrier' => $colloque->getCalendrier(),
                'documents' => $colloque->getDocuments(),
                'axes' => array_map(fn ($a) => ['id' => $a->getId(), 'code' => $a->getCode(), 'libelle' => $a->getLibelle()], $colloque->getAxes()->toArray()),
                'dateLimiteCommunication' => $colloque->getDateLimiteCommunication()->format('Y-m-d'),
            ]);
        } catch (\Throwable $e) {
            return $this->fail($e);
        }
    }

    /** Reçu de paiement (PDF) : réservé aux inscriptions dont le paiement est validé. */
    #[Route('/recu/telecharger', name: 'api_espace_recu_download', methods: ['GET'])]
    public function recu(InscriptionAccessChecker $access, RecuGeneratorInterface $recu): Response
    {
        try {
            $inscription = $access->requireActive($this->me());
            foreach ($inscription->getPaiements() as $p) {
                if ($p->getStatut() === StatutPaiement::VALIDE) {
                    $response = new Response($recu->generate($p), 200, ['Content-Type' => 'application/pdf']);
                    $response->headers->set('Content-Disposition', $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, 'recu-paiement.pdf'));
                    return $response;
                }
            }
            return $this->errorResponse('Aucun paiement validé.', 404);
        } catch (\Throwable $e) {
            return $this->fail($e);
        }
    }

    #[Route('/communications', name: 'api_espace_communications', methods: ['GET'])]
    public function communications(ListMesCommunicationsUseCase $useCase): JsonResponse
    {
        try {
            return $this->responseData($useCase->execute($this->me()), [], ['common:read', 'communication:read', 'fichier:read']);
        } catch (\Throwable $e) {
            return $this->fail($e);
        }
    }

    #[Route('/communications', name: 'api_espace_communications_create', methods: ['POST'])]
    public function deposer(Request $request, SoumettreCommunicationUseCase $useCase): JsonResponse
    {
        try {
            $c = $useCase->execute($this->me(), $this->payload($request), $request->files->get('fichier'));
            return $this->setStatusCode(201)->setMessage('Communication soumise.')->responseData($c, [], ['common:read', 'communication:read', 'fichier:read']);
        } catch (\Throwable $e) {
            return $this->fail($e);
        }
    }

    #[Route('/communications/{id}', name: 'api_espace_communications_update', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function corrigerCommunication(int $id, Request $request, SoumettreCommunicationUseCase $useCase): JsonResponse
    {
        try {
            $c = $useCase->execute($this->me(), $this->payload($request), $request->files->get('fichier'), $id);
            return $this->setMessage('Communication corrigée et renvoyée au comité.')->responseData($c, [], ['common:read', 'communication:read', 'fichier:read']);
        } catch (\Throwable $e) {
            return $this->fail($e);
        }
    }

    #[Route('/attestation', name: 'api_espace_attestation', methods: ['GET'])]
    public function attestation(GetMonAttestationUseCase $useCase): JsonResponse
    {
        try {
            return $this->responseData($useCase->execute($this->me()), [], ['attestation:read']);
        } catch (\Throwable $e) {
            return $this->fail($e);
        }
    }

    #[Route('/attestation/telecharger', name: 'api_espace_attestation_download', methods: ['GET'])]
    public function telecharger(GetMonAttestationUseCase $useCase): BinaryFileResponse|JsonResponse
    {
        try {
            $a = $useCase->execute($this->me());
            $response = new BinaryFileResponse($a->getFichier()->getAbsolutePath());
            $response->headers->set('Content-Type', 'application/pdf');
            $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, 'attestation-'.$a->getNumero().'.pdf');
            return $response;
        } catch (\Throwable $e) {
            return $this->fail($e);
        }
    }

    private function me(): Utilisateur
    {
        /** @var Utilisateur */
        return $this->getUser();
    }
}
