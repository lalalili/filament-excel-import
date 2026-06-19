<?php

use EightyNine\ExcelImport\DefaultImport;
use EightyNine\ExcelImport\EnhancedDefaultImport;
use EightyNine\ExcelImport\Events\ImportCompleted;
use EightyNine\ExcelImport\Events\ImportFailed;
use EightyNine\ExcelImport\Events\ImportQueued;
use EightyNine\ExcelImport\Events\ImportStarted;
use EightyNine\ExcelImport\ExcelImportAction;
use EightyNine\ExcelImport\QueuedDefaultImport;
use EightyNine\ExcelImport\QueuedEnhancedDefaultImport;
use EightyNine\ExcelImport\Support\FailedRowsExport;
use EightyNine\ExcelImport\Support\ImportResult;
use EightyNine\ExcelImport\Tables\ExcelImportRelationshipAction;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterImport as LaravelExcelAfterImport;
use Maatwebsite\Excel\Events\BeforeImport as LaravelExcelBeforeImport;
use Maatwebsite\Excel\Events\ImportFailed as LaravelExcelImportFailed;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Reader;

it('queues default imports with the configured chunk size', function () {
    Excel::fake();

    $action = TestableQueueExcelImportAction::make()
        ->queueImport()
        ->chunkSize(250);

    $import = $action->makeImportObjectForTest(new QueueImportLivewire);
    $action->configureImportObjectForTest($import);

    expect($import)->toBeInstanceOf(QueuedDefaultImport::class)
        ->and($import->chunkSize())->toBe(250);

    $action->runImportForTest($import, [
        'upload' => 'people.xlsx',
    ]);

    Excel::assertQueued('people.xlsx', config('filesystems.default'), fn (QueuedDefaultImport $queuedImport): bool => $queuedImport === $import);
});

it('dispatches an event after queueing an import', function () {
    Excel::fake();
    Event::fake([ImportQueued::class]);

    $action = TestableQueueExcelImportAction::make()
        ->queueImport()
        ->disk('imports');

    $import = $action->makeImportObjectForTest(new QueueImportLivewire);
    $action->configureImportObjectForTest($import);
    $action->runImportForTest($import, [
        'upload' => 'people.xlsx',
    ]);

    Event::assertDispatched(
        ImportQueued::class,
        fn (ImportQueued $event): bool => $event->import === $import
            && $event->path === 'people.xlsx'
            && $event->disk === 'imports'
    );
});

it('adds laravel excel lifecycle events to built in queued imports', function () {
    $defaultAction = TestableQueueExcelImportAction::make()
        ->queueImport();

    $defaultImport = $defaultAction->makeImportObjectForTest(new QueueImportLivewire);

    $enhancedAction = TestableQueueExcelImportAction::make()
        ->use(EnhancedDefaultImport::class)
        ->queueImport();

    $enhancedImport = $enhancedAction->makeImportObjectForTest(new QueueImportLivewire);

    expect($defaultImport)
        ->toBeInstanceOf(QueuedDefaultImport::class)
        ->toBeInstanceOf(WithEvents::class)
        ->and($enhancedImport)
        ->toBeInstanceOf(QueuedEnhancedDefaultImport::class)
        ->toBeInstanceOf(WithEvents::class)
        ->and(array_keys($defaultImport->registerEvents()))
        ->toBe([
            LaravelExcelBeforeImport::class,
            LaravelExcelAfterImport::class,
            LaravelExcelImportFailed::class,
        ])
        ->and(array_keys($enhancedImport->registerEvents()))
        ->toBe([
            LaravelExcelBeforeImport::class,
            LaravelExcelAfterImport::class,
            LaravelExcelImportFailed::class,
        ]);
});

