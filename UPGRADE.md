# Upgrade Guide

## Upgrading to 4.5.0

Version `4.5.0` changes failed row summaries from CSV to XLSX by default.
Existing imports are unchanged unless `downloadFailedRows()` is enabled.

```php
use EightyNine\ExcelImport\ExcelImportAction;

ExcelImportAction::make()
    ->downloadFailedRows()
    ->failedRowsDisk('local')
    ->failedRowsDirectory('imports/failed')
    ->failedRowsFileName('people-errors.xlsx');
```

If your application expects the previous CSV file, opt in explicitly:

```php
ExcelImportAction::make()
    ->downloadFailedRows()
    ->failedRowsFormat('csv')
    ->failedRowsFileName('people-errors.csv');
```

When `failedRowsFormat()` is not called, `.csv` and `.xlsx` suffixes passed to
`failedRowsFileName()` are used to infer the export format. Explicit
`failedRowsFormat()` calls take precedence and normalize the file extension.

Failed row file names may not contain paths or control characters. Failed row
directories must be relative paths without `.` or `..` segments.

Upload previews now reject limits above `50` rows and render at most `25`
columns. Preview output remains escaped and should be treated as a format
confirmation aid, not a replacement for `fileRules()` or `validateUsing()`.

## Upgrading to 4.4.0

Version `4.4.0` adds protected extension points for custom action subclasses.
Existing `ExcelImportAction::make()` usage is unchanged.

If your application extends `ExcelImportAction` only to catch import exceptions,
override `handleImportException()` instead of copying the full `importData()`
closure:

```php
use EightyNine\ExcelImport\ExcelImportAction;
use Maatwebsite\Excel\Validators\ValidationException;
use Throwable;

class CustomExcelImportAction extends ExcelImportAction
{
    protected function handleImportException(Throwable $exception, array $data, mixed $livewire, object $importObject): bool
    {
        if ($exception instanceof ValidationException) {
            $failure = $exception->failures()[0];

            session()->put('import_result', [
                'status' => 0,
                'errMsg' => sprintf(
                    'Row %d %s: %s',
                    $failure->row(),
                    $failure->attribute(),
                    $failure->errors()[0],
                ),
            ]);

            $this->callAfterImport($data, $livewire);

            return true;
        }

        return parent::handleImportException($exception, $data, $livewire, $importObject);
    }
}
```

The supported protected action pipeline methods are:

- `handleImport(array $data, mixed $livewire): bool`
- `callBeforeImport(array $data, mixed $livewire): void`
- `callAfterImport(array $data, mixed $livewire): void`
- `completeImport(object $importObject, array $data, mixed $livewire): bool`
- `handleImportException(Throwable $exception, array $data, mixed $livewire, object $importObject): bool`
- `handleStoppedImport(ImportStoppedException $exception): bool`

Leave unknown exceptions delegated to `parent::handleImportException()` so
`ImportStoppedException` notifications, error halting, and unhandled exception
bubbling keep the package defaults.

## Upgrading to 4.3.0

Version `4.3.0` adds queue lifecycle events. Existing synchronous imports are
unchanged, and queued imports still do not run Livewire `afterImport()` or
`afterImportResult()` hooks immediately.

New package events are available under `EightyNine\ExcelImport\Events`:

- `ImportQueued`
- `ImportStarted`
- `ImportCompleted`
- `ImportFailed`

`ImportQueued` is dispatched after `queueImport()` successfully dispatches the
Laravel Excel queued import. Built-in queued imports dispatch `ImportStarted`,
`ImportCompleted`, and `ImportFailed` from Laravel Excel `WithEvents` while the
worker processes the import.

Custom queued import classes still receive `ImportQueued` from the action. Add
your own Laravel Excel `WithEvents` implementation if you need worker lifecycle
events for custom imports.

## Upgrading to 4.2.0

Version `4.2.0` adds opt-in failed rows CSV export. Existing imports keep the
same behavior unless `downloadFailedRows()` is enabled.

```php
use EightyNine\ExcelImport\ExcelImportAction;
use EightyNine\ExcelImport\Support\ImportResult;

ExcelImportAction::make()
    ->downloadFailedRows()
    ->failedRowsDisk('local')
    ->failedRowsDirectory('imports/failed')
    ->failedRowsFileName('people-errors.csv')
    ->afterImportResult(function (ImportResult $result): void {
        $path = $result->failedRowsPath;
        $disk = $result->failedRowsDisk;
        $downloadName = $result->failedRowsDownloadName;
    });
```

Only `ImportResult::$errors` rows are exported. If an import has no row-level
errors, no CSV is written and the failed rows metadata remains `null`.

Queued imports do not write failed row summaries from the action instance
because queued work finishes later in a worker and Livewire result hooks are not
run immediately.

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
facade directly, reuse the protected pipeline methods instead of replacing the
whole action closure. On `4.4.0` or newer, override the smallest hook that fits
your custom behavior:

```php
use EightyNine\ExcelImport\ExcelImportAction;
use Throwable;

class CustomExcelImportAction extends ExcelImportAction
{
    protected function handleImportException(Throwable $exception, array $data, mixed $livewire, object $importObject): bool
    {
        session()->put('import_result', [
            'status' => 0,
            'errMsg' => $exception->getMessage(),
        ]);

        $this->callAfterImport($data, $livewire);

        return true;
    }
}
```

The lower-level extension points are:

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
