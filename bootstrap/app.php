<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(
            append: [
                \App\Http\Middleware\HandleInertiaRequests::class,
                \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
                \App\Http\Middleware\EnsureCurrentSession::class,
                \App\Http\Middleware\EnsurePasswordChanged::class,
                \App\Http\Middleware\EnsureTwoFactorVerified::class,
            ],
            prepend: [
                \App\Http\Middleware\SchoolContext::class,
                \App\Http\Middleware\CheckMaintenanceMode::class,
            ]
        );

        $middleware->alias([
            'school' => \App\Http\Middleware\SchoolContext::class,
            'ensure.current.session' => \App\Http\Middleware\EnsureCurrentSession::class,
            'password.changed' => \App\Http\Middleware\EnsurePasswordChanged::class,
            '2fa.verified' => \App\Http\Middleware\EnsureTwoFactorVerified::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule) {
        $schedule->command('finance:punish-overdue')
            ->dailyAt('00:05')
            ->withoutOverlapping()
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/punishment.log'));

        $schedule->command('admissions:process-lifecycle --reminders')
            ->hourly()
            ->withoutOverlapping()
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/admissions-lifecycle.log'));

        $schedule->command('enrollments:process-reminders --requirements')
            ->dailyAt('08:30')
            ->withoutOverlapping()
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/enrollment-reminders.log'));

        $schedule->command('docent:insights:prune')
            ->dailyAt('03:15')
            ->withoutOverlapping()
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/docent-insights-prune.log'));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
