<?php

namespace EightyNine\ExcelImport\Events;

use Throwable;

class ImportFailed
{
    public function __construct(
        public readonly object $import,
        public readonly Throwable $exception,
    ) {}
}
