<?php

use EightyNine\ExcelImport\Concerns\HasSampleExcelFile;
use EightyNine\ExcelImport\SampleExcelExport;

it('keeps sample excel export class and label separate', function () {
    $action = new class
    {
        use HasSampleExcelFile;

        public function state(): array
        {
            return [
                'sampleFileName' => $this->sampleFileName,
                'defaultExportClass' => $this->defaultExportClass,
                'sampleData' => $this->sampleData,
                'sampleButtonLabel' => $this->sampleButtonLabel,
            ];
        }
    };

    $action->sampleExcel(
        sampleData: [['email' => 'person@example.com']],
        fileName: 'people.xlsx',
        exportClass: SampleExcelExport::class,
        sampleButtonLabel: 'Download template',
    );

    expect($action->state())->toMatchArray([
        'sampleFileName' => 'people.xlsx',
        'defaultExportClass' => SampleExcelExport::class,
        'sampleData' => [['email' => 'person@example.com']],
        'sampleButtonLabel' => 'Download template',
    ]);
});

it('treats a non-class third positional sample excel argument as the label', function () {
    $action = new class
    {
        use HasSampleExcelFile;

        public function state(): array
        {
            return [
                'defaultExportClass' => $this->defaultExportClass,
                'sampleButtonLabel' => $this->sampleButtonLabel,
            ];
        }
    };

    $action->sampleExcel(
        [['email' => 'person@example.com']],
        'people.xlsx',
        '下載範本',
    );

    expect($action->state())->toMatchArray([
        'defaultExportClass' => SampleExcelExport::class,
        'sampleButtonLabel' => '下載範本',
    ]);
});

it('can configure the sample button label fluently', function () {
    $action = new class
    {
        use HasSampleExcelFile;

        public function state(): array
        {
            return [
                'sampleButtonLabel' => $this->sampleButtonLabel,
            ];
        }
    };

    $action->sampleButtonLabel('下載範本');

    expect($action->state())->toBe([
        'sampleButtonLabel' => '下載範本',
    ]);
});

it('can generate sample data from a list of columns', function () {
    $action = new class
    {
        use HasSampleExcelFile;

        public function state(): array
        {
            return [
                'sampleFileName' => $this->sampleFileName,
                'sampleData' => $this->sampleData,
                'sampleButtonLabel' => $this->sampleButtonLabel,
            ];
        }
    };

    $action->sampleColumns(['email', 'name'], 'people.xlsx', '下載範本');

    expect($action->state())->toBe([
        'sampleFileName' => 'people.xlsx',
        'sampleData' => [['email' => '', 'name' => '']],
        'sampleButtonLabel' => '下載範本',
    ]);
});
