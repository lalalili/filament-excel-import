<?php

use EightyNine\ExcelImport\DefaultImport;
use EightyNine\ExcelImport\Support\ImportResult;

beforeEach(function () {
    ImportResultTestModel::$created = [];
});

it('creates records with mapped columns and exposes an import result', function () {
    $import = new DefaultImport(ImportResultTestModel::class);
    $import->setColumnMapping([
        'Email' => 'email',
        'Name' => 'name',
    ]);
    $import->setAdditionalData([
        'source' => 'upload',
    ]);

    $import->collection(collect([
        collect(['Email' => 'person@example.com', 'Name' => 'Taylor']),
        collect(['Email' => 'other@example.com', 'Name' => 'Chris']),
    ]));

    expect(ImportResultTestModel::$created)->toBe([
        ['email' => 'person@example.com', 'name' => 'Taylor', 'source' => 'upload'],
        ['email' => 'other@example.com', 'name' => 'Chris', 'source' => 'upload'],
    ]);

    expect($import->getImportResult()->toArray())->toBe([
        'created' => 2,
        'updated' => 0,
        'skipped' => 0,
        'failed' => 0,
        'errors' => [],
        'failedRowsPath' => null,
        'failedRowsDisk' => null,
        'failedRowsDownloadName' => null,
    ]);
});

it('accepts import results from custom collection callbacks', function () {
    $import = new DefaultImport(ImportResultTestModel::class);
    $import->setCollectionMethod(fn () => ImportResult::make(created: 1, updated: 2, skipped: 3));

    $import->collection(collect([
        collect(['email' => 'person@example.com']),
    ]));

    expect($import->getImportResult()->toArray())->toMatchArray([
        'created' => 1,
        'updated' => 2,
        'skipped' => 3,
        'failed' => 0,
    ]);
});

it('summarizes import result totals and errors', function () {
    $result = ImportResult::make(
        created: 1,
        updated: 2,
        skipped: 3,
        failed: 4,
        errors: [
            ['row' => 5, 'message' => 'Invalid email'],
        ],
    );

    expect($result->total())->toBe(10)
        ->and($result->hasErrors())->toBeTrue()
        ->and(ImportResult::empty()->total())->toBe(0)
        ->and(ImportResult::empty()->hasErrors())->toBeFalse();
});

it('stores failed rows download metadata immutably', function () {
    $result = ImportResult::make(
        failed: 1,
        errors: [
            ['row' => 5, 'message' => 'Invalid email'],
        ],
    );

    $resultWithFailedRows = $result->withFailedRows(
        path: 'imports/failed/people-errors.csv',
        disk: 'imports',
        downloadName: 'people-errors.csv',
    );

    expect($result->failedRowsPath)->toBeNull()
        ->and($resultWithFailedRows->toArray())->toMatchArray([
            'failedRowsPath' => 'imports/failed/people-errors.csv',
            'failedRowsDisk' => 'imports',
            'failedRowsDownloadName' => 'people-errors.csv',
        ]);
});

it('keeps custom collection callback return values compatible', function () {
    $import = new DefaultImport(ImportResultTestModel::class);
    $processed = collect([
        collect(['email' => 'processed@example.com']),
    ]);

    $import->setCollectionMethod(fn () => $processed);

    $result = $import->collection(collect([
        collect(['email' => 'person@example.com']),
    ]));

    expect($result)->toBe($processed);
});

class ImportResultTestModel
{
    public static array $created = [];

    public static function create(array $data): void
    {
        self::$created[] = $data;
    }
}
