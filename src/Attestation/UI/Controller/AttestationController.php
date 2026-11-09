<?php

namespace App\Attestation\UI\Controller;

use App\Attestation\Application\UseCase\ApercuAttestationUseCase;
use App\Attestation\Application\UseCase\EmettreAttestationsUseCase;
use App\Attestation\Infrastructure\Pdf\DompdfAttestationGenerator;
use App\Attestation\Application\UseCase\VerifierAttestationUseCase;
use App\Security\Domain\Entity\Utilisateur;
use App\Shared\UI\Controller\ApiInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[OA\Tag(name: 'Attestations')]
class AttestationController extends ApiInterface
{
    /** Route publique ciblée par le QR code du diplôme. */
    #[Route('/api/public/attestations/{code}', name: 'api_public_attestation_verify', requirements: ['code' => '[A-Za-z0-9_\-]{8,40}'], methods: ['GET'])]
    public function verifier(string $code, VerifierAttestationUseCase $useCase): JsonResponse
    {
        try {
            return $this->responseData($useCase->execute($code));
        } catch (\Throwable $e) {
            return $this->fail($e);
        }
    }

    /** Émission unitaire { "ids": [..] } ou globale (sans "ids"). */
    #[Route('/api/admin/attestations/emettre', name: 'api_admin_attestations_emettre', methods: ['POST'])]
    #[IsGranted(Utilisateur::ROLE_ADMIN)]
    public function emettre(Request $request, EmettreAttestationsUseCase $useCase): JsonResponse
    {
        try {
            $ids = $this->payload($request)['ids'] ?? null;
            return $this->responseData($useCase->execute(is_array($ids) ? $ids : null, $this->template($this->payload($request)['template'] ?? null)));
        } catch (\Throwable $e) {
            return $this->fail($e);
        }
    }

    /** Modèles disponibles + modèle par défaut (ATTESTATION_TEMPLATE). */
    #[Route('/api/admin/attestations/modeles', name: 'api_admin_attestations_modeles', methods: ['GET'])]
    #[IsGranted(Utilisateur::ROLE_ADMIN)]
    public function modeles(DompdfAttestationGenerator $generator): JsonResponse
    {
        return $this->responseData([
            'defaut' => $generator->getDefaultTemplate(),
            'modeles' => [['code' => 'classique', 'label' => 'Classique'], ['code' => 'moderne', 'label' => 'Moderne']],
        ]);
    }

    /** Aperçu PDF sans rien enregistrer. ?inscription=ID (sinon exemple fictif) &template=classique|moderne */
    #[Route('/api/admin/attestations/apercu', name: 'api_admin_attestations_apercu', methods: ['GET'])]
    #[IsGranted(Utilisateur::ROLE_ADMIN)]
    public function apercu(Request $request, ApercuAttestationUseCase $useCase): Response
    {
        try {
            $id = $request->query->getInt('inscription') ?: null;
            $pdf = $useCase->execute($id, $this->template($request->query->get('template')));
            return new Response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="apercu-attestation.pdf"', 'Cache-Control' => 'no-store']);
        } catch (\Throwable $e) {
            return $this->fail($e);
        }
    }

    private function template(mixed $value): ?string
    {
        return is_string($value) && in_array($value, DompdfAttestationGenerator::TEMPLATES, true) ? $value : null;
    }
}
