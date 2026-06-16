<?php

namespace EightyNine\ExcelImport\Support;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class FailedRowsExport implements FromArray, WithHeadings
{
    /**
     * @param  list<array<string, mixed>>  $errors
     */
    public function __construct(
        protected array $errors
    ) {}

    /**
     * @return list<array<int, string>>
     */
    public function array(): array
    {
        $headers = $this->headings();

        return array_map(
            fn (array $error): array => array_map(
                fn (string $header): string => $this->stringify($error[$header] ?? ''),
                $headers,
            ),
            $this->errors,
        );
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        $headers = [];

        foreach ($this->errors as $error) {
            foreach (array_keys($error) as $header) {
                if (! in_array($header, $headers, true)) {
                    $headers[] = $header;
                }
            }
        }

        return $headers;
    }

    protected function stringify(mixed $value): string
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
