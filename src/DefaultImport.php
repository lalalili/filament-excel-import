<?php

namespace EightyNine\ExcelImport;

use Closure;
use EightyNine\ExcelImport\Contracts\HasImportResult;
use EightyNine\ExcelImport\Support\ImportResult;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class DefaultImport implements HasImportResult, ToCollection, WithHeadingRow
{
    protected array $additionalData = [];

    protected array $customImportData = [];

    protected ?Closure $collectionMethod = null;

    protected ?Closure $afterValidationMutator = null;

    protected array | Closure | null $columnMapping = null;

    protected ImportResult $importResult;

    public function __construct(
        public string $model,
        public array $attributes = []
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
                $this->afterValidationMutator
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
                $this->model::create($data);
                $created++;
            }

            $this->importResult = ImportResult::created($created);
        }

        return $collection;
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
