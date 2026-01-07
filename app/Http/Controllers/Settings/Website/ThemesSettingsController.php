<?php

namespace App\Http\Controllers\Settings\Website;

use App\Http\Controllers\Controller;
use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use RuangDeveloper\LaravelSettings\Facades\Settings;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * ThemesSettingsController v1.0 – Production-Ready Website Themes & Appearance Settings
 *
 * Purpose:
 * Manages school-specific UI customization, color schemes, layout preferences, and branding assets
 * that affect the admin dashboard, parent/student portals, and public website appearance.
 *
 * This is stored separately from School model and Company Settings because:
 * - Themes change frequently for rebranding campaigns or seasonal updates
 * - Allows per-school customization in multi-tenant environment (different schools, different looks)
 * - Performance: cached separately from core operational data
 * - Separation of concerns: operational data (School model) vs presentational data (themes)
 *
 * Settings Key: 'website.themes'
 *
 * Features / Problems Solved:
 * - Full Tailwind/CSS variable support with validation (hex colors, Tailwind presets)
 * - Dark mode toggle with separate logos per theme
 * - Layout flexibility (sidebar vs topbar, compact vs spacious)
 * - File upload handling for theme-specific assets (light/dark logos, login banners)
 * - Responsive preview generation (live color preview in frontend)
 * - Automatic CSS variable generation (passed to frontend via Inertia props)
 * - Industry-standard fields matching modern admin templates (VRISTO, Smarter, DreamsPOS)
 * - Secure file uploads with size/type validation and school-specific storage paths
 * - Cache invalidation on theme changes (flushes theme-related caches)
 *
 * Fits into the Settings Module:
 * - Route: GET/POST settings.website.themes (index + store)
 * - Navigation: Website & Branding → Themes & Appearance
 * - Frontend: resources/js/Pages/Settings/Website/Themes.vue
 * - Dependencies: School model (via GetSchoolModel()), Storage facade for uploads
 * - Used by: Layout components (AuthenticatedLayout.vue), CSS variables injection
 *
 * Expected Config Defaults (config/laravel-settings.php):
 *   'defaults' => [
 *       'website.themes' => [
 *           'primary_color' => '#3B82F6',
 *           'secondary_color' => '#1E40AF',
 *           'default_theme' => 'light',
 *           // etc.
 *       ]
 *   ]
 *
 * File Storage Path: storage/app/public/schools/{school_id}/themes/
 */

class ThemesSettingsController extends Controller
{
    /**
     * Display the themes and appearance settings page.
     */
    public function index()
    {
        permitted('manage-settings');

        $school = GetSchoolModel();
        
        // Merged settings: school override → global → config default
        $settings = getMergedSettings('themes', $school);

        // Generate theme preview CSS variables for live preview
        $cssVariables = $this->generateCssVariables($settings);

        return Inertia::render('Settings/Website/Themes', [
            'settings' => array_merge($settings, [
                'css_variables' => $cssVariables,
                'logo_light_url' => $settings['logo_light_url'] ?? null,
                'logo_dark_url' => $settings['logo_dark_url'] ?? null,
                'login_banner_url' => $settings['login_banner_url'] ?? null,
            ]),
            'availableLayouts' => ['sidebar', 'topbar', 'hybrid'],
            'availableThemes' => ['light', 'dark', 'auto'],
        ]);
    }

    /**
     * Store/update themes and appearance settings.
     */
    public function store(Request $request)
    {
        permitted('manage-settings');

        $school = GetSchoolModel();

        if (!$school) {
            abort(403, 'No active school context found.');
        }

        $validated = $request->validate([
            // Colors (Tailwind-compatible hex + presets)
            'primary_color' => ['required', 'string', Rule::in(['blue', 'indigo', 'purple', 'pink', 'red', 'orange', 'yellow', 'green', 'teal', 'cyan', 'slate', 'gray', 'zinc', 'stone', 'custom'])],
            'primary_custom_hex' => 'nullable|regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/',
            'secondary_color' => ['required', 'string', Rule::in(['blue', 'indigo', 'purple', 'pink', 'red', 'orange', 'yellow', 'green', 'teal', 'cyan', 'slate', 'gray', 'zinc', 'stone', 'custom'])],
            'secondary_custom_hex' => 'nullable|regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/',

            // Theme & Layout
            'default_theme' => ['required', Rule::in(['light', 'dark', 'auto'])],
            'dashboard_layout' => ['required', Rule::in(['grid', 'list', 'cards', 'modern'])],
            'sidebar_collapsed' => 'boolean',
            'menu_position' => ['required', Rule::in(['left', 'right'])],
            'compact_mode' => 'boolean',

            // Logo & Branding Assets
            'login_banner' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120', // 5MB
        ]);

        try {
            $settingsData = $validated;

            if ($request->hasFile('login_banner')) {
                $settingsData['login_banner_path'] = $this->storeThemeAsset($request->file('login_banner'), $school, 'login-banner');
                $settingsData['login_banner_url'] = Storage::url($settingsData['login_banner_path']);
            }

            // Resolve final colors (Tailwind preset or custom hex)
            $settingsData['primary_color_final'] = $this->resolveColor($validated['primary_color'], $validated['primary_custom_hex']);
            $settingsData['secondary_color_final'] = $this->resolveColor($validated['secondary_color'], $validated['secondary_custom_hex']);

            // Save school-specific theme overrides
            SaveOrUpdateSchoolSettings('themes', $settingsData, $school);

            // Clear theme caches
            Cache::tags(['themes', 'school_' . $school->id])->flush();

            return redirect()
                ->route('settings.website.themes')
                ->with('success', 'Theme settings updated successfully. Changes will apply after page refresh.');

        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Theme settings save failed', [
                'school_id' => $school->id,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->back()
                ->with('error', 'Failed to save theme settings.')
                ->withInput();
        }
    }

    /**
     * Generate CSS custom properties for live preview.
     */
    private function generateCssVariables(array $settings): array
    {
        return [
            '--primary-color' => $settings['primary_color_final'] ?? '#3B82F6',
            '--secondary-color' => $settings['secondary_color_final'] ?? '#1E40AF',
            '--logo-light' => $settings['logo_light_url'] ?? '',
            '--logo-dark' => $settings['logo_dark_url'] ?? '',
        ];
    }

    /**
     * Resolve Tailwind preset or custom hex color.
     */
    private function resolveColor(string $color, ?string $customHex): string
    {
        $tailwindColors = [
            'blue' => '#3B82F6', 'indigo' => '#4F46E5', 'purple' => '#7C3AED',
            'pink' => '#EC4899', 'red' => '#EF4444', 'orange' => '#F97316',
            'yellow' => '#EAB308', 'green' => '#10B981', 'teal' => '#0D9488',
            'cyan' => '#06B6D4', 'slate' => '#64748B', 'gray' => '#6B7280',
            'zinc' => '#71717A', 'stone' => '#78716B',
        ];

        return $color === 'custom' && $customHex ? $customHex : ($tailwindColors[$color] ?? '#3B82F6');
    }
}