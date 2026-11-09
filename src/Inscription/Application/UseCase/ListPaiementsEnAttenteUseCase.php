<?php

namespace App\Inscription\Application\UseCase;

use App\Inscription\Domain\Repository\PaiementRepositoryInterface;

/** File d'attente de vérification des paiements. */
class ListPaiementsEnAttenteUseCase
{
    public function __construct(private readonly PaiementRepositoryInterface $paiements)
    {
    }

    /** @return array{data: array, meta: array} */
    public function execute(int $page = 1, int $limit = 20): array
    {
        $page = max(1, $page);
        $limit = min(100, max(1, $limit));
        $paginator = $this->paiements->searchEnAttente($page, $limit);

        return [
            'data' => iterator_to_array($paginator, false),
            'meta' => ['page' => $page, 'limit' => $limit, 'total' => count($paginator), 'pages' => (int) ceil(count($paginator) / $limit)],
        ];
    }
}
