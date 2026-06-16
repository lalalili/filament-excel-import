<?php

use EightyNine\ExcelImport\Concerns\CanCustomiseActionSetup;
use EightyNine\ExcelImport\Concerns\HasSampleExcelFile;
use EightyNine\ExcelImport\Concerns\HasUploadForm;
use Filament\Forms\Components\FileUpload;
use Filament\Infolists\Components\TextEntry;
use Illuminate\Support\HtmlString;
use Maatwebsite\Excel\ExcelServiceProvider;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

it('applies custom upload rules max size and mime type mappings', function () {
    $action = new class
    {
        use CanCustomiseActionSetup;
        use HasSampleExcelFile;
        use HasUploadForm;

        public function uploadFieldForTest(): mixed
        {
            return $this->getUploadField();
        }

        public function defaultFormForTest(): array
        {
            return $this->getDefaultForm();
        }
    };

    $field = $action
        ->fileRules(['mimes:xlsx,csv'])
        ->maxFileSize('10mb')
        ->mimeTypeMap(['csv' => 'text/csv'])
        ->uploadFieldForTest();

    expect($field->getMaxSize())->toBe(10240)
        ->and($field->getMimeTypeMap())->toBe(['csv' => 'text/csv']);

    $rules = $action->validationRules();

    expect($rules)->toContain('mimes:xlsx,csv');
});

it('parses decimal upload size strings as kilobytes', function () {
    $action = new class
    {
        use CanCustomiseActionSetup;
        use HasSampleExcelFile;
        use HasUploadForm;

        public function uploadFieldForTest(): mixed
        {
            return $this->getUploadField();
        }
    };

    $field = $action
        ->maxFileSize('1.5mb')
        ->uploadFieldForTest();

    expect($field->getMaxSize())->toBe(1536);
});

it('rejects invalid upload size strings', function () {
    $action = new class
    {
        use CanCustomiseActionSetup;
        use HasSampleExcelFile;
        use HasUploadForm;
    };

    $action->maxFileSize('large');
})->throws(InvalidArgumentException::class, 'Max file size must be a positive number of KB or use a kb, mb, or gb suffix.');

it('requires a positive upload size', function () {
    $action = new class
    {
        use CanCustomiseActionSetup;
        use HasSampleExcelFile;
        use HasUploadForm;
    };

    $action->maxFileSize(0);
})->throws(InvalidArgumentException::class, 'Max file size must be at least 1 KB.');

it('requires a positive queue chunk size', function () {
    $action = new class
    {
        use CanCustomiseActionSetup;
        use HasSampleExcelFile;
        use HasUploadForm;
    };

    $action->chunkSize(0);
})->throws(InvalidArgumentException::class, 'Chunk size must be at least 1.');

it('requires a positive preview row limit', function () {
    $action = new class
    {
        use CanCustomiseActionSetup;
        use HasSampleExcelFile;
        use HasUploadForm;
    };

    $action->previewRows(0);
})->throws(InvalidArgumentException::class, 'Preview rows must be at least 1.');

it('limits preview rows to fifty rows', function () {
    $action = new class
    {
        use CanCustomiseActionSetup;
        use HasSampleExcelFile;
        use HasUploadForm;
    };

    $action->previewRows(51);
})->throws(InvalidArgumentException::class, 'Preview rows may not exceed 50.');

it('stores uploaded files when queue imports are enabled', function () {
    $action = new class
    {
        use CanCustomiseActionSetup;
        use HasSampleExcelFile;
        use HasUploadForm;

        public function uploadFieldForTest(): mixed
        {
            return $this->getUploadField();
        }
    };

    $field = $action
        ->storeFiles(false)
        ->queueImport()
        ->uploadFieldForTest();

    expect($field->shouldStoreFiles())->toBeTrue();
});

it('stores uploaded files when store files is called without arguments', function () {
    $action = new class
    {
        use CanCustomiseActionSetup;
        use HasSampleExcelFile;
        use HasUploadForm;

        public function uploadFieldForTest(): mixed
        {
            return $this->getUploadField();
        }
    };

    $field = $action
        ->storeFiles()
        ->uploadFieldForTest();

    expect($field->shouldStoreFiles())->toBeTrue();
});

it('builds the default upload form without custom fields', function () {
    $action = new class
    {
        use CanCustomiseActionSetup;
        use HasSampleExcelFile;
        use HasUploadForm;

        public function defaultFormForTest(): array
        {
            return $this->getDefaultForm();
        }
    };

    expect($action->defaultFormForTest())->toHaveCount(1);
});

it('adds a live preview field when preview rows are enabled', function () {
    $action = new class
    {
        use CanCustomiseActionSetup;
        use HasSampleExcelFile;
        use HasUploadForm;

        public function defaultFormForTest(): array
        {
            return $this->getDefaultForm();
        }
    };

    $fields = $action->previewRows(5)->defaultFormForTest();

    expect($fields)->toHaveCount(2)
        ->and($fields[0])->toBeInstanceOf(FileUpload::class)
        ->and($fields[0]->isLive())->toBeTrue()
        ->and($fields[1])->toBeInstanceOf(TextEntry::class);
});

