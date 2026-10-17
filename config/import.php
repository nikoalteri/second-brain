<?php

// Limits for statement imports (Phase 25). They bound memory and time before anything is staged.
return [
    'max_file_kb' => 5120,
    'max_rows' => 5000,
    'max_columns' => 40,
    'max_cell_length' => 500,
    // Uncompressed size of all XLSX entries together, and the number of entries: a zip bomb guard.
    'max_xlsx_uncompressed_bytes' => 50 * 1024 * 1024,
    'max_xlsx_entries' => 200,
    // Draft batches nobody confirmed are removed after this many days.
    'abandoned_after_days' => 30,
    'preview_rows' => 20,
];
