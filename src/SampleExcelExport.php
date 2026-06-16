<?php

namespace EightyNine\ExcelImport;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SampleExcelExport implements FromCollection, WithHeadings
{
    public function __construct(
        public array $data
    ) {}

    /**
     * @return Collection
     */
    public function collection()
    {
        return collect($this->data);
    }

    public function headings(): array
    {
        return array_keys($this->data[0]);
    }
}
