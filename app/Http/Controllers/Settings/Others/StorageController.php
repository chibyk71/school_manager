<?php

namespace App\Http\Controllers\Settings\Others;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

/**
 * Controller for managing storage settings for schools.
 */
class StorageController extends Controller
{
    /**
     * Display the storage settings page.
     *
     * @param Request $request
     * @return \Inertia\Response
     *
     * @throws \Exception If settings retrieval fails.
     */
    public function index()
    {
        try {
            // Fetch merged storage settings (school-specific or tenant defaults)
            $settings = getMergedSettings('storage', GetSchoolModel());
            return Inertia::render('Settings/Others/Storage', compact('settings'));
        } catch (\Exception $e) {
            Log::error('Failed to fetch storage settings: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load storage settings.');
        }
    }

    /**
     * Store or update storage settings.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     *
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If settings save fails.
     */
    public function store(Request $request)
    {
        try {
            // Validate incoming request data
            $validatedData = $request->validate([
                'storage_driver' => 'required|string|in:local,s3,dropbox',
                's3_key' => 'required_if:storage_driver,s3|string|nullable',
                's3_secret' => 'required_if:storage_driver,s3|string|nullable',
                's3_region' => 'required_if:storage_driver,s3|string|nullable',
                's3_bucket' => 'required_if:storage_driver,s3|string|nullable',
            ]);

            // Save or update school-specific storage settings
            SaveOrUpdateSchoolSettings('storage', $validatedData);

            return redirect()
                ->route('settings.others.storage.index')
                ->with('success', 'Storage settings updated successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Failed to save storage settings: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to save storage settings.');
        }
    }
}
