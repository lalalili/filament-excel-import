<?php

namespace EightyNine\ExcelImport\Concerns;

use Closure;
use EightyNine\ExcelImport\Contracts\HasImportResult;
use EightyNine\ExcelImport\DefaultImport;
use EightyNine\ExcelImport\EnhancedDefaultImport;
use EightyNine\ExcelImport\Exceptions\ImportStoppedException;
use EightyNine\ExcelImport\QueuedDefaultImport;
use EightyNine\ExcelImport\QueuedEnhancedDefaultImport;
use EightyNine\ExcelImport\Support\ImportResult;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use InvalidArgumentException;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Facades\Excel;

trait HasExcelImportAction
{
    use BelongsToTable;
    use CanCustomiseActionSetup;
    use HasCustomCollectionMethod;
    use HasFormActionHooks;
    use HasSampleExcelFile;
    use HasUploadForm;

    protected array $importClassAttributes = [];

    public function use(?string $class = null, ...$attributes): static
    {
        $this->importClass = $class ?: DefaultImport::class;
        $this->importClassAttributes = $attributes;

        return $this;
    }

    public static function getDefaultName(): ?string
    {
        return 'import';
    }

    public function action(Closure | string | null $action): static
    {
        if ($action !== 'importData') {
            throw new \Exception('You\'re unable to override the action for this plugin');
        }

        $this->action = $this->importData();

        return $this;
    }

    protected function setUp(): void
    {
        parent::setUp();

        if ($this->isRelationshipImport()) {
            $this->shouldSendImportSuccessNotification = false;
        }

        $this->icon('heroicon-o-arrow-down-tray')
            ->color('warning')
            ->schema(fn () => $this->getDefaultForm())
            ->modalIcon('heroicon-o-arrow-down-tray')
            ->color('success')
            ->modalWidth('md')
            ->modalAlignment('center')
            ->modalHeading(fn ($livewire) => __('excel-import::excel-import.import_action_heading'))
            ->modalDescription(__('excel-import::excel-import.import_action_description'))
            ->modalFooterActionsAlignment('right')
            ->closeModalByClickingAway(false)
            ->action('importData');
    }

    private function importData(): Closure
    {
        return function (array $data, $livewire): bool {
            if (is_callable($this->beforeImportClosure)) {
                call_user_func($this->beforeImportClosure, $data, $livewire, $this);
            }

            $importObject = $this->makeImportObject($livewire);
            $this->configureImportObject($importObject);

            try {
                $this->runImport($importObject, $data);

                if ((! $this->queueImport) && is_callable($this->afterImportClosure)) {
                    call_user_func($this->afterImportClosure, $data, $livewire);
                }

                if ((! $this->queueImport) && is_callable($this->afterImportResultClosure)) {
                    $result = $this->resolveImportResult($importObject);
                    call_user_func($this->afterImportResultClosure, $result, $data, $livewire, $this);
                }

                if ($this->shouldSendImportSuccessNotification) {
                    $this->sendSuccessfulImportNotification();
                }

                return true;
            } catch (ImportStoppedException $e) {
                $this->sendStoppedImportNotification($e);

                if ($e->getType() === 'error') {
                    $this->halt();
                }

                return false;
            }
        };
    }

    protected function isRelationshipImport(): bool
    {
        return false;
    }

    protected function makeImportObject(mixed $livewire): object
    {
        $importClass = $this->resolveImportClass();

        if ($this->isRelationshipImport()) {
            return new $importClass(
                $this->getModel(),
                $this->importClassAttributes,
                $this->additionalData,
                method_exists($livewire, 'getOwnerRecord') ? $livewire->getOwnerRecord() : null,
                method_exists($livewire, 'getRelationship') ? $livewire->getRelationship() : null,
                method_exists($livewire, 'getTable') ? $livewire->getTable() : null
            );
        }

        return new $importClass(
            method_exists($livewire, 'getModel') ? $livewire->getModel() : null,
            $this->importClassAttributes,
            $this->additionalData
        );
    }

    protected function resolveImportClass(): string
    {
        if (! $this->queueImport) {
            return $this->importClass;
        }

        return match ($this->importClass) {
            DefaultImport::class => QueuedDefaultImport::class,
            EnhancedDefaultImport::class => QueuedEnhancedDefaultImport::class,
            default => $this->importClass,
        };
    }

    protected function configureImportObject(object $importObject): void
    {
        if (method_exists($importObject, 'setAdditionalData') && ($this->additionalData !== [])) {
            $importObject->setAdditionalData($this->additionalData);
        }

        if (method_exists($importObject, 'setCustomImportData') && ($this->customImportData !== [])) {
            $importObject->setCustomImportData($this->customImportData);
        }

        if (method_exists($importObject, 'setCollectionMethod') && isset($this->collectionMethod)) {
            $importObject->setCollectionMethod($this->collectionMethod);
        }

        if (method_exists($importObject, 'setColumnMapping') && $this->columnMapping !== null) {
            $importObject->setColumnMapping($this->columnMapping);
        }

        if (method_exists($importObject, 'setChunkSize')) {
            $importObject->setChunkSize($this->chunkSize);
        }

        if (method_exists($importObject, 'setAfterValidationMutator') &&
           (isset($this->afterValidationMutator) || $this->shouldRetainBeforeValidationMutation)) {
            $afterValidationMutator = $this->shouldRetainBeforeValidationMutation ?
                    $this->beforeValidationMutator :
                    $this->afterValidationMutator;
            $importObject->setAfterValidationMutator($afterValidationMutator);
        }
    }

