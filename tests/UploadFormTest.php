<?php

use EightyNine\ExcelImport\Concerns\CanCustomiseActionSetup;
use EightyNine\ExcelImport\Concerns\HasSampleExcelFile;
use EightyNine\ExcelImport\Concerns\HasUploadForm;
use Filament\Forms\Components\FileUpload;
use Filament\Infolists\Components\TextEntry;
use Illuminate\Support\HtmlString;
use Maatwebsite\Excel\Facades\Excel;

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
