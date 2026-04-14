<?php

namespace EightyNine\ExcelImport;

use Closure;
use EightyNine\ExcelImport\Exceptions\ImportStoppedException;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class EnhancedDefaultRelationshipImport implements ToCollection, WithHeadingRow
{
    protected array $customImportData = [];

    protected ?Closure $collectionMethod = null;

    protected ?Closure $afterValidationMutator = null;

    public function __construct(
        public string $model,
        public array $attributes = [],
        protected array $additionalData = [],
        public mixed $ownerRecord = null,
        public mixed $relationship = null,
        public ?Table $table = null
    ) {}

    public function setAdditionalData(array $additionalData): void
    {
        $this->additionalData = $additionalData;
    }

    public function setCustomImportData(array $customImportData): void
    {
        $this->customImportData = $customImportData;
    }

    public function setCollectionMethod(Closure $closure): void
    {
        $this->collectionMethod = $closure;
    }

    public function setAfterValidationMutator(Closure $closure): void
    {
        $this->afterValidationMutator = $closure;
    }

    /**
     * Stop the import process and return an error message to the frontend
     */
    protected function stopImportWithError(string $message): void
    {
        throw new ImportStoppedException($message, 'error');
    }

    /**
     * Stop the import process and return a warning message to the frontend
     */
    protected function stopImportWithWarning(string $message): void
    {
        throw new ImportStoppedException($message, 'warning');
    }

    /**
     * Stop the import process and return an info message to the frontend
     */
    protected function stopImportWithInfo(string $message): void
    {
        throw new ImportStoppedException($message, 'info');
    }

    /**
     * Stop the import process and return a success message to the frontend
     */
    protected function stopImportWithSuccess(string $message): void
    {
        throw new ImportStoppedException($message, 'success');
    }

    /**
     * Validate headers and stop import if validation fails
     */
    protected function validateHeaders(array $expectedHeaders, Collection $collection): void
    {
        if ($collection->isEmpty()) {
            $this->stopImportWithError(__('excel-import::excel-import.file_empty_error'));
        }

        $firstRow = $collection->first();
        if (! $firstRow) {
            $this->stopImportWithError(__('excel-import::excel-import.header_read_error'));
        }

        $actualHeaders = array_keys(is_array($firstRow) ? $firstRow : $firstRow->toArray());
        $missingHeaders = array_diff($expectedHeaders, $actualHeaders);

        if (! empty($missingHeaders)) {
            $this->stopImportWithError(
                __('excel-import::excel-import.missing_headers_error', [
                    'missing' => implode(', ', $missingHeaders),
                    'expected' => implode(', ', $expectedHeaders),
                ])
            );
        }
    }

    /**
     * Perform custom validation and stop import if validation fails
     */
    protected function validateCustomCondition(bool $condition, string $errorMessage): void
    {
        if (! $condition) {
            $this->stopImportWithError($errorMessage);
        }
    }

    public function collection(Collection $collection)
    {
        // Allow custom validation before processing
        $this->beforeCollection($collection);

        if (is_callable($this->collectionMethod)) {
            $collection = call_user_func(
                $this->collectionMethod,
                $this->model,
                $collection,
                $this->additionalData,
                $this->afterValidationMutator,
                $this->relationship,
                $this->table
            );
        } else {
            foreach ($collection as $row) {

                $data = $row->toArray();
                if (filled($this->additionalData)) {
                    $data = array_merge($data, $this->additionalData);
                }

                if ($this->afterValidationMutator) {
                    $data = call_user_func(
                        $this->afterValidationMutator,
                        $data
                    );
                }

                // Allow custom validation before creating each record
                $this->beforeCreateRecord($data, $row);

                // insert the relation data
                $pivotData = [];

                if ($this->relationship instanceof BelongsToMany) {
                    $pivotColumns = $this->relationship->getPivotColumns();

                    $pivotData = Arr::only($data, $pivotColumns);
                    $data = Arr::except($data, $pivotColumns);
                }

                $record = $this->makeRecord($data);

                if (
                    (! $this->relationship) ||
                    $this->relationship instanceof HasManyThrough
                ) {
                    $record->save();
                    $this->afterCreateRecord($data, $row, $record);

                    continue;
                }

                if ($this->relationship instanceof BelongsToMany) {
                    $this->relationship->save($record, $pivotData);
                    $this->afterCreateRecord($data, $row, $record);

                    continue;
                }

                $this->relationship->save($record);
                $this->afterCreateRecord($data, $row, $record);
            }
        }

        // Allow custom actions after processing the entire collection
        $this->afterCollection($collection);

        return $collection;
    }

    /**
     * Override this method in your custom import class to perform validation
     * before processing the collection
     */
    protected function beforeCollection(Collection $collection): void
    {
        // Override in custom import classes
    }

    /**
     * Override this method in your custom import class to perform validation
     * before creating each record
     */
    protected function beforeCreateRecord(array $data, $row): void
    {
        // Override in custom import classes
    }

    /**
     * Override this method in your custom import class to perform actions
     * after creating each record
     */
    protected function afterCreateRecord(array $data, $row, $record = null): void
    {
        // Override in custom import classes
    }

    /**
     * Override this method in your custom import class to perform actions
     * after processing the entire collection
     */
    protected function afterCollection(Collection $collection): void
    {
        // Override in custom import classes
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function makeRecord(array $data): Model
    {
        $translatableContentDriver = $this->table?->getLivewire()?->makeFilamentTranslatableContentDriver();

        if ($translatableContentDriver) {
            return $translatableContentDriver->makeRecord($this->model, $data);
        }

        $record = new $this->model;
        $record->fill($data);

        return $record;
    }
}