it('dispatches package lifecycle events from built in queued imports', function () {
    Event::fake([
        ImportStarted::class,
        ImportCompleted::class,
        ImportFailed::class,
    ]);

    $import = new QueuedDefaultImport(QueueImportTestModel::class);
    $import->collection(collect([
        collect(['email' => 'person@example.com']),
    ]));

    $events = $import->registerEvents();
    $reader = $this->createStub(Reader::class);
    $exception = new RuntimeException('Import crashed.');

    call_user_func($events[LaravelExcelBeforeImport::class], new LaravelExcelBeforeImport($reader, $import));
    call_user_func($events[LaravelExcelAfterImport::class], new LaravelExcelAfterImport($reader, $import));
    call_user_func($events[LaravelExcelImportFailed::class], new LaravelExcelImportFailed($exception));

    Event::assertDispatched(
        ImportStarted::class,
        fn (ImportStarted $event): bool => $event->import === $import
    );

    Event::assertDispatched(
        ImportCompleted::class,
        fn (ImportCompleted $event): bool => $event->import === $import
            && $event->result->created === 1
    );

    Event::assertDispatched(
        ImportFailed::class,
        fn (ImportFailed $event): bool => $event->import === $import
            && $event->exception === $exception
    );
});

it('normalizes array upload state before synchronous imports', function () {
    Excel::fake();

    $action = TestableQueueExcelImportAction::make();

    $import = $action->makeImportObjectForTest(new QueueImportLivewire);
    $action->configureImportObjectForTest($import);
    $action->runImportForTest($import, [
        'upload' => ['people.xlsx'],
    ]);

    Excel::assertImported('people.xlsx', fn (DefaultImport $defaultImport): bool => $defaultImport === $import);
});

it('imports stored synchronous uploads from the configured upload disk', function () {
    config()->set('excel-import.upload_disk', 'imports');

    Excel::fake();

    $action = TestableQueueExcelImportAction::make()
        ->storeFiles(true);

    $import = $action->makeImportObjectForTest(new QueueImportLivewire);
    $action->configureImportObjectForTest($import);
    $action->runImportForTest($import, [
        'upload' => ['people.xlsx'],
    ]);

    Excel::assertImported('people.xlsx', 'imports', fn (DefaultImport $defaultImport): bool => $defaultImport === $import);
});

it('requires an uploaded file before synchronous imports', function () {
    $action = TestableQueueExcelImportAction::make();

    $import = $action->makeImportObjectForTest(new QueueImportLivewire);
    $action->configureImportObjectForTest($import);
    $action->runImportForTest($import, []);
})->throws(InvalidArgumentException::class, 'Import requires an uploaded file.');

it('queues imports on the configured disk', function () {
    Excel::fake();

    $action = TestableQueueExcelImportAction::make()
        ->queueImport()
        ->disk('imports');

    $import = $action->makeImportObjectForTest(new QueueImportLivewire);
    $action->configureImportObjectForTest($import);
    $action->runImportForTest($import, [
        'upload' => 'people.xlsx',
    ]);

    Excel::assertQueued('people.xlsx', 'imports', fn (QueuedDefaultImport $queuedImport): bool => $queuedImport === $import);
});

it('queues imports on the configured upload disk', function () {
    config()->set('excel-import.upload_disk', 'imports');

    Excel::fake();

    $action = TestableQueueExcelImportAction::make()
        ->queueImport();

    $import = $action->makeImportObjectForTest(new QueueImportLivewire);
    $action->configureImportObjectForTest($import);
    $action->runImportForTest($import, [
        'upload' => 'people.xlsx',
    ]);

    Excel::assertQueued('people.xlsx', 'imports', fn (QueuedDefaultImport $queuedImport): bool => $queuedImport === $import);
});

it('normalizes array upload state before queueing imports', function () {
    Excel::fake();

    $action = TestableQueueExcelImportAction::make()
        ->queueImport();

    $import = $action->makeImportObjectForTest(new QueueImportLivewire);
    $action->configureImportObjectForTest($import);
    $action->runImportForTest($import, [
        'upload' => ['people.xlsx'],
    ]);

    Excel::assertQueued('people.xlsx', config('filesystems.default'), fn (QueuedDefaultImport $queuedImport): bool => $queuedImport === $import);
});

