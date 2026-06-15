<?php

namespace EightyNine\ExcelImport\Concerns;

use Closure;
use EightyNine\ExcelImport\SampleExcelExport;
use Filament\Actions\Action;
use Maatwebsite\Excel\Facades\Excel;

trait HasSampleExcelFile
{
    protected string $sampleFileName = 'sample.xlsx';

    protected $defaultExportClass = SampleExcelExport::class;

    protected string | array | null $sampleData = null;

    protected ?string $sampleButtonLabel = null;

    protected ?Closure $actionCustomisationClosure = null;

    public function downloadSampleExcelFile()
    {
        return Excel::download(
            new $this->defaultExportClass($this->sampleData),
            $this->sampleFileName
        );
    }

    public function setSampleFileName(string $name)
    {
        $this->sampleFileName = $name;
    }

    public function setDefaultExportClass(string $class)
    {
        $this->defaultExportClass = $class;
    }

    public function setSampleData(string | array $data)
    {
        if (is_string($data)) {
            $this->sampleData = $data;

            return;
        } else {
            if (count($data) > 0 && isset($data[0]) && is_array($data[0])) {
                $this->sampleData = $data;

                return;
            } else {
                $this->sampleData = [$data];

                return;
            }
        }

    }

    public function setSampleButtonLabel(?string $label)
    {
        $this->sampleButtonLabel = $label;
    }

    public function sampleButtonLabel(?string $label): static
    {
        $this->setSampleButtonLabel($label);

        return $this;
    }

    protected function getSampleExcelButton()
    {
        $action = Action::make($this->sampleButtonLabel ?: __('excel-import::excel-import.download_sample_excel_file'));
        if (is_string($this->sampleData)) {
            $action->url($this->sampleData);
        } else {
            $action->action(fn () => $this->downloadSampleExcelFile());
        }
        if (isset($this->actionCustomisationClosure)) {
            return call_user_func($this->actionCustomisationClosure, $action);
        }

        return $action;
    }

    public function setActionCustomisationClosure(?Closure $customiseActionUsing)
    {
        $this->actionCustomisationClosure = $customiseActionUsing;
    }

    public function sampleExcel(
        array $sampleData,
        ?string $fileName = null,
        ?string $exportClass = null,
        ?string $sampleButtonLabel = null,
        ?Closure $customiseActionUsing = null
    ): static {
        if ($exportClass !== null && $sampleButtonLabel === null && $this->looksLikeSampleButtonLabel($exportClass)) {
            $sampleButtonLabel = $exportClass;
            $exportClass = null;
        }

        $this->setSampleData($sampleData);
        $this->setSampleFileName($fileName ?: $this->sampleFileName);
        $this->setDefaultExportClass($exportClass ?: $this->defaultExportClass);
        $this->setSampleButtonLabel($sampleButtonLabel ?: $this->sampleButtonLabel);
        $this->setActionCustomisationClosure($customiseActionUsing);

        return $this;
    }

    public function sampleColumns(
        array $columns,
        ?string $fileName = null,
        ?string $sampleButtonLabel = null,
        ?Closure $customiseActionUsing = null
    ): static {
        return $this->sampleExcel(
            sampleData: [array_fill_keys($columns, '')],
            fileName: $fileName,
            sampleButtonLabel: $sampleButtonLabel,
            customiseActionUsing: $customiseActionUsing,
        );
    }

    public function sampleFileExcel(
        string $url,
        ?string $sampleButtonLabel = null,
        ?Closure $customiseActionUsing = null
    ): static {
        $this->setSampleData($url);
        $this->setSampleButtonLabel($sampleButtonLabel ?: $this->sampleButtonLabel);
        $this->setActionCustomisationClosure($customiseActionUsing);

        return $this;
    }

    private function looksLikeSampleButtonLabel(string $value): bool
    {
        return ! class_exists($value) && ! str_contains($value, '\\');
    }
}
