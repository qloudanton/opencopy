<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class GenerationSettingsController extends Controller
{
    public function edit(): Response
    {
        $user = Auth::user();
        $settings = $user->getOrCreateSettings();

        // Get providers that support text generation
        $textProviders = $user->aiProviders()
            ->where('is_active', true)
            ->where('supports_text', true)
            ->get(['id', 'name', 'provider', 'model']);

        // Get providers that support image generation
        $imageProviders = $user->aiProviders()
            ->where('is_active', true)
            ->where('supports_image', true)
            ->get(['id', 'name', 'provider', 'model']);

        return Inertia::render('settings/generation-settings', [
            'settings' => [
                'default_text_provider_id' => $settings->default_text_provider_id,
                'default_image_provider_id' => $settings->default_image_provider_id,
            ],
            'textProviders' => $textProviders,
            'imageProviders' => $imageProviders,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'default_text_provider_id' => ['nullable', 'exists:ai_providers,id'],
            'default_image_provider_id' => ['nullable', 'exists:ai_providers,id'],
        ]);

        $user = Auth::user();
        $settings = $user->getOrCreateSettings();

        $settings->update([
            'default_text_provider_id' => $validated['default_text_provider_id'],
            'default_image_provider_id' => $validated['default_image_provider_id'],
        ]);

        return back()->with('success', 'Generation settings updated successfully.');
    }
}
