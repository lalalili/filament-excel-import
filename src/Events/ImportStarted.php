<?php

namespace EightyNine\ExcelImport\Events;

class ImportStarted
{
    public function __construct(
        public readonly object $import,
    ) {}
}
