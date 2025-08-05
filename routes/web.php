<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ImageController;

Route::get('/', function () {
    return redirect()->route('images.upload.form');
});

Route::get('/images/upload', [ImageController::class, 'showUploadForm'])->name('images.upload.form');
Route::post('/images/upload', [ImageController::class, 'upload'])->name('images.upload');
Route::get('/images/search', [ImageController::class, 'showSearchForm'])->name('images.search.form');
Route::post('/images/search', [ImageController::class, 'search'])->name('images.search');
