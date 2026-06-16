<?php

namespace EightyNine\ExcelImport\Events;

class ImportQueued
{
    public function __construct(
        public readonly object $import,
        public readonly mixed $path,
        public readonly ?string $disk,
    ) {}
}
