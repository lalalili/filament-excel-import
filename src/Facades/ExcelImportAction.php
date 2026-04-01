<?php

namespace EightyNine\ExcelImport\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \EightyNine\ExcelImport\ExcelImportAction
 */
class ExcelImportAction extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \EightyNine\ExcelImport\ExcelImportAction::class;
    }
}
