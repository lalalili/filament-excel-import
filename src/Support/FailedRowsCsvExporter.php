<?php

namespace EightyNine\ExcelImport\Support;

use RuntimeException;

class FailedRowsCsvExporter
{
    public function export(ImportResult $result): string
    {
        if ($result->errors === []) {
            return '';
        }

        $export = new FailedRowsExport($result->errors);
        $headers = $export->headings();
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            throw new RuntimeException('Unable to open temporary CSV stream.');
        }

        fputcsv($handle, $headers);

        foreach ($export->array() as $row) {
            fputcsv($handle, $row);
        }

        rewind($handle);

        $contents = stream_get_contents($handle) ?: '';

        fclose($handle);

        return $contents;
    }
}