it('requires an uploaded file before queued imports', function () {
    $action = TestableQueueExcelImportAction::make()
        ->queueImport();

    $import = $action->makeImportObjectForTest(new QueueImportLivewire);
    $action->configureImportObjectForTest($import);
    $action->runImportForTest($import, [
        'upload' => [],
    ]);
})->throws(InvalidArgumentException::class, 'Import requires an uploaded file.');

it('runs completion hooks with import results for synchronous imports', function () {
    $afterImportWasCalled = false;
    $importResult = null;

    $action = TestableSuccessfulExcelImportAction::make()
        ->processCollectionUsing(fn (): ImportResult => ImportResult::created(3))
        ->afterImport(function () use (&$afterImportWasCalled): void {
            $afterImportWasCalled = true;
        })
        ->afterImportResult(function (ImportResult $result) use (&$importResult): void {
            $importResult = $result;
        });

    $result = $action->runActionForTest([
        'upload' => 'people.xlsx',
    ], new QueueImportLivewire);

    expect($result)->toBeTrue()
        ->and($afterImportWasCalled)->toBeTrue()
        ->and($importResult)->toBeInstanceOf(ImportResult::class)
        ->and($importResult?->created)->toBe(3);
});

it('does not run completion hooks immediately for queued imports', function () {
    Excel::fake();

    $afterImportWasCalled = false;
    $afterImportResultWasCalled = false;

    $action = TestableQueueExcelImportAction::make()
        ->queueImport()
        ->afterImport(function () use (&$afterImportWasCalled): void {
            $afterImportWasCalled = true;
        })
        ->afterImportResult(function () use (&$afterImportResultWasCalled): void {
            $afterImportResultWasCalled = true;
        });

    $result = $action->runActionForTest([
        'upload' => 'people.xlsx',
    ], new QueueImportLivewire);

    expect($result)->toBeTrue()
        ->and($afterImportWasCalled)->toBeFalse()
        ->and($afterImportResultWasCalled)->toBeFalse();
});

it('does not write failed rows by default', function () {
    Storage::fake('imports');

    $importResult = null;

    $action = TestableSuccessfulExcelImportAction::make()
        ->failedRowsDisk('imports')
        ->processCollectionUsing(fn (): ImportResult => ImportResult::make(
            failed: 1,
            errors: [
                ['row' => 2, 'attribute' => 'email', 'message' => 'Invalid email'],
            ],
        ))
        ->afterImportResult(function (ImportResult $result) use (&$importResult): void {
            $importResult = $result;
        });

    $action->runActionForTest([
        'upload' => 'people.xlsx',
    ], new QueueImportLivewire);

    Storage::disk('imports')->assertMissing('excel-import/failed-rows/failed-rows.xlsx');

    expect($importResult)->toBeInstanceOf(ImportResult::class)
        ->and($importResult?->failedRowsPath)->toBeNull()
        ->and($importResult?->failedRowsDisk)->toBeNull()
        ->and($importResult?->failedRowsDownloadName)->toBeNull();
});

