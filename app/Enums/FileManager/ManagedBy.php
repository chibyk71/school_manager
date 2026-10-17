<?php

namespace App\Enums\FileManager;

/**
 * Lifecycle ownership of a File record.
 *
 * file_manager — File Manager controls ordinary delete (Phase 2: hard delete of
 *                empty lifecycle only; Trash/Purge is a later phase).
 * module       — Consuming module owns lifecycle; ordinary File Manager delete
 *                must not destroy the physical object or the File row.
 */
enum ManagedBy: string
{
    case FileManager = 'file_manager';
    case Module = 'module';
}
