<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\YouTubeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class IntegrationsController extends Controller
{
    public function edit(): Response
    {
        $user = Auth::user();
        $settings = $user->getOrCreateSettings();

        return Inertia::render('settings/integrations', [
            'settings' => [
                'has_youtube_api_key' => $settings->hasYouTubeApiKey(),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'youtube_api_key' => ['nullable', 'string', 'max:255'],
        ]);

        $user = Auth::user();
        $settings = $user->getOrCreateSettings();

        $updateData = [];

        // Only update API key if provided, or clear it if explicitly set to empty
        if (array_key_exists('youtube_api_key', $validated)) {
            if ($validated['youtube_api_key'] !== null && $validated['youtube_api_key'] !== '') {
                $updateData['youtube_api_key'] = $validated['youtube_api_key'];
            } elseif ($validated['youtube_api_key'] === '') {
                $updateData['youtube_api_key'] = null;
            }
        }

        if (! empty($updateData)) {
            $settings->update($updateData);
        }

        return back()->with('success', 'Integration settings updated successfully.');
    }

    public function testYouTube(Request $request, YouTubeService $youTubeService): JsonResponse
    {
        $user = Auth::user();
        $settings = $user->settings;

        if (! $settings?->hasYouTubeApiKey()) {
            return response()->json([
                'success' => false,
                'error' => 'YouTube API key is not configured.',
            ]);
        }

        $result = $youTubeService->search('Laravel tutorial', 1);

        if ($result === null) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to connect to YouTube API. Please check your API key.',
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'YouTube API connection successful!',
        ]);
    }
}
