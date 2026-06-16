<?php

namespace EightyNine\ExcelImport;

use EightyNine\ExcelImport\Concerns\DispatchesQueuedImportEvents;
use Illuminate\Contracts\Queue\ShouldQueue;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;

class QueuedDefaultImport extends DefaultImport implements ShouldQueue, WithChunkReading, WithEvents
{
    use DispatchesQueuedImportEvents;

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
