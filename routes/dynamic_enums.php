<?php

use App\Http\Controllers\Settings\System\DynamicEnumsController;
use Illuminate\Support\Facades\Route;

Route::prefix('settings/system')->name('settings.system.')->middleware(['auth'])->group(function () {
    Route::prefix('dynamic-enums')->name('dynamic-enums.')->group(function () {
        Route::get('/', [DynamicEnumsController::class, 'index'])->name('index');
        Route::get('{key}', [DynamicEnumsController::class, 'show'])->name('show')->where('key', '[a-z0-9_.\-]+');
        Route::patch('{key}/definition', [DynamicEnumsController::class, 'updateDefinition'])->name('definition.update');
        Route::post('{key}/options', [DynamicEnumsController::class, 'storeOption'])->name('options.store');
        Route::patch('{key}/options/{option}', [DynamicEnumsController::class, 'updateOption'])->name('options.update');
        Route::post('{key}/options/{option}/activate', [DynamicEnumsController::class, 'activateOption'])->name('options.activate');
        Route::post('{key}/options/{option}/deactivate', [DynamicEnumsController::class, 'deactivateOption'])->name('options.deactivate');
        Route::post('{key}/options/{option}/make-required', [DynamicEnumsController::class, 'makeRequired'])->name('options.make-required');
        Route::post('{key}/options/{option}/remove-required', [DynamicEnumsController::class, 'removeRequired'])->name('options.remove-required');
        Route::post('{key}/options/{option}/reset', [DynamicEnumsController::class, 'resetOverride'])->name('options.reset');
        Route::delete('{key}/options/{option}', [DynamicEnumsController::class, 'destroyOption'])->name('options.destroy');
    });
});