it('reads stored preview uploads from the configured upload disk', function () {
    config()->set('excel-import.upload_disk', 'imports');

    Excel::fake();

    $action = new class
    {
        use CanCustomiseActionSetup;
        use HasSampleExcelFile;
        use HasUploadForm;

        public function readPreviewRowsForTest(mixed $upload): void
        {
            $this->previewRows();
            $this->readPreviewRows($upload);
        }
    };

    $action->storeFiles(true);
    $action->readPreviewRowsForTest(['people.xlsx']);

    Excel::assertImported('people.xlsx', 'imports');
});

it('escapes preview table values', function () {
    $action = new class
    {
        use CanCustomiseActionSetup;
        use HasSampleExcelFile;
        use HasUploadForm;

        public function previewTableForTest(): HtmlString
        {
            return new HtmlString($this->renderPreviewTable(collect([
                collect([
                    'email' => '<script>alert("x")</script>',
                    'name' => 'Taylor',
                ]),
            ])));
        }
    };

    $html = $action->previewTableForTest()->toHtml();

    expect($html)->toContain('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;')
        ->and($html)->not->toContain('<script>');
});

it('limits preview table to five columns and stringifies nested values', function () {
    $action = new class
    {
        use CanCustomiseActionSetup;
        use HasSampleExcelFile;
        use HasUploadForm;

        public function previewTableForTest(): HtmlString
        {
            $row = [];

            foreach (range(1, 26) as $index) {
                $row["column_{$index}"] = "value_{$index}";
            }

            $row['column_2'] = ['nested' => 'value'];

            return new HtmlString($this->renderPreviewTable(collect([
                collect($row),
            ])));
        }
    };

    $html = $action->previewTableForTest()->toHtml();

    expect($html)
        ->toContain('column_5')
        ->toContain('{&quot;nested&quot;:&quot;value&quot;}')
        ->not->toContain('column_6')
        ->not->toContain('value_6');
});

it('previews the first visible worksheet and ignores hidden worksheets', function () {
    app()->register(ExcelServiceProvider::class);

    $path = tempnam(sys_get_temp_dir(), 'preview-visible-sheet-') . '.xlsx';

    $spreadsheet = new Spreadsheet;
    $hiddenSheet = $spreadsheet->getActiveSheet();
    $hiddenSheet->setTitle('Hidden');
    $hiddenSheet->setSheetState(Worksheet::SHEETSTATE_HIDDEN);
    $hiddenSheet->fromArray([
        ['name', 'email'],
        ['Hidden Person', 'hidden@example.com'],
    ]);

    $visibleSheet = new Worksheet($spreadsheet, 'Visible');
    $spreadsheet->addSheet($visibleSheet);
    $spreadsheet->setActiveSheetIndex(1);
    $visibleSheet->fromArray([
        ['name', 'email'],
        ['Visible Person', 'visible@example.com'],
    ]);

    (new Xlsx($spreadsheet))->save($path);
    $spreadsheet->disconnectWorksheets();

    $action = new class
    {
        use CanCustomiseActionSetup;
        use HasSampleExcelFile;
        use HasUploadForm;

        public function previewRowsForTest(string $path): array
        {
            $this->previewRows(5);

            return $this->readPreviewRows($path)->toArray();
        }
    };

    try {
        $rows = $action->previewRowsForTest($path);
    } finally {
        @unlink($path);
    }

    expect($rows)
        ->toHaveCount(1)
        ->and($rows[0]['name'])->toBe('Visible Person')
        ->and($rows[0]['email'])->toBe('visible@example.com');
});

it('preserves non english preview headers and fills blank headers with column letters', function () {
    app()->register(ExcelServiceProvider::class);

    $path = tempnam(sys_get_temp_dir(), 'preview-non-english-headers-') . '.xls';

    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray([
        ['經銷商', '據點', null, '部門代碼', '經銷商代碼'],
        ['電智捷', '內湖展示中心', 'SSI', '12V00', 'LL'],
    ]);

    (new Xls($spreadsheet))->save($path);
    $spreadsheet->disconnectWorksheets();

    $action = new class
    {
        use CanCustomiseActionSetup;
        use HasSampleExcelFile;
        use HasUploadForm;

        public function previewRowsForTest(string $path): array
        {
            $this->previewRows(5);

            return $this->readPreviewRows($path)->toArray();
        }
    };

    try {
        $rows = $action->previewRowsForTest($path);
    } finally {
        @unlink($path);
    }

    expect($rows)
        ->toHaveCount(1)
        ->and(array_keys($rows[0]))->toBe(['經銷商', '據點', 'C', '部門代碼', '經銷商代碼'])
        ->and($rows[0])->toMatchArray([
            '經銷商' => '電智捷',
            '據點' => '內湖展示中心',
            'C' => 'SSI',
            '部門代碼' => '12V00',
            '經銷商代碼' => 'LL',
        ]);
});
