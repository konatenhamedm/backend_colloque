<?php

namespace App\Reporting\Application\Service;

interface TableExporterInterface
{
    /**
     * @param array<string, array{headers: string[], rows: array<int, array<int, scalar|null>>}> $sheets nom de feuille => tableau
     * @param 'xlsx'|'csv' $format
     */
    public function render(array $sheets, string $format): string;
}
