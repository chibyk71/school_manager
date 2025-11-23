<?php

use App\Models\School;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Laratrust\LaratrustFacade;
use RuangDeveloper\LaravelSettings\Facades\Settings;
use Illuminate\Support\Facades\Log;

if (!function_exists('getMergedSettings')) {
    /**
     * Get merged settings with tenant defaults and school-specific overrides.
     *
     * @param string $key The settings key to retrieve (e.g., 'tax', 'fees').
     * @param Model|null $model The school or branch model instance.
     * @return array The merged settings array.
     *
     * @throws \InvalidArgumentException If the key is invalid.
     * @throws \Exception If settings retrieval fails.
     */
    function getMergedSettings(string $key, $model): array
    {
        try {
            if (empty($key)) {
                throw new \InvalidArgumentException('Settings key cannot be empty.');
            }

            // Fetch tenant-level settings
            $tenantSettings = Settings::get($key, []);

            // Fetch school-specific settings
            $schoolSettings = $model ? $model->getSetting($key, []) : [];

            // Merge settings: tenant < school < branch
            return array_replace_recursive(
                $tenantSettings,
                array_filter($schoolSettings, fn($value) => $value !== null)
            );
        } catch (\Exception $e) {
            Log::error("Failed to fetch settings for key '$key': " . $e->getMessage());
            throw $e;
        }
    }
}

