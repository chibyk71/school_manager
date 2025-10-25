<?php

namespace App\Http\Controllers\Settings\School\Email;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

/**
 * Controller for managing email configuration settings for schools.
 */
class EmailController extends Controller
{
    /**
     * Display the email settings page.
     *
     * Retrieves merged email settings (tenant defaults, school-specific, or branch-specific overrides)
     * and renders the email settings view.
     *
     * @param Request $request The incoming HTTP request.
     * @return \Inertia\Response The Inertia response with email settings.
     *
     * @throws \Exception If settings retrieval fails or school is not found.
     */
    public function index(Request $request)
    {
        try {
            // Fetch merged email settings (tenant, school, or branch-specific)
            $settings = getMergedSettings('email', GetSchoolModel(), $request->branch_id);

            return Inertia::render('Settings/System/Email', compact('settings'));
        } catch (\Exception $e) {
            Log::error('Failed to fetch email settings: ' . $e->getMessage());
            return redirect()->route('dashboard')->with('error', 'Failed to load email settings.');
        }
    }

    /**
     * Store or update email settings.
     *
     * Validates and saves email configuration settings for the school or branch.
     *
     * @param Request $request The incoming HTTP request with validated data.
     * @return \Illuminate\Http\RedirectResponse Redirects to the email settings page.
     *
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If settings update fails or school is not found.
     */
    public function store(Request $request)
    {
        try {
            // Check if user has permission to manage email settings
            permitted('manage-email-settings');

            // Validate incoming request data
            $validatedData = $request->validate([
                'mail_driver' => 'required|string|in:smtp,mailgun,ses,postmark',
                'from_email' => 'required|email|max:255',
                'from_name' => 'required|string|max:255',
                'mail_host' => 'required_if:mail_driver,smtp|string|max:255',
                'mail_port' => 'required_if:mail_driver,smtp|integer|min:1|max:65535',
                'mail_encryption' => 'required_if:mail_driver,smtp|string|in:tls,ssl,null',
                'mail_username' => 'required_if:mail_driver,smtp|string|max:255|nullable',
                'mail_password' => 'required_if:mail_driver,smtp|string|max:255|nullable',
            ]);

            // Encrypt sensitive fields (e.g., password)
            if (!empty($validatedData['mail_password'])) {
                $validatedData['mail_password'] = encrypt($validatedData['mail_password']);
            }

            // Save or update school/branch-specific email settings
            SaveOrUpdateSchoolSettings('email', $validatedData, GetSchoolModel(), $request->branch_id);

            return redirect()
                ->route('settings.email.index') // Standardized route name
                ->with('success', 'Email settings updated successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Failed to save email settings: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to save email settings.');
        }
    }
}
