<?php

namespace EightyNine\ExcelImport\Contracts;

use EightyNine\ExcelImport\Support\ImportResult;

interface HasImportResult
{
    public function getImportResult(): ImportResult;
}
