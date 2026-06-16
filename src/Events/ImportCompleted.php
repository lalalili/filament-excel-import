<?php

namespace EightyNine\ExcelImport\Events;

use EightyNine\ExcelImport\Support\ImportResult;

class ImportCompleted
{
    public function __construct(
        public readonly object $import,
        public readonly ImportResult $result,
    ) {}
}
