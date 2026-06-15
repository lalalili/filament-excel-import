<?php

use EightyNine\ExcelImport\DefaultImport;
use EightyNine\ExcelImport\ExcelImportAction;
use EightyNine\ExcelImport\QueuedDefaultImport;
use EightyNine\ExcelImport\Support\ImportResult;
use EightyNine\ExcelImport\Tables\ExcelImportRelationshipAction;
use Maatwebsite\Excel\Facades\Excel;

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

class QueueImportTestModel {}

class NonQueueableCustomImport extends DefaultImport {}

class TemporaryQueuedUpload
{
    public function getRealPath(): string
    {
        return '/tmp/people.xlsx';
    }
}
