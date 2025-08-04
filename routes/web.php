<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ImageController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/images/upload', [ImageController::class, 'showUploadForm'])->name('images.upload.form');
Route::post('/images/upload', [ImageController::class, 'upload'])->name('images.upload');
Route::get('/images/search', [ImageController::class, 'showSearchForm'])->name('images.search.form');
Route::post('/images/search', [ImageController::class, 'search'])->name('images.search');
Route::get('/images/clear', [ImageController::class, 'clearOldImages'])->name('images.clear');
Route::get('/images/re-extract', [ImageController::class, 'reExtractFeatures'])->name('images.re-extract');
Route::get('/images/test-similarity', [ImageController::class, 'testSimilarity'])->name('images.test-similarity');
Route::get('/images/debug-categories', [ImageController::class, 'debugCategories'])->name('images.debug-categories');
Route::get('/debug', function() { return view('images.debug'); })->name('debug');