it('writes failed row errors to xlsx by default when opted in', function () {
    Excel::fake();

    $importResult = null;

    $action = TestableSuccessfulExcelImportAction::make()
        ->downloadFailedRows()
        ->failedRowsDisk('imports')
        ->failedRowsDirectory('imports/errors')
        ->failedRowsFileName(fn (ImportResult $result): string => "people-errors-{$result->failed}")
        ->processCollectionUsing(fn (): ImportResult => ImportResult::make(
            failed: 2,
            errors: [
                ['row' => 2, 'attribute' => 'email', 'message' => 'Invalid email', 'values' => ['email' => 'bad']],
                ['row' => 3, 'attribute' => 'name', 'message' => 'Required'],
            ],
        ))
        ->afterImportResult(function (ImportResult $result) use (&$importResult): void {
            $importResult = $result;
        });

    $action->runActionForTest([
        'upload' => 'people.xlsx',
    ], new QueueImportLivewire);

    Excel::assertStored(
        'imports/errors/people-errors-2.xlsx',
        'imports',
        fn (FailedRowsExport $export): bool => $export->headings() === ['row', 'attribute', 'message', 'values']
            && $export->array() === [
                ['2', 'email', 'Invalid email', '{"email":"bad"}'],
                ['3', 'name', 'Required', ''],
            ]
    );

    expect($importResult)->toBeInstanceOf(ImportResult::class)
        ->and($importResult?->failedRowsPath)->toBe('imports/errors/people-errors-2.xlsx')
        ->and($importResult?->failedRowsDisk)->toBe('imports')
        ->and($importResult?->failedRowsDownloadName)->toBe('people-errors-2.xlsx');
});

it('writes failed row errors to csv when requested', function () {
    Storage::fake('imports');

    $importResult = null;

    $action = TestableSuccessfulExcelImportAction::make()
        ->downloadFailedRows()
        ->failedRowsFormat('csv')
        ->failedRowsDisk('imports')
        ->failedRowsDirectory('imports/errors')
        ->failedRowsFileName(fn (ImportResult $result): string => "people-errors-{$result->failed}")
        ->processCollectionUsing(fn (): ImportResult => ImportResult::make(
            failed: 2,
            errors: [
                ['row' => 2, 'attribute' => 'email', 'message' => 'Invalid email', 'values' => ['email' => 'bad']],
                ['row' => 3, 'attribute' => 'name', 'message' => 'Required'],
            ],
        ))
        ->afterImportResult(function (ImportResult $result) use (&$importResult): void {
            $importResult = $result;
        });

    $action->runActionForTest([
        'upload' => 'people.xlsx',
    ], new QueueImportLivewire);

    Storage::disk('imports')->assertExists('imports/errors/people-errors-2.csv');

    expect($importResult)->toBeInstanceOf(ImportResult::class)
        ->and($importResult?->failedRowsPath)->toBe('imports/errors/people-errors-2.csv')
        ->and($importResult?->failedRowsDisk)->toBe('imports')
        ->and($importResult?->failedRowsDownloadName)->toBe('people-errors-2.csv')
        ->and(Storage::disk('imports')->get('imports/errors/people-errors-2.csv'))
        ->toBe("row,attribute,message,values\n2,email,\"Invalid email\",\"{\"\"email\"\":\"\"bad\"\"}\"\n3,name,Required,\n");
});

it('infers csv failed rows format from the configured file name', function () {
    Storage::fake('imports');

    $importResult = null;

    $action = TestableSuccessfulExcelImportAction::make()
        ->downloadFailedRows()
        ->failedRowsDisk('imports')
        ->failedRowsFileName('people-errors.csv')
        ->processCollectionUsing(fn (): ImportResult => ImportResult::make(
            failed: 1,
            errors: [
                ['row' => 2, 'message' => 'Invalid email'],
            ],
        ))
        ->afterImportResult(function (ImportResult $result) use (&$importResult): void {
            $importResult = $result;
        });

    $action->runActionForTest([
        'upload' => 'people.xlsx',
    ], new QueueImportLivewire);

    Storage::disk('imports')->assertExists('excel-import/failed-rows/people-errors.csv');

    expect($importResult?->failedRowsDownloadName)->toBe('people-errors.csv')
        ->and(Storage::disk('imports')->get('excel-import/failed-rows/people-errors.csv'))
        ->toBe("row,message\n2,\"Invalid email\"\n");
});

