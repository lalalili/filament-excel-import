# Changelog

All notable changes to `filament-excel-import` will be documented in this file.

## 4.5.0 - 2026-06-16

- Change opt-in failed rows export to write XLSX files by default.
- Add `failedRowsFormat()` so failed row summaries can still be written as CSV.
- Harden failed rows file names and directories against unsafe paths.
- Limit upload previews to 50 rows and 25 rendered columns.
- Improve preview rendering for nested values while keeping HTML escaped.

## 4.4.0 - 2026-06-16

- Add protected import pipeline extension points for custom action subclasses.
- Allow custom actions to override `handleImportException()` instead of replacing the full `importData()` closure.
- Keep default `ImportStoppedException` notifications and error halting behavior in a reusable `handleStoppedImport()` method.

## 4.3.0 - 2026-06-16

- Add queue lifecycle events: `ImportQueued`, `ImportStarted`, `ImportCompleted`, and `ImportFailed`.
- Dispatch `ImportQueued` after `queueImport()` successfully hands the import to Laravel Excel.
- Dispatch package lifecycle events from built-in queued imports through Laravel Excel `WithEvents`.

## 4.2.0 - 2026-06-16

- Add opt-in failed rows CSV export with `downloadFailedRows()`.
- Add failed rows storage configuration through `failedRowsDisk()`, `failedRowsDirectory()`, and `failedRowsFileName()`.
- Add failed rows metadata to `ImportResult`: `failedRowsPath`, `failedRowsDisk`, and `failedRowsDownloadName`.

## 4.1.0 - 2026-06-16

- Add `ImportResult` and `afterImportResult()` for import statistics.
- Add upload hardening helpers: `fileRules()`, `maxFileSize()`, and `mimeTypeMap()`.
- Add `columnMapping()` for heading-to-field mapping in default imports.
- Add `previewRows()` to show a limited table preview in the import modal.
- Add queueable default resource import variants for `queueImport()` with configurable `chunkSize()` and stored upload handling.
- Add `sampleButtonLabel()` and `sampleColumns()` for clearer sample file configuration.
- Share the import execution flow between resource and relationship actions.

## 1.0.0 - 202X-XX-XX

- initial release
