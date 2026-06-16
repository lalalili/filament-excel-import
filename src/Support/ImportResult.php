<?php

namespace EightyNine\ExcelImport\Support;

class ImportResult
{
    /**
     * @param  list<array<string, mixed>>  $errors
     */
    public function __construct(
        public readonly int $created = 0,
        public readonly int $updated = 0,
        public readonly int $skipped = 0,
        public readonly int $failed = 0,
        public readonly array $errors = [],
        public readonly ?string $failedRowsPath = null,
        public readonly ?string $failedRowsDisk = null,
        public readonly ?string $failedRowsDownloadName = null,
    ) {}

    public static function empty(): self
    {
        return new self;
    }

    public static function created(int $count): self
    {
        return new self(created: $count);
    }

    /**
     * @param  list<array<string, mixed>>  $errors
     */
    public static function make(
        int $created = 0,
        int $updated = 0,
        int $skipped = 0,
        int $failed = 0,
        array $errors = [],
        ?string $failedRowsPath = null,
        ?string $failedRowsDisk = null,
        ?string $failedRowsDownloadName = null,
    ): self {
        return new self(
            created: $created,
            updated: $updated,
            skipped: $skipped,
            failed: $failed,
            errors: $errors,
            failedRowsPath: $failedRowsPath,
            failedRowsDisk: $failedRowsDisk,
            failedRowsDownloadName: $failedRowsDownloadName,
        );
    }

    /**
     * @return array{created: int, updated: int, skipped: int, failed: int, errors: list<array<string, mixed>>, failedRowsPath: ?string, failedRowsDisk: ?string, failedRowsDownloadName: ?string}
     */
    public function toArray(): array
    {
        return [
            'created' => $this->created,
            'updated' => $this->updated,
            'skipped' => $this->skipped,
            'failed' => $this->failed,
            'errors' => $this->errors,
            'failedRowsPath' => $this->failedRowsPath,
            'failedRowsDisk' => $this->failedRowsDisk,
            'failedRowsDownloadName' => $this->failedRowsDownloadName,
        ];
    }

    public function total(): int
    {
        return $this->created + $this->updated + $this->skipped + $this->failed;
    }

    public function hasErrors(): bool
    {
        return $this->failed > 0 || $this->errors !== [];
    }

    public function withFailedRows(
        string $path,
        ?string $disk,
        string $downloadName,
    ): self {
        return new self(
            created: $this->created,
            updated: $this->updated,
            skipped: $this->skipped,
            failed: $this->failed,
            errors: $this->errors,
            failedRowsPath: $path,
            failedRowsDisk: $disk,
            failedRowsDownloadName: $downloadName,
        );
    }
}
