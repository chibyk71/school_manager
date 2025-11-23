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
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'school' => \App\Http\Middleware\SchoolContext::class,
            'ensure.current.session' => \App\Http\Middleware\EnsureCurrentSession::class,
        ]);

        //
    })
    ->withSchedule(function (Schedule $schedule) {
        $schedule->command('finance:punish-overdue')
             ->dailyAt('00:05')  // 5 mins after midnight
             ->withoutOverlapping()
             ->onOneServer()
             ->appendOutputTo(storage_path('logs/punishment.log'));

        // $schedule->job(new App\Jobs\GenerateMonthlyStudentStatement)
        //  ->monthlyOn(1, '09:00') // 1st of every month at 9 AM
        //  ->name('Generate Monthly Statements')
        //  ->withoutOverlapping();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
