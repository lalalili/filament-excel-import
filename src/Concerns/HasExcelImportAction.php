<?php

namespace EightyNine\ExcelImport\Concerns;

use Closure;
use EightyNine\ExcelImport\Contracts\HasImportResult;
use EightyNine\ExcelImport\DefaultImport;
use EightyNine\ExcelImport\EnhancedDefaultImport;
use EightyNine\ExcelImport\Events\ImportQueued;
use EightyNine\ExcelImport\Exceptions\ImportStoppedException;
use EightyNine\ExcelImport\QueuedDefaultImport;
use EightyNine\ExcelImport\QueuedEnhancedDefaultImport;
use EightyNine\ExcelImport\Support\FailedRowsCsvExporter;
use EightyNine\ExcelImport\Support\FailedRowsExport;
use EightyNine\ExcelImport\Support\ImportResult;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

trait HasExcelImportAction
{
    use BelongsToTable;
    use CanCustomiseActionSetup;
    use HasCustomCollectionMethod;
    use HasFormActionHooks;
    use HasSampleExcelFile;
    use HasUploadForm;

    protected array $importClassAttributes = [];

    protected bool $downloadFailedRows = false;

    protected ?string $failedRowsDisk = null;

    protected string $failedRowsDirectory = 'excel-import/failed-rows';

    protected string | Closure $failedRowsFileName = 'failed-rows.xlsx';

    protected string $failedRowsFormat = 'xlsx';

    protected bool $failedRowsFormatWasConfigured = false;

    public function use(?string $class = null, ...$attributes): static
    {
        $this->importClass = $class ?: DefaultImport::class;
        $this->importClassAttributes = $attributes;

        return $this;
    }

    public function downloadFailedRows(bool $condition = true): static
    {
        $this->downloadFailedRows = $condition;

        return $this;
    }

    public function failedRowsDisk(?string $disk): static
    {
        $this->failedRowsDisk = $disk;

        return $this;
    }

    public function failedRowsDirectory(string $directory): static
    {
        $this->failedRowsDirectory = $this->sanitizeFailedRowsDirectory($directory);

        return $this;
    }

    public function failedRowsFileName(string | Closure $name): static
    {
        if (is_string($name)) {
            $this->sanitizeFailedRowsFileName($name);
        }

        $this->failedRowsFileName = $name;

        return $this;
    }

