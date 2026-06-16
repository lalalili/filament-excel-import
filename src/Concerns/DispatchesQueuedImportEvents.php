<?php

namespace EightyNine\ExcelImport\Concerns;

use EightyNine\ExcelImport\Contracts\HasImportResult;
use EightyNine\ExcelImport\Events\ImportCompleted;
use EightyNine\ExcelImport\Events\ImportFailed;
use EightyNine\ExcelImport\Events\ImportStarted;
use EightyNine\ExcelImport\Support\ImportResult;
use Illuminate\Support\Facades\Event;
use Maatwebsite\Excel\Events\AfterImport as LaravelExcelAfterImport;
use Maatwebsite\Excel\Events\BeforeImport as LaravelExcelBeforeImport;
use Maatwebsite\Excel\Events\ImportFailed as LaravelExcelImportFailed;

trait DispatchesQueuedImportEvents
{
    /**
     * @return array<class-string, callable>
     */
    public function registerEvents(): array
    {
        return [
            LaravelExcelBeforeImport::class => [$this, 'beforeImport'],
            LaravelExcelAfterImport::class => [$this, 'afterImport'],
            LaravelExcelImportFailed::class => [$this, 'importFailed'],
        ];
    }

    public function beforeImport(LaravelExcelBeforeImport $event): void
    {
        Event::dispatch(new ImportStarted($this));
    }

    public function afterImport(LaravelExcelAfterImport $event): void
    {
        Event::dispatch(new ImportCompleted(
            $this,
            $this instanceof HasImportResult ? $this->getImportResult() : ImportResult::empty(),
        ));
    }

    public function importFailed(LaravelExcelImportFailed $event): void
    {
        Event::dispatch(new ImportFailed($this, $event->getException()));
    }
}
