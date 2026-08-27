<?php

use App\Http\Controllers\PromotionBatchController;
use Illuminate\Support\Facades\Route;

/*
| Student Promotion Module routes
| Required from routes/web.php on feature/promotion-module
*/

Route::resource('promotions', PromotionBatchController::class)
    ->only(['index', 'store', 'show']);

Route::get('promotions/{batch}/review', [PromotionBatchController::class, 'review'])
    ->name('promotions.review');

Route::post('promotions/{batch}/students/{student}/override', [PromotionBatchController::class, 'override'])
    ->name('promotions.override');

Route::post('promotions/{batch}/approve', [PromotionBatchController::class, 'approve'])
    ->name('promotions.approve');

Route::post('promotions/{batch}/execute', [PromotionBatchController::class, 'execute'])
    ->name('promotions.execute');

Route::post('promotions/{batch}/cancel', [PromotionBatchController::class, 'cancel'])
    ->name('promotions.cancel');
