<?php

namespace App\Providers;

use App\Services\SchoolService;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Log;

/**
 * Service provider for registering the SchoolService singleton.
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

            // Register alias for easier access
            $this->app->alias('schoolManager', SchoolService::class);
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
        // Register event listener for school creation
        \App\Models\School::created(function ($school) {
            Log::info("School created: {$school->name} (ID: {$school->id})");
            // Initialize default settings for new schools
            app('schoolManager')->setActiveSchool($school);
        });
    }
}
