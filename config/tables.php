<?php

// TODO: Comming back to this to make it use the config stored in the database settings table

return [

    /*
    |--------------------------------------------------------------------------
    | Default Pagination Settings
    |--------------------------------------------------------------------------
    |
    | These values are used when no explicit per_page is provided.
    |
    */
    'default_per_page' => env('TABLES_DEFAULT_PER_PAGE', 20),

    /*
    |--------------------------------------------------------------------------
    | Maximum Allowed Rows Per Page
    |--------------------------------------------------------------------------
    |
    | Prevents users from requesting huge pages that could overload the server.
    | Used in both standard pagination and window mode safety cap.
    |
    */
    'max_per_page' => env('TABLES_MAX_PER_PAGE', 100),

    /*
    |--------------------------------------------------------------------------
    | Client-Side Threshold
    |--------------------------------------------------------------------------
    |
    | If total records ≤ this number, the frontend will switch to full
    | client-side mode (fetch all rows once). Above this → server-side with
    | windowed prefetching.
    |
    */
    'client_side_threshold' => env('TABLES_CLIENT_SIDE_THRESHOLD', 1000),

    /*
    |--------------------------------------------------------------------------
    | Server-Side Window Size
    |--------------------------------------------------------------------------
    |
    | When in server-side mode and a window is requested (larger chunk),
    | this is the number of rows returned in one window.
    | Recommended: 200–400 (e.g. 10 pages of 20–40 rows).
    |
    */
    'window_size' => env('TABLES_WINDOW_SIZE', 200),

    /*
    |--------------------------------------------------------------------------
    | Maximum Window Size Safety Cap
    |--------------------------------------------------------------------------
    |
    | Even if frontend asks for a huge per_page in window mode, we cap it.
    |
    */
    'max_window_size' => env('TABLES_MAX_WINDOW_SIZE', 500),

    /*
    |--------------------------------------------------------------------------
    | Cache Durations
    |--------------------------------------------------------------------------
    |
    | How long column definitions and schema caches are kept.
    |
    */
    'column_definition_cache_hours' => 1,
    'schema_cache_hours' => 6,

    /*
    |--------------------------------------------------------------------------
    | Export Settings
    |--------------------------------------------------------------------------
    |
    | Default chunk size when exporting large datasets (if you implement streaming export).
    |
    */
    'export_chunk_size' => 1000,

    /*
    |--------------------------------------------------------------------------
    | Global Features
    |--------------------------------------------------------------------------
    |
    | Toggle global table features if needed.
    |
    */
    'enable_column_toggler' => true,
    'enable_export' => true,
    'enable_bulk_actions' => true,

];