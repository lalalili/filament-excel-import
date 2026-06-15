<?php

namespace EightyNine\ExcelImport;

use Illuminate\Contracts\Queue\ShouldQueue;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class QueuedEnhancedDefaultImport extends EnhancedDefaultImport implements ShouldQueue, WithChunkReading
{
    protected int $chunkSize = 1000;

    public function setChunkSize(int $chunkSize): void
    {
        $this->chunkSize = $chunkSize;
    }

    public function chunkSize(): int
    {
        return $this->chunkSize;
    }
}
