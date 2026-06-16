<?php

it('documents the 4.1.0 release notes and upgrade path', function () {
    $readme = file_get_contents(__DIR__ . '/../README.md');
    $changelog = file_get_contents(__DIR__ . '/../CHANGELOG.md');
    $upgrade = file_get_contents(__DIR__ . '/../UPGRADE.md');

    expect($readme)
        ->toContain('## v4.1.0 Highlights')
        ->toContain('afterImportResult()')
        ->toContain('queueImport()')
        ->toContain('previewRows()')
        ->toContain('columnMapping()')
        ->and($changelog)
        ->toContain('## 4.1.0 - 2026-06-16')
        ->and($upgrade)
        ->toContain('## Upgrading to 4.1.0')
        ->toContain('makeImportObject($livewire)')
        ->toContain('configureImportObject($importObject)')
        ->toContain('runImport($importObject, $data)');
});

it('documents the 4.2.0 failed rows csv release notes and upgrade path', function () {
    $readme = file_get_contents(__DIR__ . '/../README.md');
    $changelog = file_get_contents(__DIR__ . '/../CHANGELOG.md');
    $upgrade = file_get_contents(__DIR__ . '/../UPGRADE.md');

    expect($readme)
        ->toContain('## v4.2.0 Highlights')
        ->toContain('downloadFailedRows()')
        ->toContain('failedRowsDisk()')
        ->toContain('failedRowsPath')
        ->and($changelog)
        ->toContain('## 4.2.0 - 2026-06-16')
        ->toContain('failedRowsFileName()')
        ->and($upgrade)
        ->toContain('## Upgrading to 4.2.0')
        ->toContain('Queued imports do not write failed row summaries');
});

it('documents the 4.3.0 queue observability release notes and upgrade path', function () {
    $readme = file_get_contents(__DIR__ . '/../README.md');
    $changelog = file_get_contents(__DIR__ . '/../CHANGELOG.md');
    $upgrade = file_get_contents(__DIR__ . '/../UPGRADE.md');

    expect($readme)
        ->toContain('## v4.3.0 Highlights')
        ->toContain('ImportQueued')
        ->toContain('ImportCompleted')
        ->toContain('horizon:snapshot')
        ->and($changelog)
        ->toContain('## 4.3.0 - 2026-06-16')
        ->toContain('ImportFailed')
        ->and($upgrade)
        ->toContain('## Upgrading to 4.3.0')
        ->toContain('Custom queued import classes still receive `ImportQueued`');
});

it('documents the 4.4.0 custom action extension release notes and upgrade path', function () {
    $readme = file_get_contents(__DIR__ . '/../README.md');
    $changelog = file_get_contents(__DIR__ . '/../CHANGELOG.md');
    $upgrade = file_get_contents(__DIR__ . '/../UPGRADE.md');

    expect($readme)
        ->toContain('## v4.4.0 Highlights')
        ->toContain('handleImportException()')
        ->toContain('handleStoppedImport()')
        ->and($changelog)
        ->toContain('## 4.4.0 - 2026-06-16')
        ->toContain('protected import pipeline extension points')
        ->and($upgrade)
        ->toContain('## Upgrading to 4.4.0')
        ->toContain('callAfterImport(array $data, mixed $livewire): void')
        ->toContain('parent::handleImportException()');
});
