<?php

use EightyNine\ExcelImport\DefaultRelationshipImport;
use EightyNine\ExcelImport\ExcelImportAction;
use EightyNine\ExcelImport\Exceptions\ImportStoppedException;
use EightyNine\ExcelImport\Support\ImportResult;
use EightyNine\ExcelImport\Tables\ExcelImportRelationshipAction;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException as IlluminateValidationException;
use Maatwebsite\Excel\Validators\Failure;
use Maatwebsite\Excel\Validators\ValidationException as ExcelValidationException;

it('runs the import pipeline hooks in order for synchronous imports', function () {
    $recorder = new PipelineRecorder;
    $importResult = null;

    $action = TestablePipelineExcelImportAction::make()
        ->recorder($recorder)
        ->processCollectionUsing(fn (): ImportResult => ImportResult::created(2))
        ->beforeImport(function () use ($recorder): void {
            $recorder->events[] = 'before';
        })
        ->afterImport(function () use ($recorder): void {
            $recorder->events[] = 'after';
        })
        ->afterImportResult(function (ImportResult $result) use ($recorder, &$importResult): void {
            $recorder->events[] = 'result';
            $importResult = $result;
        });

    $result = $action->runActionForTest([
        'upload' => 'people.xlsx',
    ], new PipelineLivewire);

    expect($result)->toBeTrue()
        ->and($recorder->events)->toBe(['before', 'run', 'after', 'result'])
        ->and($importResult)->toBeInstanceOf(ImportResult::class)
        ->and($importResult?->created)->toBe(2);
});

it('allows subclasses to handle validation exceptions without replacing importData', function () {
    $afterImportWasCalled = false;
    $exception = makeExcelValidationException();

    $action = TestableValidationHandlingExcelImportAction::make()
        ->throwDuringImport($exception)
        ->afterImport(function () use (&$afterImportWasCalled): void {
            $afterImportWasCalled = true;
        });

    $result = $action->runActionForTest([
        'upload' => 'people.xlsx',
    ], new PipelineLivewire);

    expect($result)->toBeTrue()
        ->and($afterImportWasCalled)->toBeTrue()
        ->and($action->handledException)->toBe($exception)
        ->and(session('import_result'))->toBe([
            'status' => 0,
            'errMsg' => '第 2 列 email：Email is required.',
        ]);
});

it('continues to bubble exceptions that custom handlers do not handle', function () {
    $action = TestableValidationHandlingExcelImportAction::make()
        ->throwDuringImport(new RuntimeException('Import crashed.'));

    $action->runActionForTest([
        'upload' => 'people.xlsx',
    ], new PipelineLivewire);
})->throws(RuntimeException::class, 'Import crashed.');

it('keeps stopped imports on the default handler', function () {
    $action = TestableStoppedExcelImportAction::make()
        ->stopWith(new ImportStoppedException('Please review the file.', 'warning'));

    $result = $action->runActionForTest([
        'upload' => 'people.xlsx',
    ], new PipelineLivewire);

    expect($result)->toBeFalse();
});

it('halts stopped imports when the stopped type is error', function () {
    $action = TestableStoppedExcelImportAction::make()
        ->stopWith(new ImportStoppedException('Import failed.', 'error'));

    $action->runActionForTest([
        'upload' => 'people.xlsx',
    ], new PipelineLivewire);
})->throws(Halt::class);

it('runs relationship imports through the same pipeline', function () {
    $afterImportWasCalled = false;

    $action = TestablePipelineExcelImportRelationshipAction::make()
        ->model(PipelineModel::class)
        ->afterImport(function () use (&$afterImportWasCalled): void {
            $afterImportWasCalled = true;
        });

    $result = $action->runActionForTest([
        'upload' => 'people.xlsx',
    ], new PipelineLivewire);

    expect($result)->toBeTrue()
        ->and($afterImportWasCalled)->toBeTrue()
        ->and($action->importObjectClass)->toBe(DefaultRelationshipImport::class);
});

function makeExcelValidationException(): ExcelValidationException
{
    $validator = Validator::make([], [
        'email' => ['required'],
    ]);

    return new ExcelValidationException(
        new IlluminateValidationException($validator),
        [
            new Failure(2, 'email', ['Email is required.']),
        ],
    );
}

class PipelineRecorder
{
    public array $events = [];
}

class TestablePipelineExcelImportAction extends ExcelImportAction
{
    protected PipelineRecorder $recorder;

    public function recorder(PipelineRecorder $recorder): static
    {
        $this->recorder = $recorder;

        return $this;
    }

    public function runActionForTest(array $data, mixed $livewire): bool
    {
        $this->action('importData');

        return ($this->getActionFunction())($data, $livewire);
    }

    protected function runImport(object $importObject, array $data): void
    {
        $this->guardImportUpload($data['upload'] ?? null);
        $this->recorder->events[] = 'run';

        if (method_exists($importObject, 'collection')) {
            $importObject->collection(collect([
                collect(['email' => 'person@example.com']),
                collect(['email' => 'other@example.com']),
            ]));
        }
    }
}

class TestableValidationHandlingExcelImportAction extends ExcelImportAction
{
    public ?Throwable $handledException = null;

    protected ?Throwable $exception = null;

    public function throwDuringImport(Throwable $exception): static
    {
        $this->exception = $exception;

        return $this;
    }

    public function runActionForTest(array $data, mixed $livewire): bool
    {
        $this->action('importData');

        return ($this->getActionFunction())($data, $livewire);
    }

    protected function runImport(object $importObject, array $data): void
    {
        throw $this->exception ?? new RuntimeException('Import crashed.');
    }

    protected function handleImportException(Throwable $exception, array $data, mixed $livewire, object $importObject): bool
    {
        $this->handledException = $exception;

        if ($exception instanceof ExcelValidationException) {
            $failure = $exception->failures()[0];

            session()->put('import_result', [
                'status' => 0,
                'errMsg' => sprintf(
                    '第 %d 列 %s：%s',
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

class TestableStoppedExcelImportAction extends ExcelImportAction
{
    protected ?ImportStoppedException $exception = null;

    public function stopWith(ImportStoppedException $exception): static
    {
        $this->exception = $exception;

        return $this;
    }

    public function runActionForTest(array $data, mixed $livewire): bool
    {
        $this->action('importData');

        return ($this->getActionFunction())($data, $livewire);
    }

    protected function runImport(object $importObject, array $data): void
    {
        throw $this->exception ?? new ImportStoppedException('Import stopped.', 'warning');
    }
}

class TestablePipelineExcelImportRelationshipAction extends ExcelImportRelationshipAction
{
    public ?string $importObjectClass = null;

    public function runActionForTest(array $data, mixed $livewire): bool
    {
        $this->action('importData');

        return ($this->getActionFunction())($data, $livewire);
    }

    protected function runImport(object $importObject, array $data): void
    {
        $this->guardImportUpload($data['upload'] ?? null);
        $this->importObjectClass = $importObject::class;
    }
}

class PipelineLivewire
{
    public function getModel(): string
    {
        return PipelineModel::class;
    }
}

class PipelineModel extends Model
{
    protected $guarded = [];
}
