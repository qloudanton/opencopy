<?php

use App\Http\Controllers\ArticleController;
use App\Http\Controllers\ContentPlannerController;
use App\Http\Controllers\IntegrationController;
use App\Http\Controllers\KeywordController;
use App\Http\Controllers\KeywordsController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectPageController;
use App\Models\Project;
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
    Route::resource('projects', ProjectController::class)->except(['edit', 'update']);
    Route::get('projects/{project}/edit', fn (Project $project) => redirect()->route('projects.settings', $project));
    Route::post('projects/analyze-website', [ProjectController::class, 'analyzeWebsite'])
        ->name('projects.analyze-website');
    Route::post('projects/generate-audiences', [ProjectController::class, 'generateAudiences'])
        ->name('projects.generate-audiences');
    Route::post('projects/generate-competitors', [ProjectController::class, 'generateCompetitors'])
        ->name('projects.generate-competitors');
    Route::post('projects/generate-keywords', [ProjectController::class, 'generateKeywords'])
        ->name('projects.generate-keywords');
    Route::post('projects/{project}/keywords/analyze', [KeywordController::class, 'analyze'])
        ->name('projects.keywords.analyze');
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
    Route::post('projects/{project}/articles/{article}/improve', [ArticleController::class, 'improve'])
        ->name('projects.articles.improve');
    Route::post('projects/{project}/articles/{article}/recalculate-seo', [ArticleController::class, 'recalculateSeo'])
        ->name('projects.articles.recalculate-seo');
    Route::post('projects/{project}/articles/{article}/generate-featured-image', [ArticleController::class, 'generateFeaturedImage'])
        ->name('projects.articles.generate-featured-image');
    Route::get('projects/{project}/articles/{article}/featured-image-status', [ArticleController::class, 'featuredImageStatus'])
        ->name('projects.articles.featured-image-status');
    Route::delete('projects/{project}/articles/{article}/featured-image', [ArticleController::class, 'deleteFeaturedImage'])
        ->name('projects.articles.delete-featured-image');
    Route::post('projects/{project}/articles/{article}/publish', [ArticleController::class, 'publish'])
        ->name('projects.articles.publish');
    Route::post('projects/{project}/articles/{article}/retry-publication', [ArticleController::class, 'retryPublication'])
        ->name('projects.articles.retry-publication');
    Route::get('projects/{project}/articles/{article}/publication-status', [ArticleController::class, 'publicationStatus'])
        ->name('projects.articles.publication-status');
    Route::post('projects/{project}/articles/{article}/regenerate-inline-image', [ArticleController::class, 'regenerateInlineImage'])
        ->name('projects.articles.regenerate-inline-image');
    Route::post('projects/{project}/articles/{article}/enrich', [ArticleController::class, 'enrich'])
        ->name('projects.articles.enrich');
    Route::get('projects/{project}/articles/{article}/enrichment-status', [ArticleController::class, 'enrichmentStatus'])
        ->name('projects.articles.enrichment-status');
    Route::post('projects/{project}/youtube-search', [ArticleController::class, 'searchYouTube'])
        ->name('projects.youtube-search');
    // Project Settings (sectioned)
    Route::get('projects/{project}/settings', [ProjectController::class, 'settingsGeneral'])
        ->name('projects.settings');
    Route::put('projects/{project}/settings/general', [ProjectController::class, 'updateSettingsProjectDetails'])
        ->name('projects.settings.general.update');
    Route::get('projects/{project}/settings/content', [ProjectController::class, 'settingsContent'])
        ->name('projects.settings.content');
    Route::put('projects/{project}/settings/content', [ProjectController::class, 'updateSettingsContent'])
        ->name('projects.settings.content.update');
    Route::get('projects/{project}/settings/localization', [ProjectController::class, 'settingsLocalization'])
        ->name('projects.settings.localization');
    Route::put('projects/{project}/settings/localization', [ProjectController::class, 'updateSettingsLocalization'])
        ->name('projects.settings.localization.update');
    Route::get('projects/{project}/settings/internal-linking', [ProjectController::class, 'settingsInternalLinking'])
        ->name('projects.settings.internal-linking');
    Route::put('projects/{project}/settings/internal-linking', [ProjectController::class, 'updateSettingsInternalLinking'])
        ->name('projects.settings.internal-linking.update');
    Route::get('projects/{project}/settings/media', [ProjectController::class, 'settingsMedia'])
        ->name('projects.settings.media');
    Route::put('projects/{project}/settings/media', [ProjectController::class, 'updateSettingsMedia'])
        ->name('projects.settings.media.update');
    Route::get('projects/{project}/settings/call-to-action', [ProjectController::class, 'settingsCallToAction'])
        ->name('projects.settings.call-to-action');
    Route::put('projects/{project}/settings/call-to-action', [ProjectController::class, 'updateSettingsCallToAction'])
        ->name('projects.settings.call-to-action.update');
    Route::get('projects/{project}/settings/publishing', [ProjectController::class, 'settingsPublishing'])
        ->name('projects.settings.publishing');
    Route::put('projects/{project}/settings/publishing', [ProjectController::class, 'updateSettingsPublishing'])
        ->name('projects.settings.publishing.update');
    Route::get('projects/{project}/settings/danger-zone', [ProjectController::class, 'settingsDangerZone'])
        ->name('projects.settings.danger-zone');

    // Integrations
    Route::get('projects/{project}/integrations', [IntegrationController::class, 'index'])
        ->name('projects.integrations.index');
    Route::post('projects/{project}/integrations', [IntegrationController::class, 'store'])
        ->name('projects.integrations.store');
    Route::put('projects/{project}/integrations/{integration}', [IntegrationController::class, 'update'])
        ->name('projects.integrations.update');
    Route::delete('projects/{project}/integrations/{integration}', [IntegrationController::class, 'destroy'])
        ->name('projects.integrations.destroy');
    Route::post('projects/{project}/integrations/{integration}/test', [IntegrationController::class, 'test'])
        ->name('projects.integrations.test');
    Route::post('projects/{project}/integrations/{integration}/toggle', [IntegrationController::class, 'toggleActive'])
        ->name('projects.integrations.toggle');

    // Project Pages (Sitemap/Internal Linking)
    Route::get('projects/{project}/pages', [ProjectPageController::class, 'index'])
        ->name('projects.pages.index');
    Route::post('projects/{project}/pages', [ProjectPageController::class, 'store'])
        ->name('projects.pages.store');
    Route::post('projects/{project}/pages/sync', [ProjectPageController::class, 'sync'])
        ->name('projects.pages.sync');
    Route::get('projects/{project}/pages/stats', [ProjectPageController::class, 'stats'])
        ->name('projects.pages.stats');
    Route::put('projects/{project}/pages/{page}', [ProjectPageController::class, 'update'])
        ->name('projects.pages.update');
    Route::delete('projects/{project}/pages/{page}', [ProjectPageController::class, 'destroy'])
        ->name('projects.pages.destroy');

    // Content Planner
    Route::get('projects/{project}/planner', [ContentPlannerController::class, 'index'])
        ->name('projects.planner.index');
    Route::post('projects/{project}/planner', [ContentPlannerController::class, 'store'])
        ->name('projects.planner.store');
    Route::put('projects/{project}/planner/{content}', [ContentPlannerController::class, 'update'])
        ->name('projects.planner.update');
    Route::delete('projects/{project}/planner/{content}', [ContentPlannerController::class, 'destroy'])
        ->name('projects.planner.destroy');
    Route::post('projects/{project}/planner/{content}/schedule', [ContentPlannerController::class, 'schedule'])
        ->name('projects.planner.schedule');
    Route::post('projects/{project}/planner/{content}/unschedule', [ContentPlannerController::class, 'unschedule'])
        ->name('projects.planner.unschedule');
    Route::post('projects/{project}/planner/{content}/status', [ContentPlannerController::class, 'updateStatus'])
        ->name('projects.planner.update-status');
    Route::post('projects/{project}/planner/bulk-add', [ContentPlannerController::class, 'bulkAdd'])
        ->name('projects.planner.bulk-add');
    Route::post('projects/{project}/planner/auto-create', [ContentPlannerController::class, 'autoCreate'])
        ->name('projects.planner.auto-create');
    Route::post('projects/{project}/planner/create-keyword', [ContentPlannerController::class, 'createKeyword'])
        ->name('projects.planner.create-keyword');
    Route::post('projects/{project}/planner/auto-schedule', [ContentPlannerController::class, 'autoSchedule'])
        ->name('projects.planner.auto-schedule');
    Route::post('projects/{project}/planner/{content}/generate', [ContentPlannerController::class, 'generate'])
        ->name('projects.planner.generate');
    Route::post('projects/{project}/planner/{content}/publish', [ContentPlannerController::class, 'publish'])
        ->name('projects.planner.publish');
});

require __DIR__.'/settings.php';
