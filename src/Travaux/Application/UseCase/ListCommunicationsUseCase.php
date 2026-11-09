<?php

namespace App\Travaux\Application\UseCase;

use App\Travaux\Domain\Repository\CommunicationRepositoryInterface;

class ListCommunicationsUseCase
{
    public function __construct(private readonly CommunicationRepositoryInterface $communications)
    {
    }

    /** @return array{data: array, meta: array} */
    public function execute(array $filters, int $page = 1, int $limit = 20): array
    {
        $page = max(1, $page);
        $limit = min(100, max(1, $limit));
        $p = $this->communications->search($filters, $page, $limit);

        return ['data' => iterator_to_array($p, false), 'meta' => ['page' => $page, 'limit' => $limit, 'total' => count($p), 'pages' => (int) ceil(count($p) / $limit)]];
    }
}
