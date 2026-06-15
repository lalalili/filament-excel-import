<?php

namespace EightyNine\ExcelImport\Concerns;

use Closure;
use EightyNine\ExcelImport\Support\PreviewRowsImport;
use EightyNine\ExcelImport\ValidationImport;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use InvalidArgumentException;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

trait HasUploadForm
{
    protected ?array $beforeUploadFieldFormFields = null;

    protected ?array $afterUploadFieldFormFields = null;

    protected ?string $disk = null;

    protected ?Closure $uploadField = null;

    protected ?Closure $afterValidationMutator = null;

    protected ?Closure $beforeValidationMutator = null;

    protected bool $shouldRetainBeforeValidationMutation = false;

    protected string | Closure $visibility = 'public';

    protected array $validationRules = [];

    protected array $fileRules = [];

    protected array $mimeTypeMap = [];

    protected int | string | null $maxFileSize = null;

    protected bool $queueImport = false;

    protected int $chunkSize = 1000;

    protected ?int $previewRows = null;

    protected array | Closure | null $columnMapping = null;

    protected bool $validate = false;

    public function mutateBeforeValidationUsing(Closure $closure, $shouldRetainBeforeValidationMutation = false): static
    {
        $this->beforeValidationMutator = $closure;
        $this->shouldRetainBeforeValidationMutation = $shouldRetainBeforeValidationMutation;

        return $this;
    }

    public function mutateAfterValidationUsing(Closure $closure): static
    {
        $this->afterValidationMutator = $closure;

        return $this;
    }

    public function validateUsing(array $rules): static
    {
        $this->validationRules = $rules;
        $this->validate = true;

        return $this;
    }

    public function fileRules(array $rules): static
    {
        $this->fileRules = $rules;

        return $this;
    }

    public function maxFileSize(int | string $size): static
    {
        $this->parseMaxFileSizeInKilobytes($size);

        $this->maxFileSize = $size;

        return $this;
    }

    public function mimeTypeMap(array $map): static
    {
        $this->mimeTypeMap = $map;

        return $this;
    }

    public function queueImport(bool $condition = true): static
    {
        $this->queueImport = $condition;

        return $this;
    }

    public function chunkSize(int $size): static
    {
        if ($size < 1) {
            throw new InvalidArgumentException('Chunk size must be at least 1.');
        }

        $this->chunkSize = $size;

        return $this;
    }

    public function previewRows(int $limit = 10): static
    {
        if ($limit < 1) {
            throw new InvalidArgumentException('Preview rows must be at least 1.');
        }

        $this->previewRows = $limit;

        return $this;
    }

    public function columnMapping(array | Closure $mapping): static
    {
        $this->columnMapping = $mapping;

        return $this;
    }

    public function uploadField(Closure $closure): static
    {
        $this->uploadField = $closure;

        return $this;
    }

    private function addField(Field $field, bool $isAfterUpload = false)
    {
        if ($isAfterUpload) {
            $this->afterUploadFieldFormFields[] = $field;
        } else {
            $this->beforeUploadFieldFormFields[] = $field;
        }

        return $this;
    }

    public function beforeUploadField(array $fields): static
    {
        foreach ($fields as $field) {
            $this->addField($field, false);
        }

        return $this;
    }

    public function afterUploadField(array $fields): static
    {
        foreach ($fields as $field) {
            $this->addField($field, true);
        }

        return $this;
    }

    protected function getDefaultForm(): array
    {
        $formFields = $this->beforeUploadFieldFormFields ?? [];

        $formFields[] = $this->uploadField ?
            call_user_func($this->uploadField, $this->getUploadField()) :
            $this->getUploadField();

        if ($this->previewRows !== null && $this->previewRows > 0) {
            $formFields[] = TextEntry::make('excel_preview')
                ->label(__('excel-import::excel-import.preview_rows'))
                ->state(fn (Get $get): HtmlString => $this->previewUploadedFile($get('upload')));
        }

        if ($this->afterUploadFieldFormFields) {
            $formFields = array_merge($formFields, $this->afterUploadFieldFormFields);
        }

        return $formFields;
    }

    public function disk(string $disk): static
    {
        $this->disk = $disk;

        return $this;
    }

    public function visibility(string | Closure | null $visibility): static
    {
        $this->visibility = $visibility;

        return $this;
    }

    protected function getUploadField()
    {
        $fileUpload = FileUpload::make('upload')
            ->acceptedFileTypes($this->acceptedFileTypes)
            ->label(function ($livewire) {
                if (! method_exists($livewire, 'getTable')) {
                    return __('excel-import::excel-import.excel_data');
                }

                return str($livewire->getTable()->getPluralModelLabel())->title() . ' ' . __('excel-import::excel-import.excel_data');
            })
            ->default(1)
            ->storeFiles($this->queueImport || $this->storeFiles)
            ->disk(fn () => $this->uploadDisk())
            ->visibility($this->visibility)
            ->rules($this->validationRules())
            ->columns()
            ->required();

        if ($this->previewRows !== null && $this->previewRows > 0) {
            $fileUpload->live();
        }

        if ($this->maxFileSize !== null) {
            $fileUpload->maxSize($this->maxFileSizeInKilobytes());
        }

        if ($this->mimeTypeMap !== []) {
            $fileUpload->mimeTypeMap($this->mimeTypeMap);
        }

        if (filled($this->sampleData)) {
            $fileUpload->hintAction(
                $this->getSampleExcelButton()
            );
        }

        return $fileUpload;
    }

