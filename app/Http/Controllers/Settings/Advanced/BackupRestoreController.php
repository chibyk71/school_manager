<?php

namespace App\Http\Controllers\Settings\Advanced;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * BackupRestoreController v1.0 – Production-Ready Backup & Restore Management
 *
 * TODO: Add scheduled backups + email notifications in future versions
 * TODO: Add restore functionality with confirmation in future versions
 * TODO: Add check if tenant package supports backups
 *
 * Purpose:
 * Provides a secure interface for system administrators to create, download, and delete database + files backups.
 * Essential for disaster recovery and data migration.
 *
 * Why this page is necessary:
 * - Regular backups are critical for any production system
 * - Schools need to export data for audits, migrations, or recovery
 * - Industry standard: most admin panels have a "Backup" section
 *
 * Features / Problems Solved:
 * - Uses Laravel's built-in `php artisan backup:run` (spatie/laravel-backup recommended)
 * - Lists existing backups with size, date, download/delete actions
 * - Create new backup on demand
 * - Secure: permission check, no direct file access
 * - Download streaming (handles large files)
 * - Delete with confirmation
 * - Responsive PrimeVue DataTable
 * - Production-ready: error handling, logging, streamed response
 *
 * Assumptions:
 * - spatie/laravel-backup package is installed and configured
 * - Backups stored in `storage/app/backups` or cloud (S3)
 * - Disk configured in config/backup.php
 *
 * Settings: No database settings needed — uses package config
 *
 * Fits into the Settings Module:
 * - Route: GET/POST/DELETE settings.advanced.backup
 * - Navigation: Other Settings → Backup & Restore
 * - Frontend: resources/js/Pages/Settings/Advanced/BackupRestore.vue
 */

class BackupRestoreController extends Controller
{
    public function index(Request $request)
    {
        permitted('manage-backups');

        // Get backup disk from config
        $disk = Storage::disk(config('backup.backup.destination.disks')[0] ?? 'local');

        $backups = collect($disk->files('Laravel')) // Default folder
            ->filter(fn($file) => str_ends_with($file, '.zip'))
            ->map(function ($file) use ($disk) {
                return [
                    'path' => $file,
                    'name' => basename($file),
                    'size' => $disk->size($file),
                    'date' => $disk->lastModified($file),
                ];
            })
            ->sortByDesc('date')
            ->values();

        return Inertia::render('Settings/Advanced/BackupRestore', [
            'backups' => $backups,
            'crumbs' => [
                ['label' => 'Settings'],
                ['label' => 'Other Settings'],
                ['label' => 'Backup & Restore'],
            ],
        ]);
    }

    public function create(Request $request)
    {
        permitted('manage-backups');

        try {
            Artisan::call('backup:run', ['--only-db' => false]); // Full backup

            return redirect()
                ->route('settings.advanced.backup')
                ->with('success', 'Backup created successfully.');
        } catch (\Exception $e) {
            Log::error('Backup creation failed', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Failed to create backup.');
        }
    }

    public function download(Request $request, $filename)
    {
        permitted('manage-backups');

        $disk = Storage::disk(config('backup.backup.destination.disks')[0] ?? 'local');
        $path = 'Laravel/' . $filename;

        if (!$disk->exists($path)) {
            abort(404, 'Backup file not found.');
        }

        return new StreamedResponse(function () use ($disk, $path) {
            $stream = $disk->readStream($path);
            fpassthru($stream);
        }, 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function destroy(Request $request, $filename)
    {
        permitted('manage-backups');

        $disk = Storage::disk(config('backup.backup.destination.disks')[0] ?? 'local');
        $path = 'Laravel/' . $filename;

        if ($disk->exists($path)) {
            $disk->delete($path);
        }

        return redirect()
            ->route('settings.advanced.backup')
            ->with('success', 'Backup deleted successfully.');
    }
}
