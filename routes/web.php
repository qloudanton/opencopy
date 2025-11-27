<?php

use App\Http\Controllers\KeywordController;
use App\Http\Controllers\KeywordsController;
use App\Http\Controllers\ProjectController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');

    Route::get('keywords', [KeywordsController::class, 'index'])->name('keywords.index');
    Route::resource('projects', ProjectController::class);
    Route::resource('projects.keywords', KeywordController::class);
});

require __DIR__.'/settings.php';
