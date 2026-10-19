<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

use App\Jobs\FileManager\DiscoverTrashedFilesForPurgeJob;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new DiscoverTrashedFilesForPurgeJob(50))->hourly();