    public function validationRules(): array
    {
        $rules = $this->fileRules;
        if ($this->validate) {
            $rules[] = fn (): Closure => function (string $attribute, $value, Closure $fail) {
                Excel::import(
                    new ValidationImport(
                        $fail,
                        $this->validationRules,
                        $this->beforeValidationMutator
                    ),
                    $value
                );
            };
        }

        return $rules;
    }

    protected function uploadDisk(): string
    {
        return $this->disk ?: (config('excel-import.upload_disk') ?: config('filesystems.default'));
    }

    protected function importDisk(): ?string
    {
        if ($this->disk !== null) {
            return $this->disk;
        }

        return ($this->storeFiles || $this->queueImport) ? $this->uploadDisk() : null;
    }

    protected function previewUploadedFile(mixed $upload): HtmlString
    {
        if (blank($upload)) {
            return new HtmlString('<p class="text-sm text-gray-500 dark:text-gray-400">' . e(__('excel-import::excel-import.preview_waiting_for_upload')) . '</p>');
        }

        try {
            $rows = $this->readPreviewRows($upload);
        } catch (Throwable) {
            return new HtmlString('<p class="text-sm text-danger-600 dark:text-danger-400">' . e(__('excel-import::excel-import.preview_unavailable')) . '</p>');
        }

        if ($rows->isEmpty()) {
            return new HtmlString('<p class="text-sm text-gray-500 dark:text-gray-400">' . e(__('excel-import::excel-import.preview_empty')) . '</p>');
        }

        return new HtmlString($this->renderPreviewTable($rows));
    }

    protected function readPreviewRows(mixed $upload): Collection
    {
        $upload = $this->normalizeUpload($upload);

        if (blank($upload)) {
            return collect();
        }

        $disk = $this->isTemporaryUpload($upload) ? null : $this->importDisk();

        $sheets = $disk === null
            ? Excel::toCollection(new PreviewRowsImport($this->previewRows ?? 10), $upload)
            : Excel::toCollection(new PreviewRowsImport($this->previewRows ?? 10), $upload, $disk);

        return $sheets->first() ?? collect();
    }

    protected function normalizeUpload(mixed $upload): mixed
    {
        if (is_array($upload)) {
            return reset($upload) ?: null;
        }

        return $upload;
    }

    protected function isTemporaryUpload(mixed $upload): bool
    {
        return is_object($upload) && method_exists($upload, 'getRealPath');
    }

    protected function renderPreviewTable(Collection $rows): string
    {
        $headers = $rows
            ->flatMap(fn (Collection | array $row): array => array_keys($row instanceof Collection ? $row->toArray() : $row))
            ->unique()
            ->values();

        $headerCells = $headers
            ->map(fn (string | int $header): string => '<th class="px-2 py-1 text-left font-medium text-gray-700 dark:text-gray-200">' . e((string) $header) . '</th>')
            ->implode('');

        $bodyRows = $rows
            ->map(function (Collection | array $row) use ($headers): string {
                $row = $row instanceof Collection ? $row->toArray() : $row;

                $cells = $headers
                    ->map(fn (string | int $header): string => '<td class="px-2 py-1 text-gray-600 dark:text-gray-300">' . e((string) ($row[$header] ?? '')) . '</td>')
                    ->implode('');

                return '<tr class="border-t border-gray-200 dark:border-gray-700">' . $cells . '</tr>';
            })
            ->implode('');

        return '<div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">'
            . '<table class="min-w-full text-sm">'
            . '<thead class="bg-gray-50 dark:bg-gray-800"><tr>' . $headerCells . '</tr></thead>'
            . '<tbody class="bg-white dark:bg-gray-900">' . $bodyRows . '</tbody>'
            . '</table>'
            . '</div>';
    }

    private function maxFileSizeInKilobytes(): int
    {
        return $this->parseMaxFileSizeInKilobytes($this->maxFileSize);
    }

    private function parseMaxFileSizeInKilobytes(int | string | null $size): int
    {
        if (is_int($size)) {
            if ($size < 1) {
                throw new InvalidArgumentException('Max file size must be at least 1 KB.');
            }

            return $size;
        }

        $value = trim(mb_strtolower((string) $size));

        if (is_numeric($value)) {
            return $this->validateMaxFileSize((int) ceil((float) $value));
        }

        if (str_ends_with($value, 'kb')) {
            return $this->validateMaxFileSize((int) ceil((float) trim(substr($value, 0, -2))));
        }

        if (str_ends_with($value, 'mb')) {
            return $this->validateMaxFileSize((int) ceil((float) trim(substr($value, 0, -2)) * 1024));
        }

        if (str_ends_with($value, 'gb')) {
            return $this->validateMaxFileSize((int) ceil((float) trim(substr($value, 0, -2)) * 1024 * 1024));
        }

        throw new InvalidArgumentException('Max file size must be a positive number of KB or use a kb, mb, or gb suffix.');
    }

    private function validateMaxFileSize(int $size): int
    {
        if ($size < 1) {
            throw new InvalidArgumentException('Max file size must be at least 1 KB.');
        }

        return $size;
    }
}
