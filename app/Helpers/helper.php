<?php

use App\Models\School;
use RuangDeveloper\LaravelSettings\Facades\Settings;

if (!function_exists('getMergedSettings')) {
    /**
     * Get merged settings with tenant defaults and school-specific overrides.
     *
     * @param string $key
     * @return array
     */
    function getMergedSettings(string $key, $model): array
    {
        $tenantSettings = Settings::get($key, []);
        $schoolSettings = $model? $model::getSetting($key, []): [];

        // Merge settings: Use school-specific if set, otherwise tenant defaults
        return array_replace_recursive($tenantSettings, array_filter($schoolSettings, fn($value) => $value !== null));
    }
}

if (!function_exists('GetSchoolModel')) {
    /**
     * Get the current school model instance for the currently authenticated user or that this request is for
     */
    function GetSchoolModel(): ?School {
        // first of all check if there is a school item in the request, if so, use that one
        if (request()->has('school')) {
            return School::find(request('school'));
        }

        // if there is no school in the request, check if the user is authenticated and belongs to a school
        if (auth()->check()) {
            // check if the user belongs to a school and return that school model instance
            return auth()->user()->school;
        }

        // return null if no school is found
        return null;
    }
}

if (!function_exists('SaveOrUpdateSchoolSettings')) {
    /**
     * Save or update the school-specific settings
     */
    function SaveOrUpdateSchoolSettings($key, $validatedData): void {
        // Get the current school model instance for the currently authenticated user or that this request is for
        // or use the Settings Model if no school is found to save as a default/system wide setting.

        $model = GetSchoolModel() ?? Settings::class;

        $model->setSetting($key, $validatedData);
    }
}
