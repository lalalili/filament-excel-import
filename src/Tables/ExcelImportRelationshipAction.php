<?php

namespace EightyNine\ExcelImport\Tables;

use EightyNine\ExcelImport\Concerns\HasExcelImportAction;
use EightyNine\ExcelImport\DefaultRelationshipImport;
use Filament\Actions\Action;

class ExcelImportRelationshipAction extends Action
{
    use HasExcelImportAction;

    protected string $importClass = DefaultRelationshipImport::class;

    protected function isRelationshipImport(): bool
    {
        return true;
    }
}
