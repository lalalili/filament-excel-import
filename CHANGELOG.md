# Changelog

All notable changes to `filament-excel-import` will be documented in this file.

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
