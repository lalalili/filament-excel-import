<?php

// config for EightyNine/ExcelImportAction
return [

    /**
     * File upload path
     *
     * Customise the path where the file will be uploaded to,
     * if left empty, config('filesystems.default') will be used
     */
    'upload_disk' => null,

    /**
     * Load custom stylesheet
     *
     * Set to false to disable loading the custom CSS to prevent conflicts
     * with existing button styles in your application
     */
    'load_stylesheet' => false,

    /**
     * Upload preview defaults
     *
     * These defaults keep modal previews compact. Individual actions may
     * override the rendered column count with previewColumns().
     */
    'preview' => [
        'columns' => 5,
    ],
];
