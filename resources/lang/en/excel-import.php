<?php

// translations for EightyNine/ExcelImportAction
return [
    // Import Action Labels
    'import_action_heading' => 'Import Excel',
    'import_action_description' => 'Import data into database from Excel file',
    'excel_data' => 'Excel Data',
    'download_sample_excel_file' => 'Download Sample Excel File',
    'preview_rows' => 'Preview Rows',
    'preview_waiting_for_upload' => 'Upload a file to preview its first rows.',
    'preview_empty' => 'No preview rows were found in the uploaded file.',
    'preview_unavailable' => 'Unable to preview the uploaded file.',

    // Import Status Title
    'import_failed' => 'Import Failed',
    'import_warning' => 'Import Warning',
    'import_information' => 'Import Information',
    'import_success' => 'Import Success',
    'import_queued' => 'Import Queued',

    // Success Message
    'import_success_message' => 'Import completed successfully',
    'import_queued_message' => 'Import has been queued and will be processed in the background.',

    // Validation Messages
    'validation_failed' => 'Row :row failed validation. The following messages were returned: :messages',

    // File Validation Errors
    'file_empty_error' => 'The uploaded file is empty or has no valid data.',
    'header_read_error' => 'Unable to read the header row from the uploaded file.',
    'missing_headers_error' => 'Missing required headers: :missing. Expected headers: :expected',
];