it('lets an explicit failed rows format replace a conflicting file extension', function () {
    Excel::fake();

    $importResult = null;

    $action = TestableSuccessfulExcelImportAction::make()
        ->downloadFailedRows()
        ->failedRowsFormat('xlsx')
        ->failedRowsDisk('imports')
        ->failedRowsFileName('people-errors.csv')
        ->processCollectionUsing(fn (): ImportResult => ImportResult::make(
            failed: 1,
            errors: [
                ['row' => 2, 'message' => 'Invalid email'],
            ],
        ))
        ->afterImportResult(function (ImportResult $result) use (&$importResult): void {
            $importResult = $result;
        });

    $action->runActionForTest([
        'upload' => 'people.xlsx',
    ], new QueueImportLivewire);

    Excel::assertStored('excel-import/failed-rows/people-errors.xlsx', 'imports');

    expect($importResult?->failedRowsDownloadName)->toBe('people-errors.xlsx');
});

it('rejects unsupported failed rows formats', function () {
    TestableSuccessfulExcelImportAction::make()->failedRowsFormat('pdf');
})->throws(InvalidArgumentException::class, 'Failed rows format must be csv or xlsx.');

it('rejects unsafe failed rows directories', function (string $directory) {
    TestableSuccessfulExcelImportAction::make()->failedRowsDirectory($directory);
})->with([
    '',
    '/absolute',
    '../exports',
    'exports/../errors',
    'exports\\errors',
    "exports\nerrors",
])->throws(InvalidArgumentException::class);

it('rejects unsafe failed rows file names', function (string $fileName) {
    TestableSuccessfulExcelImportAction::make()->failedRowsFileName($fileName);
})->with([
    '',
    '/absolute.xlsx',
    '../errors.xlsx',
    'nested/errors.xlsx',
    'nested\\errors.xlsx',
    "errors\n.xlsx",
])->throws(InvalidArgumentException::class);

it('does not write failed rows for queued imports', function () {
    Storage::fake('imports');
    Excel::fake();

    $action = TestableQueueExcelImportAction::make()
        ->queueImport()
        ->downloadFailedRows()
        ->failedRowsDisk('imports')
        ->afterImportResult(function (): void {
            throw new RuntimeException('Queued imports should not run result hooks immediately.');
        });

    $result = $action->runActionForTest([
        'upload' => 'people.xlsx',
    ], new QueueImportLivewire);

    Storage::disk('imports')->assertMissing('excel-import/failed-rows/failed-rows.xlsx');

    expect($result)->toBeTrue();
});

it('requires custom queued imports to be queueable and chunked', function () {
    $action = TestableQueueExcelImportAction::make()
        ->use(NonQueueableCustomImport::class)
        ->queueImport();

    $import = $action->makeImportObjectForTest(new QueueImportLivewire);
    $action->configureImportObjectForTest($import);

    $action->runImportForTest($import, [
        'upload' => 'people.xlsx',
    ]);
})->throws(InvalidArgumentException::class, 'Queued imports must implement ShouldQueue and WithChunkReading.');

it('does not provide a queueable relationship default import', function () {
    $action = TestableQueueExcelImportRelationshipAction::make()
        ->model(QueueImportTestModel::class)
        ->queueImport();

    $import = $action->makeImportObjectForTest(new QueueImportLivewire);
    $action->configureImportObjectForTest($import);

    $action->runImportForTest($import, [
        'upload' => 'people.xlsx',
    ]);
})->throws(InvalidArgumentException::class, 'Queued imports must implement ShouldQueue and WithChunkReading.');

it('rejects collection callback closures for queued imports', function () {
    $action = TestableQueueExcelImportAction::make()
        ->queueImport()
        ->processCollectionUsing(fn () => null);

    $import = $action->makeImportObjectForTest(new QueueImportLivewire);
    $action->configureImportObjectForTest($import);

    $action->runImportForTest($import, [
        'upload' => 'people.xlsx',
    ]);
})->throws(InvalidArgumentException::class, 'Queued imports cannot use processCollectionUsing() closures.');

