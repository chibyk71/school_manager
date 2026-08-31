<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        \Illuminate\Support\Facades\Gate::policy(
            \App\Models\Promotion\PromotionBatch::class,
            \App\Policies\Promotion\PromotionPolicy::class
        );

        \Illuminate\Support\Facades\Gate::policy(
            \App\Models\Student\Enrollment::class,
            \App\Policies\Student\EnrollmentPolicy::class
        );

        Vite::prefetch(concurrency: 3);
    }
}
