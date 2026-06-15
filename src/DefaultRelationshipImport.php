<?php

namespace EightyNine\ExcelImport;

use Closure;
use EightyNine\ExcelImport\Contracts\HasImportResult;
use EightyNine\ExcelImport\Support\ImportResult;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class DefaultRelationshipImport implements HasImportResult, ToCollection, WithHeadingRow
{
    protected array $customImportData = [];

    protected ?Closure $collectionMethod = null;

    protected ?Closure $afterValidationMutator = null;

    protected array | Closure | null $columnMapping = null;

    protected ImportResult $importResult;

    public function __construct(
        public string $model,
        public array $attributes = [],
        protected array $additionalData = [],
        public mixed $ownerRecord = null,
        public mixed $relationship = null,
        public ?Table $table = null
    ) {
        $this->importResult = ImportResult::empty();
    }

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

    public function setColumnMapping(array | Closure $mapping): void
    {
        $this->columnMapping = $mapping;
    }

    public function getImportResult(): ImportResult
    {
        return $this->importResult;
    }

    public function collection(Collection $collection)
    {
        if (is_callable($this->collectionMethod)) {
            $result = call_user_func(
                $this->collectionMethod,
                $this->model,
                $collection,
                $this->additionalData,
                $this->afterValidationMutator,
                $this->relationship,
                $this->table
            );

            if ($result instanceof ImportResult) {
                $this->importResult = $result;
            } elseif ($result instanceof Collection) {
                $collection = $result;
            }
        } else {
            $created = 0;

            foreach ($collection as $row) {

                $data = $row->toArray();
                $data = $this->mapColumns($data);

                if (filled($this->additionalData)) {
                    $data = array_merge($data, $this->additionalData);
                }

                if ($this->afterValidationMutator) {
                    $data = call_user_func(
                        $this->afterValidationMutator,
                        $data
                    );
                }

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
                    $created++;

                    continue;
                }

                if ($this->relationship instanceof BelongsToMany) {
                    $this->relationship->save($record, $pivotData);
                    $created++;

                    continue;
                }

                $this->relationship->save($record);
                $created++;
            }

            $this->importResult = ImportResult::created($created);
        }

        return $collection;
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

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mapColumns(array $data): array
    {
        if ($this->columnMapping === null) {
            return $data;
        }

        if ($this->columnMapping instanceof Closure) {
            return call_user_func($this->columnMapping, $data);
        }

        $mapped = [];

        foreach ($data as $key => $value) {
            $mapped[$this->columnMapping[$key] ?? $key] = $value;
        }

        return $mapped;
    }
}
