<?php

use App\Http\Controllers\FileManager\BrowserController;
use App\Http\Controllers\FileManager\FileController;
use App\Http\Controllers\FileManager\FolderController;
use App\Http\Controllers\FileManager\ModuleConfigurationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->prefix('file-manager')->name('file-manager.')->group(function () {
    Route::get('/', [BrowserController::class, 'index'])->name('browser');

    Route::get('folders', [FolderController::class, 'index'])->name('folders.index');
    Route::post('folders', [FolderController::class, 'store'])->name('folders.store');
    Route::get('folders/{id}', [FolderController::class, 'show'])->name('folders.show');
    Route::patch('folders/{id}', [FolderController::class, 'update'])->name('folders.update');
    Route::delete('folders/{id}', [FolderController::class, 'destroy'])->name('folders.destroy');

    Route::get('files', [FileController::class, 'index'])->name('files.index');
    Route::get('files/trash', [FileController::class, 'trashIndex'])->name('files.trash');
    Route::post('files', [FileController::class, 'store'])->name('files.store');
    Route::get('files/{id}', [FileController::class, 'show'])->name('files.show');
    Route::get('files/{id}/download', [FileController::class, 'download'])->name('files.download');
    Route::patch('files/{id}/move', [FileController::class, 'move'])->name('files.move');
    Route::post('files/{id}/restore', [FileController::class, 'restore'])->name('files.restore');
    Route::post('files/{id}/clear-purge-failure', [FileController::class, 'clearPurgeFailure'])->name('files.clear-purge-failure');
    Route::delete('files/{id}', [FileController::class, 'destroy'])->name('files.destroy');
    Route::post('files/{id}/attachments', [FileController::class, 'attach'])->name('files.attach');
    Route::delete('files/{id}/attachments/{attachmentId}', [FileController::class, 'detach'])->name('files.detach');

    Route::get('modules', [ModuleConfigurationController::class, 'index'])->name('modules.index');
    Route::patch('modules/{moduleCode}', [ModuleConfigurationController::class, 'update'])->name('modules.update');
});
