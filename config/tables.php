<?php

return [
    'default_per_page' => env('TABLES_DEFAULT_PER_PAGE', 50),
    'max_per_page' => env('TABLES_MAX_PER_PAGE', 100),
    'prefetch_pages' => env('TABLES_PREFETCH_PAGES', 2),
    'column_definition_cache_hours' => 1,
    'schema_cache_hours' => 6,
    'export_chunk_size' => 1000,
    'enable_column_toggler' => true,
    'enable_export' => true,
    'enable_bulk_actions' => true,
];
