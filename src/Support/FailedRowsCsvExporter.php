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

        $headers = $this->headers($result->errors);
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            throw new RuntimeException('Unable to open temporary CSV stream.');
        }

        fputcsv($handle, $headers);

        foreach ($result->errors as $error) {
            fputcsv($handle, array_map(
                fn (string $header): string => $this->stringify($error[$header] ?? ''),
                $headers,
            ));
        }

        rewind($handle);

        $contents = stream_get_contents($handle) ?: '';

        fclose($handle);

        return $contents;
    }

    /**
     * @param  list<array<string, mixed>>  $errors
     * @return list<string>
     */
    private function headers(array $errors): array
    {
        $headers = [];

        foreach ($errors as $error) {
            foreach (array_keys($error) as $header) {
                if (! in_array($header, $headers, true)) {
                    $headers[] = $header;
                }
            }
        }

        return $headers;
    }

    private function stringify(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return json_encode($value, JSON_THROW_ON_ERROR);
    }
}
