<?php

namespace App\Helpers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/**
 * ModelResolver
 *
 * Production-ready helper that resolves Eloquent model classes from friendly base names.
 *
 * Core purpose:
 *   - Allow frontend / API to send short names like 'student', 'teacher', 'guardian'
 *   - Convert them to full qualified class names (FQCN) like App\Models\Academic\Student
 *   - Enable safe, refactor-friendly polymorphic usage in custom fields module
 *
 * Features:
 *   - Recursive filesystem scan of app/Models/
 *   - Forever cache with tags (fast after first miss)
 *   - Graceful degradation in production (logs instead of crashing)
 *   - Collision detection & logging (two models with same basename)
 *   - Reverse lookup (FQCN → basename)
 *   - Easy cache invalidation
 *
 * Performance:
 *   - First call after cache miss: ~50–500ms (depending on number of models)
 *   - Subsequent calls: instant (cached array lookup)
 *
 * Security:
 *   - Only includes classes that extend Eloquent Model
 *   - No dynamic code execution from filesystem
 *
 * Usage:
 *   ModelResolver::get('Student')          → 'App\Models\Academic\Student' | null
 *   ModelResolver::getOrFail('Staff')      → throws in dev, returns null in prod + logs
 *   ModelResolver::alias($fqcn)            → 'Staff'
 *   ModelResolver::clearCache()            → manual invalidation
 *
 * Invalidation command:
 *   php artisan cache:clear --tags=model_resolver
 */
class ModelResolver
{
    private const CACHE_KEY_BASE = 'model_resolver_map';
    private const CACHE_TAG = 'model_resolver';

    /**
     * Get the cache key (environment-aware)
     */
    private static function cacheKey(): string
    {
        return self::CACHE_KEY_BASE . ':' . app()->environment();
    }

    /**
     * Resolve model FQCN from friendly base name (case-insensitive)
     *
     * @param string $name e.g. 'student', 'Teacher', 'guardian'
     * @return string|null Full class name or null if not found
     */
    public static function get(string $name): ?string
    {
        $studly = Str::studly(trim($name));

        $map = Cache::tags(self::CACHE_TAG)->rememberForever(
            self::cacheKey(),
            fn() => self::buildModelMap()
        );

        return $map[$studly] ?? null;
    }

    /**
     * Same as get(), but throws in local/dev environments
     * In production: logs error and returns null
     *
     * @throws RuntimeException In local/dev if model not found
     */
    public static function getOrFail(string $name): string
    {
        $class = self::get($name);

        if ($class === null) {
            $message = "Model class could not be resolved for name: '{$name}'. " .
                "Possible typo or model missing. " .
                "Run 'php artisan cache:clear --tags=model_resolver' after adding models.";

            if (app()->environment('local', 'testing')) {
                throw new RuntimeException($message);
            }

            Log::error($message, [
                'resolver_input' => $name,
                'environment' => app()->environment(),
            ]);

            return ''; // or you could return a fallback class if desired
        }

        return $class;
    }

    /**
     * Reverse lookup: get base name from full class name
     *
     * @param string $class Full qualified class name
     * @return string|null Basename or null if invalid/not a model
     */
    public static function alias(string $class): ?string
    {
        if (!class_exists($class) || !is_subclass_of($class, Model::class)) {
            return null;
        }

        return class_basename($class);
    }

    /**
     * Build the internal map by scanning app/Models recursively
     *
     * @return array<string, string> [ 'Student' => 'App\Models\Academic\Student', ... ]
     */
    private static function buildModelMap(): array
    {
        $map = [];
        $modelsPath = app_path('Models');

        if (!is_dir($modelsPath)) {
            Log::warning("Models directory not found during resolver scan", [
                'path' => $modelsPath,
            ]);
            return $map;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($modelsPath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = str_replace($modelsPath . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $fqcn = 'App\\Models\\' . str_replace(['/', '.php'], ['\\', ''], $relativePath);

            // Only include valid Eloquent models
            if (!class_exists($fqcn) || !is_subclass_of($fqcn, Model::class)) {
                continue;
            }

            $baseName = class_basename($fqcn);

            // Handle name collisions (two models with same base name)
            if (isset($map[$baseName]) && $map[$baseName] !== $fqcn) {
                Log::warning("Model name collision detected - keeping first found", [
                    'basename' => $baseName,
                    'first_found' => $map[$baseName],
                    'new_candidate' => $fqcn,
                    'environment' => app()->environment(),
                ]);

                // Optional: prefer certain namespaces (uncomment & customize if needed)
                // if (str_contains($fqcn, '\\Academic\\') && !str_contains($map[$baseName], '\\Academic\\')) {
                //     $map[$baseName] = $fqcn;
                // }

                continue;
            }

            $map[$baseName] = $fqcn;
        }

        return $map;
    }

    /**
     * Clear all cached model maps
     * Useful after adding, renaming or removing models
     */
    public static function clearCache(): void
    {
        Cache::tags(self::CACHE_TAG)->flush();
    }

    /**
     * Remove a single entry from cache (dev convenience)
     */
    public static function forget(string $name): void
    {
        $studly = Str::studly(trim($name));
        $key = self::cacheKey();

        $map = Cache::tags(self::CACHE_TAG)->get($key, []);
        unset($map[$studly]);
        Cache::tags(self::CACHE_TAG)->forever($key, $map);
    }
}
