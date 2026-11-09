<?php

namespace App\Reporting\UI\Controller;

use App\Reporting\Application\UseCase\ExportRapportUseCase;
use App\Reporting\Application\UseCase\GetDashboardStatsUseCase;
use App\Security\Domain\Entity\Utilisateur;
use App\Shared\UI\Controller\ApiInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin')]
#[IsGranted(Utilisateur::ROLE_ADMIN)]
#[OA\Tag(name: 'Back-office : reporting')]
class AdminReportingController extends ApiInterface
{
    #[Route('/stats', name: 'api_admin_stats', methods: ['GET'])]
    public function stats(GetDashboardStatsUseCase $useCase): JsonResponse
    {
        try {
            return $this->responseData($useCase->execute());
        } catch (\Throwable $e) {
            return $this->fail($e);
        }
    }

    /** GET /api/admin/exports/emargement?format=xlsx|csv  —  /exports/finances */
    #[Route('/exports/{type}', name: 'api_admin_export', requirements: ['type' => 'emargement|finances'], methods: ['GET'])]
    public function export(string $type, Request $request, ExportRapportUseCase $useCase): Response
    {
        try {
            $file = $useCase->execute($type, (string) $request->query->get('format', 'xlsx'));
            $response = new Response($file['content'], 200, ['Content-Type' => $file['mime']]);
            $response->headers->set('Content-Disposition', $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $file['filename']));
            return $response;
        } catch (\Throwable $e) {
            return $this->fail($e);
        }
    }
}
