<?php

namespace EightyNine\ExcelImport\Support;

use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithLimit;

class PreviewRowsImport implements WithHeadingRow, WithLimit
{
    public function __construct(
        protected int $limit
    ) {}

    public function limit(): int
    {
        return max(1, $this->limit);
    }
}
