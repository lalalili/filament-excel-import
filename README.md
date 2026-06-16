# Filament Excel Import

[![Latest Version on Packagist](https://img.shields.io/packagist/v/eightynine/filament-excel-import.svg?style=flat-square)](https://packagist.org/packages/eightynine/filament-excel-import)
[![Total Downloads](https://img.shields.io/packagist/dt/eightynine/filament-excel-import.svg?style=flat-square)](https://packagist.org/packages/eightynine/filament-excel-import)

This package adds a new feature to your filament resource, allowing you to easily import data to your model

_This package brings the maatwebsite/laravel-excel functionalities to filament. You can use all the maatwebsite/laravel-excel features in your laravel project_


## v4.1.0 Highlights

Version `4.1.0` stabilizes the 4.x import pipeline for PHP 8.4, Laravel 13, and
Filament 5 while keeping support for the existing 4.x public API.

- `ImportResult` and `afterImportResult()` expose created, updated, skipped,
  failed, and row error counts for synchronous imports.
- `queueImport()` dispatches compatible imports through Laravel Excel queues and
  uses chunked queueable variants for the built-in default resource imports.
- `previewRows()` shows a limited spreadsheet preview in the import modal before
  the user submits the import.
- `columnMapping()` maps uploaded headings to model attributes in default
  imports.
- `fileRules()`, `maxFileSize()`, and `mimeTypeMap()` make upload validation
  stricter without replacing the upload field.
- `sampleColumns()` and `sampleButtonLabel()` simplify sample file setup.

See [UPGRADE.md](UPGRADE.md) for migration notes from older fork commits and
custom action subclasses.

## v4.2.0 Highlights

Version `4.2.0` adds an opt-in failed rows CSV summary for synchronous imports.
By default, imports do not write failed row files or persist notification state.

- `downloadFailedRows()` writes `ImportResult::$errors` to CSV after a
  synchronous import.
- `failedRowsDisk()`, `failedRowsDirectory()`, and `failedRowsFileName()`
  control where the CSV is stored.
- `ImportResult` now exposes `failedRowsPath`, `failedRowsDisk`, and
  `failedRowsDownloadName` metadata for `afterImportResult()` callbacks.

## v4.3.0 Highlights

Version `4.3.0` adds queue lifecycle events for applications that need import
monitoring, notifications, or audit logs around queued imports.

- `ImportQueued` is dispatched after `queueImport()` successfully queues an
  import with Laravel Excel.
- Built-in queued imports dispatch `ImportStarted`, `ImportCompleted`, and
  `ImportFailed` through Laravel Excel `WithEvents`.
- Queued imports still do not run Livewire `afterImport()` or
  `afterImportResult()` hooks immediately.

## 🛠️ Be Part of the Journey

Hi, I'm Eighty Nine. I created excel import plugin to solve real problems I faced as a developer. Your sponsorship will allow me to dedicate more time to enhancing these tools and helping more people. [Become a sponsor](https://github.com/sponsors/eighty9nine) and join me in making a positive impact on the developer community.


## Installation

You can install the package via composer:

```bash
composer require eightynine/filament-excel-import
```

## Usage

Before using this action, make sure to allow [Mass Assignment](https://laravel.com/docs/10.x/eloquent#mass-assignment) for your model. If you are doing a custom import, this is not necessary.

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['name', 'phone', 'email'];
}
```

For example, if you have a `ClientResource` in your project, integrate the action into `ListClients` class as demonstrated below:

```php

namespace App\Filament\Resources\ClientResource\Pages;

use App\Filament\Resources\ClientResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListClients extends ListRecords
{
    protected static string $resource = ClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \EightyNine\ExcelImport\ExcelImportAction::make()
                ->color("primary"),
            Actions\CreateAction::make(),
        ];
    }
}

```

### Customise Import Process

#### Using a closure
You can use a closure to process the collection after it has been imported.

```php

    protected function getHeaderActions(): array
    {
        return [
            \EightyNine\ExcelImport\ExcelImportAction::make()
                ->processCollectionUsing(function (string $modelClass, Collection $collection) {
                    // Do some stuff with the collection
                    return $collection;
                }),
            Actions\CreateAction::make(),
        ];
    }
```

#### Using your own Import class

If you wish to use your own import class to change the import procedure, you can create your own Import class.

```bash
php artisan make:import MyClientImport
```

Then in your action use your client import class

```php

    protected function getHeaderActions(): array
    {
        return [
            \EightyNine\ExcelImport\ExcelImportAction::make()
                ->slideOver()
                ->color("primary")
                ->use(App\Imports\MyClientImport::class),
            Actions\CreateAction::make(),
        ];
    }
```

### Form Customisation

You can customise the form by using the `beforeUploadField` and `afterUploadField` methods. These methods accept an array of fields that will be added to the form before and after the upload field. You can also use the `uploadField` method to customise the upload field.

```php

    protected function getHeaderActions(): array
    {
        return [
            \EightyNine\ExcelImport\ExcelImportAction::make()
                ->slideOver()
                ->color("primary")
                ->use(App\Imports\MyClientImport::class)
                // Add fields before the upload field
                ->beforeUploadField([
                    TextInput::make('default_password'),
                    TextInput::make('default_status'),
                ])
                // Or add fields after the upload field
                ->afterUploadField([
                    TextInput::make('default_password'),
                    TextInput::make('default_status'),
                ])
                // Or customise the upload field
                ->uploadField(
                    fn ($upload) => $upload
                    ->label("Some other label")
                )
                // Use the additional form fields data
                ->beforeImport(function (array $data, $livewire, $excelImportAction) {
                    $defaultStatus = $data['default_status'];
                    $defaultPassword = $data['default_password'];

                    // When adding the additional data, the data will be merged with 
                    // the row data when inserting into the database
                    $excelImportAction->additionalData([
                        'password' => $defaultPassword,
                        'status' => $defaultStatus
                    ]);

                    // When adding the custom import data, the data will be available in
                    // the custom import as $this->customImport data, when the custom import extends the
                    // Default import.
                    $excelImportAction->customImportData([
                        'other_details' => [ 1, 2, 3, 4],
                        'age' => 5
                    ]);

                    // Do some other stuff with the data before importing
                })
                ,
            Actions\CreateAction::make(),
        ];
    }
```

### Custom Upload Disk
To use a custom upload disk, you can publish the config file and customise the upload_disk config.

```bash
php artisan vendor:publish --tag=excel-import-config
```

Then in your config file, you can customise the upload_disk config.

```php
return [
    /**
     * File upload path
     * 
     * Customise the path where the file will be uploaded to, 
     * if left empty, config('filesystems.default') will be used
     */
    'upload_disk' => 's3',
];
```

### Upload validation and security

The upload field restricts files to Excel / CSV MIME types by default. You may add
Laravel validation rules, max file size, and custom MIME mappings when your
environment needs stricter handling:

```php
\EightyNine\ExcelImport\ExcelImportAction::make()
    ->acceptedFileTypes([
        'text/csv',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ])
    ->fileRules(['mimes:csv,xlsx,xls'])
    ->maxFileSize('10mb')
    ->mimeTypeMap(['csv' => 'text/csv']);
```

Size strings accept `kb`, `mb`, and `gb` suffixes, including decimal values
like `1.5mb`. The parsed size must be at least `1` KB.

When using local or public disks, keep generated storage names instead of
preserving user-provided filenames. Filament's file upload component validates
MIME types, but original filenames can still be unsafe on PHP-executing disks.

### Performing Actions Before and After Import

You can perform actions before and after import by using the beforeImport and afterImport closures.

`$data` is the data that is submitted via the form, meaning the file upload is also available in `$data['upload']`, and `$livewire` is the Livewire instance that the action is being performed on (in this case, the ListClients class).

```php

    protected function getHeaderActions(): array
    {
        return [
            \EightyNine\ExcelImport\ExcelImportAction::make()
                ->slideOver()
                ->color("primary")
                ->use(App\Imports\MyClientImport::class)
                ->beforeImport(function ($data, $livewire, $excelImportAction) {
                    // Perform actions before import
                })
                ->afterImport(function ($data, $livewire, $excelImportAction) {
                    // Perform actions after import
                }),
            Actions\CreateAction::make(),
        ];
    }
```

### Reading import results

Default import classes expose an `ImportResult` with basic counts. Custom
collection callbacks may return an `ImportResult` to provide their own numbers:

```php
use EightyNine\ExcelImport\Support\ImportResult;

\EightyNine\ExcelImport\ExcelImportAction::make()
    ->processCollectionUsing(function (string $modelClass, Collection $collection): ImportResult {
        // Process records...

        return ImportResult::make(created: 10, skipped: 2);
    })
    ->afterImportResult(function (ImportResult $result): void {
        // $result->created, $result->updated, $result->skipped, $result->failed
    });
```

### Downloading failed rows

Use `downloadFailedRows()` when a custom import returns row-level errors in its
`ImportResult`. The package writes a CSV summary only when this option is
enabled and `ImportResult::$errors` is not empty:

```php
use EightyNine\ExcelImport\Support\ImportResult;

\EightyNine\ExcelImport\ExcelImportAction::make()
    ->downloadFailedRows()
    ->failedRowsDisk('local')
    ->failedRowsDirectory('imports/failed')
    ->failedRowsFileName(fn (ImportResult $result): string => "failed-rows-{$result->failed}.csv")
    ->afterImportResult(function (ImportResult $result): void {
        // $result->failedRowsDisk
        // $result->failedRowsPath
        // $result->failedRowsDownloadName
    });
```

The default directory is `excel-import/failed-rows`, and the default download
name is `failed-rows.csv`. File names are normalized to `.csv`.

Failed rows CSV export is synchronous-only. Queued imports finish later in a
worker, so they do not run Livewire result hooks or write failed row summaries
from the action instance.

### Column mapping

Use `columnMapping()` when uploaded headings differ from your model columns:

```php
\EightyNine\ExcelImport\ExcelImportAction::make()
    ->columnMapping([
        'Email Address' => 'email',
        'Full Name' => 'name',
    ]);
```

### Previewing uploaded rows

Use `previewRows()` to show the first rows of the uploaded spreadsheet in the
import modal before the user submits the import:

```php
\EightyNine\ExcelImport\ExcelImportAction::make()
    ->previewRows(5);
```

The preview row limit must be at least `1`.

### Queueing imports

Use `queueImport()` to dispatch the import through Laravel queues. The built-in
resource default imports are automatically swapped to queueable, chunked variants:

```php
\EightyNine\ExcelImport\ExcelImportAction::make()
    ->queueImport()
    ->chunkSize(500);
```

The chunk size must be at least `1`.

Custom import classes used with `queueImport()` must implement both
`Illuminate\Contracts\Queue\ShouldQueue` and
`Maatwebsite\Excel\Concerns\WithChunkReading`.
Queued imports store uploaded files permanently before dispatching and read them
from the action `disk()` or `excel-import.upload_disk` config. If you replace the
default upload field, make sure it returns a stored file path instead of a
temporary upload object.
Relationship imports are not automatically made queueable because relation
manager state is not safe to serialize; provide a custom queue-safe import class
if you need that workflow.
Closure-based settings such as `processCollectionUsing()`, closure column
mappings, and validation mutators are not queue-safe; move that logic into a
custom import class when queueing.
Because queued imports finish later in a worker, `afterImport()` and
`afterImportResult()` only run for synchronous imports.

### Observing queued imports

Queued imports dispatch package events you can listen to from your application:

```php
use EightyNine\ExcelImport\Events\ImportCompleted;
use EightyNine\ExcelImport\Events\ImportFailed;
use EightyNine\ExcelImport\Events\ImportQueued;
use EightyNine\ExcelImport\Events\ImportStarted;
```

`ImportQueued` is dispatched by the action after Laravel Excel accepts the
queued import:

```php
use EightyNine\ExcelImport\Events\ImportQueued;

public function handle(ImportQueued $event): void
{
    logger()->info('Excel import queued', [
        'path' => $event->path,
        'disk' => $event->disk,
        'import' => $event->import::class,
    ]);
}
```

Built-in queued imports dispatch lifecycle events while the worker processes the
file:

```php
use EightyNine\ExcelImport\Events\ImportCompleted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

class SendImportFinishedNotification implements ShouldQueue
{
    public function handle(ImportCompleted $event): void
    {
        Notification::route('mail', 'imports@example.com')
            ->notify(new ImportFinishedNotification($event->result));
    }
}
```

`ImportFailed` exposes the thrown exception:

```php
use EightyNine\ExcelImport\Events\ImportFailed;

public function handle(ImportFailed $event): void
{
    report($event->exception);
}
```

When using Horizon, keep Excel imports on a queue with enough workers for the
expected spreadsheet size and monitor it from `/horizon`. Horizon requires the
Redis queue driver. To populate Horizon metrics, schedule snapshots:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('horizon:snapshot')->everyFiveMinutes();
```

For queue wait alerts, configure Horizon notification routes in your
`HorizonServiceProvider` and set wait thresholds in `config/horizon.php`.

### Data Validation

You can validate the data before importing by using the `validateUsing` method. This method accepts an array of rules that will be used to validate the data. You can use all the rules from the Laravel validation system.

```php

    protected function getHeaderActions(): array
    {
        return [
            \EightyNine\ExcelImport\ExcelImportAction::make()
                ->validateUsing([
                    'name' => 'required',
                    'email' => 'required|email',
                    'phone' => ['required','numeric'],
                ]),
            Actions\CreateAction::make(),
        ];
    }
```

### Mutating data before and after validation
In some cases you may want to mutate the data before or after validation, in order to achieve this, you can use `mutateBeforeValidationUsing` and `mutateAfterValidationUsing` functions methods.

```php

    \EightyNine\ExcelImport\ExcelImportAction::make()
        ->mutateBeforeValidationUsing(function(array $data): array{
            $data['date'] = Carbon::make((string) str($value)->replace('.', '-'));
            return $data;
        })
        ->validateUsing([
            'name' => 'required',
            'email' => 'required|email',
            'phone' => ['required','numeric'],
        ])
        ->mutateAfterValidationUsing(
            closure: function(array $data): array{
                $data['date'] = $data['date']->format('Y-m-d');
                return $data;
            },
            shouldRetainBeforeValidationMutation: true // if this is set, the mutations will be retained after validation (avoids repetition in/of afterValidation)
        ),

```

### Sample Excel File
You can allow users to download a sample excel file by using the `sampleExcel` method. This method accepts an array of data, a file name, an export class and a sample button label.

```php

use Filament\Forms\Components\Actions\Action;

protected function getHeaderActions(): array
{
    return [
        \EightyNine\ExcelImport\ExcelImportAction::make()
            ->sampleExcel(
                sampleData: [
                    ['name' => 'John Doe', 'email' => 'john@doe.com', 'phone' => '123456789'],
                    ['name' => 'Jane Doe', 'email' => 'jane@doe.com', 'phone' => '987654321'],
                ], 
                fileName: 'sample.xlsx', 
                exportClass: App\Exports\SampleExport::class, 
                sampleButtonLabel: 'Download Sample',
                customiseActionUsing: fn(Action $action) => $action->color('secondary')
                    ->icon('heroicon-m-clipboard')
                    ->requiresConfirmation(),
            ),
        // OR
        \EightyNine\ExcelImport\ExcelImportAction::make()
            ->sampleColumns(
                columns: ['name', 'email', 'phone'],
                fileName: 'sample.xlsx',
                sampleButtonLabel: 'Download Sample',
            ),
        // OR
        \EightyNine\ExcelImport\ExcelImportAction::make()
            ->sampleFileExcel(
                url: url('excel/users.xlsx'),
                sampleButtonLabel: 'Download Sample',
                customiseActionUsing: fn(Action $action) => $action->color('secondary')
                    ->icon('heroicon-m-clipboard')
                    ->requiresConfirmation(),
            ),
        Actions\CreateAction::make(),
    ];
}
```

### Using import action to import relationships

The import action can also be used to import relationships. This is done by using the `DefaultRelationshipImport` class in your relation manager.

```php

use EightyNine\ExcelImport\Tables\ExcelImportRelationshipAction;


class PostsRelationManager extends RelationManager
{
    protected static string $relationship = 'posts';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                Tables\Columns\TextColumn::make('title'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                ExcelImportRelationshipAction::make()
                    ->slideOver()
                    ->color('primary')
                    ->validateUsing([
                        'title' => 'required',
                        'body' => 'required',
                    ]),
            ]);
    }
}
```

### Stopping Imports and Returning Messages

**New in v3.1.5+**: You can now stop the import process from within your custom import class and return messages to the frontend. This is useful for:

- Header validation to ensure the file format is correct
- Business logic validation that might require stopping the entire import
- Custom validation that depends on form data or external conditions

#### Using Enhanced Import Classes

The package now provides `EnhancedDefaultImport` and `EnhancedDefaultRelationshipImport` classes that include methods to stop imports:

```php
use EightyNine\ExcelImport\EnhancedDefaultImport;
use Illuminate\Support\Collection;

class CustomUserImport extends EnhancedDefaultImport
{
    protected function beforeCollection(Collection $collection): void
    {
        // Validate required headers
        $requiredHeaders = ['name', 'email', 'phone'];
        $this->validateHeaders($requiredHeaders, $collection);

        // Custom business logic validation
        if ($collection->count() > 1000) {
            $this->stopImportWithError('Too many rows. Maximum 1000 allowed.');
        }

        // Access custom data from the form
        $formData = $this->customImportData;
        if (isset($formData['department_id'])) {
            $departmentExists = Department::where('id', $formData['department_id'])->exists();
            $this->validateCustomCondition(
                $departmentExists,
                'Selected department does not exist.'
            );
        }
    }

    protected function beforeCreateRecord(array $data, $row): void
    {
        // Row-level validation
        if (User::where('email', $data['email'])->exists()) {
            $this->stopImportWithWarning(
                "User with email {$data['email']} already exists."
            );
        }
    }

    protected function afterCollection(Collection $collection): void
    {
        // Show success message with statistics
        $count = $collection->count();
        $this->stopImportWithSuccess("Successfully imported {$count} users!");
    }
}
```

#### Available Methods

**Stop Import Methods:**
- `stopImportWithError(string $message)` - Shows red error notification
- `stopImportWithWarning(string $message)` - Shows orange warning notification  
- `stopImportWithInfo(string $message)` - Shows blue info notification
- `stopImportWithSuccess(string $message)` - Shows green success notification

**Validation Helpers:**
- `validateHeaders(array $expectedHeaders, Collection $collection)` - Validates required headers
- `validateCustomCondition(bool $condition, string $errorMessage)` - Custom validation

**Hook Methods (override in your class):**
- `beforeCollection(Collection $collection)` - Called before processing starts
- `beforeCreateRecord(array $data, $row)` - Called before each record creation
- `afterCreateRecord(array $data, $row, $record)` - Called after each record creation
- `afterCollection(Collection $collection)` - Called after processing completes

#### Using with Form Data

You can access custom form data in your import class for validation:

```php
protected function getHeaderActions(): array
{
    return [
        \EightyNine\ExcelImport\ExcelImportAction::make()
            ->use(CustomUserImport::class)
            ->beforeUploadField([
                Select::make('department_id')
                    ->label('Department')
                    ->options(Department::pluck('name', 'id'))
                    ->required(),
            ])
            ->beforeImport(function (array $data, $livewire, $excelImportAction) {
                $excelImportAction->customImportData([
                    'department_id' => $data['department_id'],
                    'imported_by' => auth()->id(),
                ]);
            }),
    ];
}
```

#### Using with Existing Import Classes

If you have existing import classes, you can add stopping capabilities by throwing the `ImportStoppedException`:

```php
use EightyNine\ExcelImport\Exceptions\ImportStoppedException;

class MyExistingImport implements ToCollection, WithHeadingRow
{
    public function collection(Collection $collection)
    {
        // Your existing validation logic
        if ($someCondition) {
            throw new ImportStoppedException('Custom error message', 'error');
        }
        
        // Continue with normal processing...
    }
}
```

The exception constructor accepts:
- `$message` (string) - The message to show to the user
- `$type` (string) - The notification type: 'error', 'warning', 'info', or 'success' (default: 'error')


```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

-   [Eighty Nine](https://github.com/eighty9nine)
-   [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## CSS Styling and Theme Integration

### Avoiding CSS Conflicts

By default, this package loads minimal CSS to avoid conflicts with your application's existing button styles and theme. The stylesheet loading is disabled by default to prevent issues with `[type="button"]` selectors and other global styles.

If you need to enable the custom stylesheet, you can publish the config file and set `load_stylesheet` to `true`:

```bash
php artisan vendor:publish --tag=excel-import-config
```

Then in your `config/excel-import.php` file:

```php
return [
    // ...other config options
    
    /**
     * Load custom stylesheet
     *
     * Set to true to enable loading the custom CSS.
     * Set to false to disable loading to prevent conflicts
     * with existing button styles in your application
     */
    'load_stylesheet' => true,
];
```

### Theme Integration

The Excel Import action inherits the styling from your Filament theme automatically. If you're experiencing styling conflicts, ensure that:

1. Your Filament theme is properly configured
2. The `load_stylesheet` config option is set to `false` (default)
3. Your application's CSS doesn't have overly broad selectors that might conflict

### Custom Styling

If you need custom styling for the Excel Import components, you can:

1. Add CSS targeting the specific modal and form components
2. Use Filament's theming system to customize the appearance
3. Override specific component classes in your application's CSS

## Troubleshooting

### CSS Conflicts with `[type="button"]` selectors

If you're experiencing CSS conflicts where the Excel Import package is overriding your application's button styles, this is because the package was previously loading the full Filament theme CSS. 

**This has been resolved in the latest version** by:

1. **Minimal CSS Loading**: The package now loads only minimal CSS by default
2. **Configurable Stylesheet**: You can enable/disable stylesheet loading via config
3. **Theme Integration**: The package relies on your existing Filament theme

If you're still experiencing issues:

1. Ensure you're using the latest version of the package
2. Verify that `load_stylesheet` is set to `false` in your config (this is the default)
3. Clear your browser cache and rebuild your assets

### Migration from Previous Versions

If you're upgrading from a previous version that had CSS conflicts:

1. Publish the config file: `php artisan vendor:publish --tag=excel-import-config`
2. Ensure `load_stylesheet` is set to `false`
3. Rebuild your assets: `npm run build`
