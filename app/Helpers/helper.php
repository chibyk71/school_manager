<?php

use App\Models\School;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
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
        $schoolSettings = $model? $model->getSetting($key, []): [];

        // Merge settings: Use school-specific if set, otherwise tenant defaults
        return array_replace_recursive($tenantSettings, array_filter($schoolSettings, fn($value) => $value !== null));
    }
}

if (!function_exists('GetSchoolModel')) {
    /**
     * Get the current school model instance for the currently authenticated user or that this request is for
     */
    function GetSchoolModel(): ?School {
        return app('schoolManager')->getActiveSchool();

        // if there is no school in the request, check if the user is authenticated and belongs to a school
        // if (auth()->check()) {
        //     // check if the user belongs to a school and return that school model instance
        //     return auth()->user()->school;
        // }

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

if (!function_exists('createExcerpt')) {

    /**
     * Creates an excerpt from the given content.
     *
     * This function strips HTML tags from the content, trims it, and then creates
     * an excerpt of the specified length. If the content exceeds the specified length,
     * it appends a specified string (default is '...') to the end of the excerpt.
     *
     * @param string $content The content to create an excerpt from.
     * @param int $length The maximum number of words for the excerpt. Default is 20.
     * @param string $more The string to append if the content exceeds the length. Default is '...'.
     * @return string The generated excerpt.
     */
    function createExcerpt($content, $length = 20, $more = '...')
    {
        $excerpt = strip_tags(trim($content));
        $words = str_word_count($excerpt, 2);
        if (count($words) > $length) {
            $words = array_slice($words, 0, $length, true);
            end($words);
            // $position = key( $words ) + strlen( current( $words ) );
            $position = key($words);
            $excerpt = substr($excerpt, 0, $position) . $more;
        }
        return $excerpt;
    }
}

if (! function_exists('array_get')) {
    /**
     * Get an item from an array using "dot" notation.
     *
     * @param  \ArrayAccess|array  $array
     * @param  string|int  $key
     * @param  mixed  $default
     * @return mixed
     */
    function array_get($array, $key, $default = null)
    {
        return Arr::get($array, $key, $default);
    }
    
}


    
if (!function_exists('modelClassFromName')) {
    /**
     * Get an instance of a model class from its name.
     *
     * @param string $name
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    function modelClassFromName(string $name): ?Model
    {
        $baseNamespace = 'App\\Models\\';
        $modelsPath = app_path('Models');

        // Convert to StudlyCase
        $className = Str::studly($name);

        // Scan all PHP files in Models directory recursively
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($modelsPath));

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                // Get the relative path from Models directory
                $relativePath = str_replace($modelsPath . DIRECTORY_SEPARATOR, '', $file->getPathname());

                // Convert to namespace format (replace slashes with \ and remove .php)
                $modelNamespace = $baseNamespace . str_replace(['/', '\\', '.php'], ['\\', '\\', ''], $relativePath);

                // Check if the class exists and is a Model
                if (class_exists($modelNamespace) && is_subclass_of($modelNamespace, Model::class)) {
                    // Match the last part of the namespace with the given name
                    if (class_basename($modelNamespace) === $className) {
                        return new $modelNamespace;
                    }
                }
            }
        }

        return null;
    }
}
