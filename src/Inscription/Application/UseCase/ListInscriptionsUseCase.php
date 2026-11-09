<?php

namespace App\Inscription\Application\UseCase;

use App\Inscription\Domain\Repository\InscriptionRepositoryInterface;

class ListInscriptionsUseCase
{
    public function __construct(private readonly InscriptionRepositoryInterface $inscriptions)
    {
    }

    /** @return array{data: array, meta: array} */
    public function execute(array $filters, int $page = 1, int $limit = 20): array
    {
        $page = max(1, $page);
        $limit = min(100, max(1, $limit));
        $paginator = $this->inscriptions->search($filters, $page, $limit);

        return [
            'data' => iterator_to_array($paginator, false),
            'meta' => ['page' => $page, 'limit' => $limit, 'total' => count($paginator), 'pages' => (int) ceil(count($paginator) / $limit)],
        ];
    }
}