    public function failedRowsFormat(string $format): static
    {
        $this->failedRowsFormat = $this->sanitizeFailedRowsFormat($format);
        $this->failedRowsFormatWasConfigured = true;

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

    protected function importData(): Closure
    {
        return fn (array $data, $livewire): bool => $this->handleImport($data, $livewire);
    }

    protected function handleImport(array $data, mixed $livewire): bool
    {
        $this->callBeforeImport($data, $livewire);

        $importObject = $this->makeImportObject($livewire);
        $this->configureImportObject($importObject);

        try {
            $this->runImport($importObject, $data);

            return $this->completeImport($importObject, $data, $livewire);
        } catch (Throwable $exception) {
            return $this->handleImportException($exception, $data, $livewire, $importObject);
        }
    }

    protected function callBeforeImport(array $data, mixed $livewire): void
    {
        if (is_callable($this->beforeImportClosure)) {
            call_user_func($this->beforeImportClosure, $data, $livewire, $this);
        }
    }

    protected function callAfterImport(array $data, mixed $livewire): void
    {
        if (is_callable($this->afterImportClosure)) {
            call_user_func($this->afterImportClosure, $data, $livewire);
        }
    }

    protected function completeImport(object $importObject, array $data, mixed $livewire): bool
    {
        if (! $this->queueImport) {
            $this->callAfterImport($data, $livewire);
            $this->callAfterImportResult($importObject, $data, $livewire);
        }

        if ($this->shouldSendImportSuccessNotification) {
            $this->sendSuccessfulImportNotification();
        }

        return true;
    }

    protected function callAfterImportResult(object $importObject, array $data, mixed $livewire): void
    {
        if (! is_callable($this->afterImportResultClosure)) {
            return;
        }

        $result = $this->resolveImportResult($importObject);
        $result = $this->exportFailedRowsIfEnabled($result, $data);

        call_user_func($this->afterImportResultClosure, $result, $data, $livewire, $this);
    }

    protected function handleImportException(Throwable $exception, array $data, mixed $livewire, object $importObject): bool
    {
        if ($exception instanceof ImportStoppedException) {
            return $this->handleStoppedImport($exception);
        }

        throw $exception;
    }

    protected function handleStoppedImport(ImportStoppedException $exception): bool
    {
        $this->sendStoppedImportNotification($exception);

        if ($exception->getType() === 'error') {
            $this->halt();
        }

        return false;
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

            $path = $this->importPath(['upload' => $upload]);
            $disk = $this->uploadDisk();

            Excel::queueImport($importObject, $path, $disk);
            Event::dispatch(new ImportQueued($importObject, $path, $disk));

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

    protected function exportFailedRowsIfEnabled(ImportResult $result, array $data): ImportResult
    {
        if (! $this->downloadFailedRows || $this->queueImport || $result->errors === []) {
            return $result;
        }

        $disk = $this->resolvedFailedRowsDisk();
        ['downloadName' => $downloadName, 'format' => $format] = $this->resolvedFailedRowsExportOptions($result, $data);
        $path = $this->failedRowsPath($downloadName);

        if ($format === 'csv') {
            Storage::disk($disk)->put($path, (new FailedRowsCsvExporter)->export($result));
        } else {
            Excel::store(new FailedRowsExport($result->errors), $path, $disk, ExcelWriter::XLSX);
        }

        return $result->withFailedRows($path, $disk, $downloadName);
    }

    protected function resolvedFailedRowsDisk(): string
    {
        return $this->failedRowsDisk ?? $this->uploadDisk();
    }

    /**
     * @return array{downloadName: string, format: string}
     */
    protected function resolvedFailedRowsExportOptions(ImportResult $result, array $data): array
    {
        $name = $this->failedRowsFileName instanceof Closure
            ? call_user_func($this->failedRowsFileName, $result, $data, $this)
            : $this->failedRowsFileName;

        $name = $this->sanitizeFailedRowsFileName((string) $name);
        $format = $this->resolvedFailedRowsFormat($name);

        return [
            'downloadName' => $this->withFailedRowsExtension($name, $format),
            'format' => $format,
        ];
    }

    protected function failedRowsPath(string $downloadName): string
    {
        $fileName = $this->sanitizeFailedRowsFileName($downloadName);
        $directory = $this->sanitizeFailedRowsDirectory($this->failedRowsDirectory);

        return $directory . '/' . $fileName;
    }

    protected function resolvedFailedRowsFormat(string $fileName): string
    {
        if ($this->failedRowsFormatWasConfigured) {
            return $this->failedRowsFormat;
        }

        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (in_array($extension, ['csv', 'xlsx'], true)) {
            return $extension;
        }

        return $this->failedRowsFormat;
    }

    protected function withFailedRowsExtension(string $fileName, string $format): string
    {
        $baseName = preg_replace('/\.(csv|xlsx)$/i', '', $fileName) ?? $fileName;

        return Str::endsWith(strtolower($fileName), '.' . $format)
            ? $fileName
            : $baseName . '.' . $format;
    }

    protected function sanitizeFailedRowsFormat(string $format): string
    {
        $format = strtolower(trim($format));

        if (! in_array($format, ['csv', 'xlsx'], true)) {
            throw new InvalidArgumentException('Failed rows format must be csv or xlsx.');
        }

        return $format;
    }

    protected function sanitizeFailedRowsDirectory(string $directory): string
    {
        $directory = trim($directory);

        if ($directory === '') {
            throw new InvalidArgumentException('Failed rows directory cannot be empty.');
        }

        if (
            str_starts_with($directory, '/')
            || str_contains($directory, '\\')
            || preg_match('/^[A-Za-z]:[\/\\\\]/', $directory) === 1
        ) {
            throw new InvalidArgumentException('Failed rows directory must be a relative path.');
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $directory) === 1) {
            throw new InvalidArgumentException('Failed rows directory cannot contain control characters.');
        }

        $directory = trim($directory, '/');

        foreach (explode('/', $directory) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new InvalidArgumentException('Failed rows directory must be a relative path without dot segments.');
            }
        }

        return $directory;
    }

    protected function sanitizeFailedRowsFileName(string $fileName): string
    {
        $fileName = trim($fileName);

        if ($fileName === '') {
            throw new InvalidArgumentException('Failed rows file name cannot be empty.');
        }

        if (preg_match('/[\/\\\\\x00-\x1F\x7F]/', $fileName) === 1 || str_contains($fileName, '..')) {
            throw new InvalidArgumentException('Failed rows file name must not contain paths or control characters.');
        }

        return $fileName;
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
