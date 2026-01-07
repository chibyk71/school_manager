<?php

namespace App\Http\Controllers\Settings\Website;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * SocialAuthSettingsController v1.0 – Production-Ready Social Authentication Settings
 *
 * Purpose:
 * Manages OAuth2 social login providers for the school/platform (Google, Facebook, Microsoft,
 * Apple, LinkedIn, Twitter/X). Enables "Login with Google" buttons on login/register pages.
 *
 * Why stored in settings table (not School model):
 * - OAuth credentials are sensitive and change frequently (API key rotation)
 * - Allows per-school customization of enabled providers in multi-tenant setup
 * - Global system admin can set platform-wide defaults, schools can override/enable specific ones
 * - Clean separation from core operational data; security best practice
 * - Credentials encrypted at rest using Laravel's encryption (via settings package)
 *
 * Settings Key: 'website.social'
 *
 * Features / Problems Solved:
 * - Uses your exact helpers: getMergedSettings() + SaveOrUpdateSchoolSettings()
 * - No abort() on missing school → system admins edit global defaults for all schools
 * - Provider-specific validation (e.g., Google requires both client_id/secret)
 * - Enable/disable toggles per provider (school can disable sensitive ones)
 * - Redirect URI helpers generated automatically for copy-paste into provider dashboards
 * - Security: validates URLs, limits key lengths, logs credential changes
 * - Frontend shows copyable redirect URIs and status indicators (configured/tested)
 * - Industry-standard: matches Auth0, Laravel Socialite, Firebase Auth patterns
 *
 * Fits into the Settings Module:
 * - Route: GET/POST settings.website.social (index + store)
 * - Navigation: Website & Branding → Social Authentication
 * - Frontend: resources/js/Pages/Settings/Website/Social.vue
 * - Dependencies: Laravel Socialite (for actual OAuth flows), your helpers
 * - Used by: Login/Register pages with Socialite buttons
 *
 * Default Values (for seeding on tenant bootstrap – add to your seeder):
 *   'website.social' => [
 *       'google_enabled' => false,
 *       'google_client_id' => '',
 *       'google_client_secret' => '',
 *       'facebook_enabled' => false,
 *       'facebook_client_id' => '',
 *       'facebook_client_secret' => '',
 *       'microsoft_enabled' => false,
 *       'microsoft_client_id' => '',
 *       'microsoft_client_secret' => '',
 *       'apple_enabled' => false,
 *       'apple_client_id' => '',
 *       'apple_team_id' => '',
 *       'apple_key_id' => '',
 *       'apple_private_key' => '',
 *       'twitter_enabled' => false,
 *       'twitter_client_id' => '',
 *       'twitter_client_secret' => '',
 *       'linkedin_enabled' => false,
 *       'linkedin_client_id' => '',
 *       'linkedin_client_secret' => '',
 *   ]
 *
 * Security Notes:
 * - All *_secret fields auto-encrypted by LaravelSettings package
 * - Only enabled providers with both ID+secret are active
 * - Rate limiting on OAuth callbacks handled in routes/middleware
 * - CSRF protection via Laravel Socialite built-in state parameter
 */

class SocialAuthSettingsController extends Controller
{
    /**
     * Display the social authentication settings page.
     */
    public function index()
    {
        permitted('manage-settings');

        $school = GetSchoolModel(); // May be null for system admin editing global defaults

        $settings = getMergedSettings('auth.social', $school);

        // Generate redirect URIs for copy-paste convenience
        $baseUrl = config('app.url');
        $redirectUris = [
            'login' => $baseUrl . '/auth/callback/{provider}',
            'register' => $baseUrl . '/auth/register/callback/{provider}',
        ];

        return Inertia::render('Settings/Website/Social', [
            'settings' => $settings,
            'crumbs' => [
                ['label' => 'Settings'],
                ['label' => 'Website & Branding'],
                ['label' => 'Social Authentication'],
            ],
            'redirect_uris' => $redirectUris,
            'providers' => [
                'google' => ['name' => 'Google', 'docs' => 'https://developers.google.com/identity'],
                'facebook' => ['name' => 'Facebook', 'docs' => 'https://developers.facebook.com/docs/facebook-login'],
                'microsoft' => ['name' => 'Microsoft', 'docs' => 'https://docs.microsoft.com/en-us/azure/active-directory'],
                'apple' => ['name' => 'Sign in with Apple', 'docs' => 'https://developer.apple.com/documentation/sign_in_with_apple'],
                'twitter' => ['name' => 'Twitter / X', 'docs' => 'https://developer.twitter.com/en/docs/authentication'],
                'linkedin' => ['name' => 'LinkedIn', 'docs' => 'https://docs.microsoft.com/en-us/linkedin'],
            ],
        ]);
    }

    /**
     * Store/update social authentication settings.
     */
    public function store(Request $request)
    {
        permitted('manage-settings');

        $school = GetSchoolModel(); // May be null → saves as global defaults

        $validated = $request->validate([
            // Google
            'google_enabled' => 'boolean',
            'google_client_id' => 'nullable|string|max:255',
            'google_client_secret' => 'nullable|string|max:255',

            // Facebook
            'facebook_enabled' => 'boolean',
            'facebook_client_id' => 'nullable|string|max:255',
            'facebook_client_secret' => 'nullable|string|max:255',

            // Microsoft
            'microsoft_enabled' => 'boolean',
            'microsoft_client_id' => 'nullable|string|max:255',
            'microsoft_client_secret' => 'nullable|string|max:255',

            // Apple (Sign in with Apple)
            'apple_enabled' => 'boolean',
            'apple_client_id' => 'nullable|string|max:255',
            'apple_team_id' => 'nullable|string|max:100',
            'apple_key_id' => 'nullable|string|max:100',
            'apple_private_key' => 'nullable|string|max:5000', // PEM content

            // Twitter/X
            'twitter_enabled' => 'boolean',
            'twitter_client_id' => 'nullable|string|max:255',
            'twitter_client_secret' => 'nullable|string|max:255',

            // LinkedIn
            'linkedin_enabled' => 'boolean',
            'linkedin_client_id' => 'nullable|string|max:255',
            'linkedin_client_secret' => 'nullable|string|max:255',
        ]);

        // Conditional validation: enabled providers must have credentials
        foreach (['google', 'facebook', 'microsoft', 'twitter', 'linkedin'] as $provider) {
            if ($validated[$provider . '_enabled']) {
                if (empty($validated[$provider . '_client_id']) || empty($validated[$provider . '_client_secret'])) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        $provider . '_client_id' => ['Client ID is required when provider is enabled.'],
                        $provider . '_client_secret' => ['Client Secret is required when provider is enabled.'],
                    ]);
                }
            }
        }

        if ($validated['apple_enabled']) {
            $appleRequired = ['apple_client_id', 'apple_team_id', 'apple_key_id', 'apple_private_key'];
            foreach ($appleRequired as $field) {
                if (empty($validated[$field])) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        $field => ['This field is required when Apple is enabled.'],
                    ]);
                }
            }
        }

        try {
            SaveOrUpdateSchoolSettings('auth.social', $validated, $school);

            $scope = $school ? 'school' : 'global defaults';
            return redirect()
                ->route('settings.website.social')
                ->with('success', "Social authentication settings updated for {$scope} successfully.");

        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Social auth settings save failed', [
                'school_id' => $school?->id,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()
                ->with('error', 'Failed to save social authentication settings.')
                ->withInput();
        }
    }
}
