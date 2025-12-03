<?php

use App\Http\Controllers\Settings\AiProviderController;
use App\Http\Controllers\Settings\GenerationSettingsController;
use App\Http\Controllers\Settings\IntegrationsController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\TwoFactorAuthenticationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('user-password.edit');

    Route::put('settings/password', [PasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::get('settings/appearance', function () {
        return Inertia::render('settings/appearance');
    })->name('appearance.edit');

    Route::get('settings/two-factor', [TwoFactorAuthenticationController::class, 'show'])
        ->name('two-factor.show');

    Route::get('settings/ai-providers', [AiProviderController::class, 'index'])->name('ai-providers.index');
    Route::post('settings/ai-providers', [AiProviderController::class, 'store'])->name('ai-providers.store');
    Route::put('settings/ai-providers/{aiProvider}', [AiProviderController::class, 'update'])->name('ai-providers.update');
    Route::delete('settings/ai-providers/{aiProvider}', [AiProviderController::class, 'destroy'])->name('ai-providers.destroy');
    Route::post('settings/ai-providers/{aiProvider}/default', [AiProviderController::class, 'setDefault'])->name('ai-providers.set-default');

    Route::get('settings/integrations', [IntegrationsController::class, 'edit'])->name('integrations.edit');
    Route::put('settings/integrations', [IntegrationsController::class, 'update'])->name('integrations.update');
    Route::post('settings/integrations/test-youtube', [IntegrationsController::class, 'testYouTube'])->name('integrations.test-youtube');

    Route::get('settings/generation', [GenerationSettingsController::class, 'edit'])->name('generation-settings.edit');
    Route::put('settings/generation', [GenerationSettingsController::class, 'update'])->name('generation-settings.update');
});
