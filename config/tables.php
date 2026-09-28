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

    /*
    |--------------------------------------------------------------------------
    | Selection (Phase 6)
    |--------------------------------------------------------------------------
    | Maximum number of explicit IDs accepted in a single selection.
    | Oversized ID selections are rejected — never silently converted to query.
    */
    'selection' => [
        'max_ids' => env('TABLES_SELECTION_MAX_IDS', 5000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Export strategy (Phase 6)
    |--------------------------------------------------------------------------
    | Synchronous path is implemented. Queued export can key off this threshold
    | without changing request contracts.
    */
    'export' => [
        'sync_max_rows' => env('TABLES_EXPORT_SYNC_MAX_ROWS', 5000),
        'queued_enabled' => env('TABLES_EXPORT_QUEUED_ENABLED', false),
    ],
];
