<?php

use App\Http\Controllers\ArticleController;
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
    Route::post('projects/{project}/keywords/{keyword}/generate', [KeywordController::class, 'generate'])
        ->name('projects.keywords.generate');
    Route::get('projects/{project}/articles', [ArticleController::class, 'index'])
        ->name('projects.articles.index');
    Route::get('projects/{project}/articles/{article}', [ArticleController::class, 'show'])
        ->name('projects.articles.show');
    Route::get('projects/{project}/articles/{article}/edit', [ArticleController::class, 'edit'])
        ->name('projects.articles.edit');
    Route::put('projects/{project}/articles/{article}', [ArticleController::class, 'update'])
        ->name('projects.articles.update');
    Route::delete('projects/{project}/articles/{article}', [ArticleController::class, 'destroy'])
        ->name('projects.articles.destroy');
    Route::get('projects/{project}/settings', [ProjectController::class, 'settings'])
        ->name('projects.settings');
    Route::put('projects/{project}/settings', [ProjectController::class, 'updateSettings'])
        ->name('projects.settings.update');
});

require __DIR__.'/settings.php';
