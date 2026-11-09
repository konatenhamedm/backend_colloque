<?php

namespace App\Reporting\Infrastructure\Export;

use App\Reporting\Application\Service\TableExporterInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class SpreadsheetTableExporter implements TableExporterInterface
{
    public function render(array $sheets, string $format): string
    {
        $book = new Spreadsheet();
        $book->removeSheetByIndex(0);

        foreach ($sheets as $name => $table) {
            $sheet = $book->createSheet();
            $sheet->setTitle(mb_substr(preg_replace('/[\\\\\/?*\[\]:]/', ' ', (string) $name), 0, 31));
            $sheet->fromArray([$table['headers'], ...$this->neutralise($table['rows'])], null, 'A1');

            $last = $sheet->getHighestColumn();
            $sheet->getStyle("A1:{$last}1")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
            $sheet->getStyle("A1:{$last}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1B2A6B');
            foreach (range('A', $last) as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
            $sheet->freezePane('A2');
            if ($format === 'csv') {
                break; // un CSV = une seule table
            }
        }
        $book->setActiveSheetIndex(0);

        $tmp = tempnam(sys_get_temp_dir(), 'exp');
        try {
            $writer = $format === 'csv' ? (new Csv($book))->setDelimiter(';')->setUseBOM(true) : new Xlsx($book);
            $writer->save($tmp);
            return (string) file_get_contents($tmp);
        } finally {
            @unlink($tmp);
        }
    }

    /** Empêche l'injection de formules Excel (=, +, -, @) via des champs saisis par les participants. */
    private function neutralise(array $rows): array
    {
        return array_map(fn (array $row) => array_map(
            static fn ($v) => is_string($v) && $v !== '' && str_contains("=+-@\t\r", $v[0]) ? "'".$v : $v,
            $row
        ), $rows);
    }
}
