<?php

namespace EightyNine\ExcelImport\Support;

use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithLimit;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class PreviewRowsImport implements WithHeadingRow, WithLimit, WithMultipleSheets
{
    public function __construct(
        protected int $limit,
        protected ?int $sheetIndex = null,
    ) {}

    public function limit(): int
    {
        return max(1, $this->limit);
    }

    public function sheets(): array
    {
        if ($this->sheetIndex === null) {
            return [$this];
        }

        return [$this->sheetIndex => $this];
    }
}