    protected function runImport(object $importObject, array $data): void
    {
        $upload = $data['upload'] ?? null;

        $this->guardImportUpload($upload);

        if ($this->queueImport) {
            $this->guardQueuedImportConfiguration();
            $this->guardQueuedImportUpload($upload);

            if (! $importObject instanceof ShouldQueue || ! $importObject instanceof WithChunkReading) {
                throw new InvalidArgumentException('Queued imports must implement ShouldQueue and WithChunkReading.');
            }

            Excel::queueImport($importObject, $this->importPath(['upload' => $upload]), $this->uploadDisk());

            return;
        }

        $path = $this->importPath(['upload' => $upload]);
        $disk = $this->importDisk();

        $disk === null
            ? Excel::import($importObject, $path)
            : Excel::import($importObject, $path, $disk);
    }

    protected function importPath(array $data): mixed
    {
        $upload = $this->normalizeUpload($data['upload']);

        if ($this->isRelationshipImport()) {
            return $upload;
        }

        return is_object($upload) && method_exists($upload, 'getRealPath')
            ? $upload->getRealPath()
            : $upload;
    }

    protected function resolveImportResult(object $importObject): ImportResult
    {
        if ($importObject instanceof HasImportResult) {
            return $importObject->getImportResult();
        }

        return ImportResult::empty();
    }

    protected function sendStoppedImportNotification(ImportStoppedException $exception): void
    {
        $notification = match ($exception->getType()) {
            'warning' => Notification::make()
                ->warning()
                ->title(__('excel-import::excel-import.import_warning'))
                ->body($exception->getUserMessage()),
            'info' => Notification::make()
                ->info()
                ->title(__('excel-import::excel-import.import_information'))
                ->body($exception->getUserMessage()),
            'success' => Notification::make()
                ->success()
                ->title(__('excel-import::excel-import.import_success'))
                ->body($exception->getUserMessage()),
            default => Notification::make()
                ->danger()
                ->title(__('excel-import::excel-import.import_failed'))
                ->body($exception->getUserMessage()),
        };

        $notification->send();
    }

    protected function sendSuccessfulImportNotification(): void
    {
        Notification::make()
            ->success()
            ->title($this->queueImport
                ? __('excel-import::excel-import.import_queued')
                : __('excel-import::excel-import.import_success'))
            ->body($this->queueImport
                ? __('excel-import::excel-import.import_queued_message')
                : __('excel-import::excel-import.import_success_message'))
            ->send();
    }

    protected function guardQueuedImportConfiguration(): void
    {
        if ($this->collectionMethod !== null) {
            throw new InvalidArgumentException('Queued imports cannot use processCollectionUsing() closures. Use a custom queueable import class instead.');
        }

        if ($this->columnMapping instanceof Closure) {
            throw new InvalidArgumentException('Queued imports cannot use closure column mappings. Use an array mapping or a custom queueable import class instead.');
        }

        if ($this->afterValidationMutator !== null || ($this->shouldRetainBeforeValidationMutation && $this->beforeValidationMutator !== null)) {
            throw new InvalidArgumentException('Queued imports cannot use validation mutator closures. Use a custom queueable import class instead.');
        }

        if (
            $this->containsClosure($this->additionalData) ||
            $this->containsClosure($this->customImportData) ||
            $this->containsClosure($this->importClassAttributes)
        ) {
            throw new InvalidArgumentException('Queued imports cannot serialize closures in import data.');
        }
    }

    protected function guardImportUpload(mixed $upload): void
    {
        if (! blank($this->normalizeUpload($upload))) {
            return;
        }

        throw new InvalidArgumentException('Import requires an uploaded file.');
    }

    protected function guardQueuedImportUpload(mixed $upload): void
    {
        if (! $this->containsTemporaryUpload($upload)) {
            return;
        }

        throw new InvalidArgumentException('Queued imports require stored upload paths. Keep the default queued upload storage or enable storeFiles().');
    }

    protected function containsClosure(mixed $value): bool
    {
        if ($value instanceof Closure) {
            return true;
        }

        if (! is_array($value)) {
            return false;
        }

        foreach ($value as $item) {
            if ($this->containsClosure($item)) {
                return true;
            }
        }

        return false;
    }

    protected function containsTemporaryUpload(mixed $value): bool
    {
        if ($this->isTemporaryUpload($value)) {
            return true;
        }

        if (! is_array($value)) {
            return false;
        }

        foreach ($value as $item) {
            if ($this->containsTemporaryUpload($item)) {
                return true;
            }
        }

        return false;
    }
}
