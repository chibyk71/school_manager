<?php

use App\Http\Controllers\Api\StatisticsController;
use App\Http\Controllers\SchoolSectionController;
use Illuminate\Support\Facades\Route;

Route::prefix('/options')->group(function() {
    Route::get('/class-levels')->name('class-level.options');
    Route::get('/school-sections', [SchoolSectionController::class, 'options'])->name('school-section.options');
});


Route::prefix('/api')->group(function () {
    Route::get('/stats', [StatisticsController::class, 'index']);
    Route::post('/stats/clear-cache', [StatisticsController::class, 'clearCache']);
});
