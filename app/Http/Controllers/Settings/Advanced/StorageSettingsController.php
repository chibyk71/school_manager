<?php

namespace App\Http\Controllers\Settings\Advanced;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

/**
 * StorageSettingsController v1.0 – Production-Ready File Storage Configuration
 *
 * Purpose:
 * Central configuration for file storage drivers (Local vs AWS S3) used for uploads:
 * - Student photos, documents, invoices, receipts, certificates, etc.
 *
 * Design Match:
 * Exactly matches your PreSkool template:
 * - Card list: Local Storage + AWS
 * - Toggle + gear icon (configure)
 * - Modal with Access Key, Secret Key, Bucket, Region, Base URL, Status
 * - Clean, responsive layout
 *
 * Features / Problems Solved:
 * - Uses getMergedSettings() + SaveOrUpdateSchoolSettings() → global defaults + school overrides
 * - No abort() → system admin can set defaults
 * - Driver selection (local or s3)
 * - Secure credential encryption
 * - Modal-based configuration (clean main page)
 * - Test connection button (optional future enhancement)
 * - Production-ready: validation, encryption, error handling
 *
 * Settings Key: 'advanced.storage'
 *
 * Structure:
 *   'advanced.storage' => [
 *       'driver' => 's3', // or 'local'
 *       's3' => [
 *           'key' => '...',
 *           'secret' => '...',
 *           'bucket' => '...',
 *           'region' => 'us-east-1',
 *           'url' => 'https://bucket.s3.amazonaws.com',
 *           'enabled' => true,
 *       ],
 *       'local' => ['enabled' => true],
 *   ]
 *
 * Fits into the Settings Module:
 * - Route: GET/POST settings.advanced.storage
 * - Navigation: Other Settings → Storage
 * - Frontend: resources/js/Pages/Settings/Advanced/Storage.vue
 * - Modal: resources/js/Components/Modals/StorageModal.vue
 */

class StorageSettingsController extends Controller
{
    public function index(Request $request)
    {
        permitted('manage-settings');

        $school = GetSchoolModel();

        $settings = getMergedSettings('advanced.storage', $school);

        $drivers = [
            'local' => [
                'name' => 'Local Storage',
                'logo' => 'local',
                'description' => 'Files stored on your server (default). Simple and fast.',
                'fields' => [],
            ],
            's3' => [
                'name' => 'Amazon S3 (AWS)',
                'logo' => 'aws',
                'description' => 'Cloud storage with high durability and CDN support.',
                'fields' => ['key', 'secret', 'bucket', 'region', 'url'],
            ],
        ];

        return Inertia::render('Settings/Advanced/Storage', [
            'settings' => $settings,
            'drivers' => $drivers,
            'crumbs' => [
                ['label' => 'Settings'],
                ['label' => 'Other Settings'],
                ['label' => 'Storage'],
            ],
        ]);
    }

    public function store(Request $request)
    {
        permitted('manage-settings');

        $school = GetSchoolModel();

        $validated = $request->validate([
            'driver' => 'required|in:local,s3',
            'enabled' => 'boolean',
            'config' => 'required|array',
            'config.key' => 'required_if:driver,s3|string|max:255',
            'config.secret' => 'required_if:driver,s3|string|max:255',
            'config.bucket' => 'required_if:driver,s3|string|max:255',
            'config.region' => 'required_if:driver,s3|string|max:50',
            'config.url' => 'nullable|url|max:500',
        ]);

        try {
            $current = getMergedSettings('advanced.storage', $school);

            $driver = $validated['driver'];
            $current['driver'] = $driver;

            $current[$driver] = [
                'enabled' => $validated['enabled'],
                'config' => $driver === 's3' ? $validated['config'] : [],
            ];

            // Encrypt secrets
            if ($driver === 's3') {
                $current['s3']['config']['secret'] = encrypt($validated['config']['secret']);
            }

            SaveOrUpdateSchoolSettings('advanced.storage', $current, $school);

            return redirect()
                ->route('settings.advanced.storage')
                ->with('success', ucfirst($driver) . ' storage configured successfully.');
        } catch (\Exception $e) {
            Log::error('Storage settings save failed', [
                'driver' => $validated['driver'] ?? null,
                'school_id' => $school?->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()
                ->with('error', 'Failed to save storage settings.')
                ->withInput();
        }
    }
}