<?php

namespace EightyNine\ExcelImport\Concerns;

trait CanCustomiseActionSetup
{
    protected array $acceptedFileTypes = ['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'text/csv'];

    protected bool $storeFiles = false;

    protected bool $shouldSendImportSuccessNotification = true;

    public function acceptedFileTypes(array $types): static
    {
        $this->acceptedFileTypes = $types;

        return $this;
    }

    public function storeFiles(bool $storeFiles = true): static
    {
        $this->storeFiles = $storeFiles;

        return $this;
    }

    public function sendSuccessNotification(bool $condition = true): static
    {
        $this->shouldSendImportSuccessNotification = $condition;

        return $this;
    }
}
