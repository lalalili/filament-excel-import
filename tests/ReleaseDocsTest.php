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