it('rejects closure column mappings for queued imports', function () {
    $action = TestableQueueExcelImportAction::make()
        ->queueImport()
        ->columnMapping(fn (array $row): array => $row);

    $import = $action->makeImportObjectForTest(new QueueImportLivewire);
    $action->configureImportObjectForTest($import);

    $action->runImportForTest($import, [
        'upload' => 'people.xlsx',
    ]);
})->throws(InvalidArgumentException::class, 'Queued imports cannot use closure column mappings.');

it('rejects validation mutator closures for queued imports', function () {
    $action = TestableQueueExcelImportAction::make()
        ->queueImport()
        ->mutateAfterValidationUsing(fn (array $data): array => $data);

    $import = $action->makeImportObjectForTest(new QueueImportLivewire);
    $action->configureImportObjectForTest($import);

    $action->runImportForTest($import, [
        'upload' => 'people.xlsx',
    ]);
})->throws(InvalidArgumentException::class, 'Queued imports cannot use validation mutator closures.');

it('rejects closures nested in queued import data', function () {
    $action = TestableQueueExcelImportAction::make()
        ->queueImport()
        ->additionalData([
            'callback' => fn () => null,
        ]);

    $import = $action->makeImportObjectForTest(new QueueImportLivewire);
    $action->configureImportObjectForTest($import);

    $action->runImportForTest($import, [
        'upload' => 'people.xlsx',
    ]);
})->throws(InvalidArgumentException::class, 'Queued imports cannot serialize closures in import data.');

it('requires stored upload paths for queued imports', function () {
    $action = TestableQueueExcelImportAction::make()
        ->queueImport();

    $import = $action->makeImportObjectForTest(new QueueImportLivewire);
    $action->configureImportObjectForTest($import);

    $action->runImportForTest($import, [
        'upload' => new TemporaryQueuedUpload,
    ]);
})->throws(InvalidArgumentException::class, 'Queued imports require stored upload paths.');

it('requires stored upload paths in array state for queued imports', function () {
    $action = TestableQueueExcelImportAction::make()
        ->queueImport();

    $import = $action->makeImportObjectForTest(new QueueImportLivewire);
    $action->configureImportObjectForTest($import);

    $action->runImportForTest($import, [
        'upload' => [new TemporaryQueuedUpload],
    ]);
})->throws(InvalidArgumentException::class, 'Queued imports require stored upload paths.');

class TestableQueueExcelImportAction extends ExcelImportAction
{
    public function makeImportObjectForTest(mixed $livewire): object
    {
        return $this->makeImportObject($livewire);
    }

    public function configureImportObjectForTest(object $importObject): void
    {
        $this->configureImportObject($importObject);
    }

    public function runImportForTest(object $importObject, array $data): void
    {
        $this->runImport($importObject, $data);
    }

    public function runActionForTest(array $data, mixed $livewire): bool
    {
        $this->action('importData');

        return ($this->getActionFunction())($data, $livewire);
    }
}

class TestableQueueExcelImportRelationshipAction extends ExcelImportRelationshipAction
{
    public function makeImportObjectForTest(mixed $livewire): object
    {
        return $this->makeImportObject($livewire);
    }

    public function configureImportObjectForTest(object $importObject): void
    {
        $this->configureImportObject($importObject);
    }

    public function runImportForTest(object $importObject, array $data): void
    {
        $this->runImport($importObject, $data);
    }
}

class TestableSuccessfulExcelImportAction extends TestableQueueExcelImportAction
{
    protected function runImport(object $importObject, array $data): void
    {
        $this->guardImportUpload($data['upload'] ?? null);

        if (method_exists($importObject, 'collection')) {
            $importObject->collection(collect([
                collect(['email' => 'person@example.com']),
            ]));
        }
    }
}

class QueueImportLivewire
{
    public function getModel(): string
    {
        return QueueImportTestModel::class;
    }
}

class QueueImportTestModel
{
    public static function create(array $data): void
    {
        //
    }
}

class NonQueueableCustomImport extends DefaultImport {}

class TemporaryQueuedUpload
{
    public function getRealPath(): string
    {
        return '/tmp/people.xlsx';
    }
}
