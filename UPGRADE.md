# Upgrade Guide

## Upgrading to 4.1.0

Version `4.1.0` stabilizes the 4.x import pipeline introduced after the older
fork commits. The package name remains `eightynine/filament-excel-import`, and
existing `ExcelImportAction::make()` usage continues to work without changes.

### Requirements

- PHP 8.2 or newer.
- Filament 4 or 5.
- Laravel 11, 12, or 13 compatible Illuminate packages.

### Notable 4.1.0 changes

- Default imports now expose `EightyNine\ExcelImport\Support\ImportResult` for
  created, updated, skipped, failed, and row error counts.
- `afterImportResult()` runs after synchronous imports so applications can read
  import statistics without replacing the import flow.
- `queueImport()` dispatches compatible imports through Laravel Excel queues and
  swaps built-in default imports to queueable, chunked variants.
- `previewRows()` can show uploaded spreadsheet rows in the modal before import.
- `columnMapping()` maps uploaded headings to model attributes for default
  imports.
- Upload hardening helpers are available through `fileRules()`, `maxFileSize()`,
  and `mimeTypeMap()`.
- Sample file configuration can use `sampleButtonLabel()` and `sampleColumns()`
  for clearer setup.

### Prefer hooks over replacing the action

If your fork replaced the action closure only to run code before or after the
import, move that logic to the supported hooks:

```php
use EightyNine\ExcelImport\ExcelImportAction;
use EightyNine\ExcelImport\Support\ImportResult;

ExcelImportAction::make()
    ->beforeImport(function (array $data, mixed $livewire, ExcelImportAction $action): void {
        $action->customImportData([
            'imported_by' => auth()->id(),
        ]);
    })
    ->afterImportResult(function (ImportResult $result): void {
        logger()->info('Excel import finished', $result->toArray());
    });
```

`afterImport()` and `afterImportResult()` run for synchronous imports only.
Queued imports finish later in a worker, so move post-processing for queued
imports into the import class or queue lifecycle handling.

### Updating custom action subclasses

If your fork keeps a custom action subclass and it previously called the Excel
facade directly, reuse the protected pipeline methods instead. This keeps custom
actions aligned with upload normalization, queue validation, column mapping,
chunk configuration, and result resolution:

```php
use EightyNine\ExcelImport\ExcelImportAction;
use EightyNine\ExcelImport\Support\ImportResult;

class CustomExcelImportAction extends ExcelImportAction
{
    protected function importWithPackagePipeline(array $data, mixed $livewire): ImportResult
    {
        $importObject = $this->makeImportObject($livewire);

        $this->configureImportObject($importObject);
        $this->runImport($importObject, $data);

        return $this->resolveImportResult($importObject);
    }
}
```

The three main extension points are:

- `makeImportObject($livewire)` creates the configured import class, including
  relationship imports when used from relation managers.
- `configureImportObject($importObject)` applies additional data, custom import
  data, collection callbacks, column mapping, chunk size, and validation
  mutators when the import supports them.
- `runImport($importObject, $data)` executes either synchronous import or queued
  import according to `queueImport()`.

### Queueing notes

Custom import classes used with `queueImport()` must implement both
`Illuminate\Contracts\Queue\ShouldQueue` and
`Maatwebsite\Excel\Concerns\WithChunkReading`.

Closure-based settings are intentionally rejected for queued imports because
they cannot be serialized safely. Move `processCollectionUsing()` logic, closure
column mappings, and validation mutators into a queue-safe import class.

### Suggested verification

After upgrading, run the package checks:

```bash
composer test
composer analyse
composer format -- --test
```