if (!function_exists('GetSchoolModel')) {

    /**
     * Get the current school model instance for the authenticated user or request.
     *
     * @return School|null The active school model instance, or null if not found.
     *
     * @throws \Exception If school retrieval fails.
     */
    function GetSchoolModel(): ?School
    {
        try {
            // Use SchoolManager to get the active school
            $school = app('schoolManager')->getActiveSchool();

            // Fallback to authenticated user's school if no school is set in the request
            if (!$school && auth()->check()) {
                $school = auth()->user()->school;
            }

            return $school;
        } catch (\Exception $e) {
            Log::error('Failed to retrieve active school: ' . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('permitted')) {

    /**
     * Check if the current user has the specified permission(s) and abort if not authorized.
     *
     * @param string|array $permissions The permission(s) to check (e.g., 'manage-settings').
     * @param bool $jsonResponse Whether to return a JSON response for API calls.
     * @return void
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException If unauthorized.
     */
    function permitted(string|array $permissions, bool $jsonResponse = false): void
    {
        if (!LaratrustFacade::hasPermission($permissions)) {
            if ($jsonResponse) {
                response()->json(['error' => 'Unauthorized action.'], 403)->send();
                exit;
            }
            abort(403, 'Unauthorized action.');
        }
    }
}

if (!function_exists('SaveOrUpdateSchoolSettings')) {

    /**
     * Save or update school-specific or branch-specific settings.
     *
     * @param string $key The settings key to save (e.g., 'tax', 'fees').
     * @param array $validatedData The validated settings data to save.
     * @param Model|null $model The school or branch model instance.
     * @param int|null $branchId Optional branch ID for branch-specific settings.
     * @return void
     *
     * @throws \InvalidArgumentException If the key or data is invalid.
     * @throws \Exception If settings save fails.
     */
    function SaveOrUpdateSchoolSettings(string $key, array $validatedData, $model = null): void
    {
        try {
            if (empty($key)) {
                throw new \InvalidArgumentException('Settings key cannot be empty.');
            }
            if (empty($validatedData)) {
                throw new \InvalidArgumentException('Settings data cannot be empty.');
            }

            // Determine the model to save settings to
            $targetModel = ($model ?? GetSchoolModel());

            // Fallback to global settings if no model is provided
            $targetModel = $targetModel ?? Settings::class;

            // Save settings
            $targetModel->setSetting($key, $validatedData);
        } catch (\Exception $e) {
            Log::error("Failed to save settings for key '$key': " . $e->getMessage());
            throw $e;
        }
    }
}

if (!function_exists('createExcerpt')) {

    /**
     * Create an excerpt from the given content.
     *
     * @param string $content The content to create an excerpt from.
     * @param int $length The maximum number of words for the excerpt. Default is 20.
     * @param string $more The string to append if content exceeds length. Default is '...'.
     * @return string The generated excerpt.
     */
    function createExcerpt($content, $length = 20, $more = '...'): string
    {
        $excerpt = strip_tags(trim($content));
        $words = str_word_count($excerpt, 2);
        if (count($words) > $length) {
            $words = array_slice($words, 0, $length, true);
            end($words);
            $position = key($words);
            $excerpt = substr($excerpt, 0, $position) . $more;
        }
        return $excerpt;
    }
}

if (!function_exists('array_get')) {
    /**
     * Get an item from an array using "dot" notation.
     *
     * @param \ArrayAccess|array $array The array to search in.
     * @param string|int $key The key to retrieve (supports dot notation).
     * @param mixed $default The default value if the key is not found.
     * @return mixed The retrieved value or default.
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
     * @param string $name The name of the model (e.g., 'School', 'Branch').
     * @return Model|null The model instance, or null if not found.
     *
     * @throws \Exception If model resolution fails.
     */
    function modelClassFromName(string $name): ?Model
    {
        try {
            $baseNamespace = 'App\\Models\\';
            $className = Str::studly($name);

            // Cache model mappings to improve performance
            static $modelCache = [];
            if (isset($modelCache[$className])) {
                return new $modelCache[$className];
            }

            // Check if class exists in the expected namespace
            $modelNamespace = $baseNamespace . $className;
            if (class_exists($modelNamespace) && is_subclass_of($modelNamespace, Model::class)) {
                $modelCache[$className] = $modelNamespace;
                return new $modelNamespace;
            }

            // Fallback to scanning Models directory (less frequent)
            $modelsPath = app_path('Models');
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($modelsPath));

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $relativePath = str_replace($modelsPath . DIRECTORY_SEPARATOR, '', $file->getPathname());
                    $modelNamespace = $baseNamespace . str_replace(['/', '\\', '.php'], ['\\', '\\', ''], $relativePath);

                    if (class_exists($modelNamespace) && is_subclass_of($modelNamespace, Model::class)) {
                        if (class_basename($modelNamespace) === $className) {
                            $modelCache[$className] = $modelNamespace;
                            return new $modelNamespace;
                        }
                    }
                }
            }

            return null;
        } catch (\Exception $e) {
            Log::error("Failed to resolve model '$name': " . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('generateEnrollmentId')) {
    /**
     * Build an enrollment ID from the school-specific format.
     *
     * @param  \App\Models\School  $school
     * @param  int                 $numericPart   (the auto-increment value)
     * @return string
     */
    function generateEnrollmentId(\App\Models\School $school, int $numericPart): string
    {
        $settings = getMergedSettings('user_management', $school);

        $format = $settings['enrollment_id_format'] ?? '{prefix}-{year}-{number}';
        $length = $settings['enrollment_id_number_length'] ?? 6;
        $prefix = $school->code ?? 'SCH';               // you probably have a short code on the school model
        $year = now()->format('Y');

        $number = str_pad($numericPart, $length, '0', STR_PAD_LEFT);

        return str_replace(
            ['{prefix}', '{year}', '{number}'],
            [$prefix, $year, $number],
            $format
        );
    }
}

if (! function_exists('currentSession')) {
    /**
     * @return \App\Models\Academic\AcademicSession|null
     */
    function currentSession(): ?\App\Models\Academic\AcademicSession
    {
        return app('academicContext')->currentSession();
    }
}

if (! function_exists('currentTerm')) {
    /**
     * @return \App\Models\Academic\Term|null
     */
    function currentTerm(): ?\App\Models\Academic\Term
    {
        return app('academicContext')->currentTerm();
    }
}

if (!function_exists('send_school_sms')) {
    /**
     * Send an SMS using the current school's configured providers (with fallback)
     *
     * This is the easiest way to send ad-hoc SMS from anywhere in your app.
     * Automatically resolves the current school context.
     *
     * @param string              $to            Phone number (e.g. 08012345678 or +2348012345678)
     * @param string              $message       SMS body
     * @param \App\Models\School|null $school    Optional: override school (defaults to current)
     * @param array               $options       Optional: ['sender' => 'CustomID', 'force' => true]
     *
     * @return bool  true if sent via at least one provider
     */
    function send_school_sms(
        string $to,
        string $message,
        ?\App\Models\School $school = null,
        array $options = []
    ): bool {
        // Normalize phone number (remove spaces, dashes, etc.)
        $to = preg_replace('/[^0-9+]/', '', $to);

        // Validate basic phone length
        if (strlen($to) < 10 || strlen($to) > 15) {
            \Log::warning('Invalid phone number for SMS', ['to' => $to, 'message' => $message]);
            return false;
        }

        // Resolve school if not provided
        if (!$school) {
            $school = GetSchoolModel();
            if (!$school) {
                \Log::warning('send_school_sms: No active school found', ['to' => $to]);
                return false;
            }
        }

        // Override sender if provided
        if (!empty($options['sender'])) {
            // Temporarily override global sender for this message
            $original = getMergedSettings('sms', $school);
            $modified = $original;
            $modified['global_sender_id'] = $options['sender'];
            // Settings::setTemporary($modified);
        }

        try {
            $sent = app(\App\Services\SmsService::class)->send($to, $message, $school);

            // Fire event or log if needed
            if ($sent) {
                // event(new \App\Events\SmsSent($to, $message, $school));
            }

            return $sent;
        } catch (\Throwable $e) {
            \Log::error('send_school_sms helper failed', [
                'to' => $to,
                'school_id' => $school->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}

