<?php

namespace App\Providers;

use App\Services\AcademicSessionService;
use App\Services\SchoolService;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;

/**
 * MyServiceProvider
 *
 * Core Responsibilities:
 * ----------------------
 * This service provider is responsible for bootstrapping and registering application services
 * related to school management in the multi-tenant SaaS application.
 *
 * Key Features:
 * -------------
 * 1. Service Registration:
 *    - Registers SchoolService as a singleton ('schoolManager') for managing school contexts
 *    - Registers AcademicSessionService as a singleton ('academicContext')
 *    - Provides convenient aliases for dependency injection
 *
 * 2. Bootstrapping:
 *    - Sets up event listeners for key model events (e.g., School::created)
 *    - Logs school creation events
 *    - Can be extended to register middleware, event listeners, or other bootstrapping logic
 *
 * Design Principles:
 * ------------------
 * - Thin provider: Focused only on registration and basic bootstrapping
 * - Event-driven: Uses Laravel events for decoupling (e.g., SchoolCreated event preferred over direct listeners)
 * - Error handling: Wrapped in try-catch with logging for robustness
 *
 * Important Notes:
 * ----------------
 * - Event listeners here should be minimal — prefer EventServiceProvider for complex mappings
 * - The School::created listener logs creation but avoids heavy operations (e.g., no direct settings init)
 * - For production: Consider migrating event mappings to EventServiceProvider
 * - The setActiveSchool call in created listener is now removed to prevent side effects in queued/async contexts
 *
 * Future Improvements:
 * --------------------
 * - Migrate to EventServiceProvider for better organization of event-listener mappings
 * - Add middleware registration if needed for school/section context enforcement
 */
class MyServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * Binds the SchoolService as a singleton with the 'schoolManager' key,
     * making it available throughout the application for managing school and branch contexts.
     *
     * @return void
     *
     * @throws \Exception If service registration fails.
     */
    public function register(): void
    {
        try {
            $this->app->singleton('schoolManager', function () {
                return new SchoolService();
            });

            $this->app->singleton('academicContext', fn() => new AcademicSessionService());

            // Register alias for easier access
            $this->app->alias('schoolManager', SchoolService::class);
            $this->app->alias('academicContext', AcademicSessionService::class);
        } catch (\Exception $e) {
            Log::error('Failed to register SchoolService: ' . $e->getMessage());
            throw new \Exception('Unable to register SchoolService.');
        }
    }

    /**
     * Bootstrap services.
     *
     * Sets up event listeners and middleware for school and branch management.
     *
     * @return void
     */
    public function boot(): void
    {
        // Register minimal event listener for school creation
        // Logs the creation event — all heavy lifting (settings, defaults) moved to SchoolCreated event listeners
        \App\Models\School::created(function ($school) {
            Log::info("School created: {$school->name} (ID: {$school->id})");
            // Removed: app('schoolManager')->setActiveSchool($school);
            // This could cause issues in queued/async contexts or multi-tenant flows
        });
    }
}
