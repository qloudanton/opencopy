<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class YouTubeService
{
    private const API_BASE_URL = 'https://www.googleapis.com/youtube/v3';

    private ?User $user = null;

    /**
     * Set the user context for API key retrieval.
     */
    public function forUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Search for YouTube videos.
     *
     * @param  string  $query  The search query
     * @param  int  $maxResults  Maximum number of results to return
     * @return array|null Returns the first video result or null if none found
     */
    public function search(string $query, int $maxResults = 1): ?array
    {
        $apiKey = $this->getApiKey();

        if (! $apiKey) {
            Log::warning('YouTube API key not configured');

            return null;
        }

        try {
            $response = Http::get(self::API_BASE_URL.'/search', [
                'part' => 'snippet',
                'q' => $query,
                'type' => 'video',
                'maxResults' => $maxResults,
                'videoEmbeddable' => 'true',
                'relevanceLanguage' => 'en',
                'safeSearch' => 'moderate',
                'key' => $apiKey,
            ]);

            if ($response->successful()) {
                $items = $response->json('items', []);

                return $items[0] ?? null;
            }

            Log::error('YouTube API request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('YouTube API exception', [
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Search for multiple YouTube videos.
     *
     * @param  string  $query  The search query
     * @param  int  $maxResults  Maximum number of results to return
     * @return array Returns array of video results
     */
    public function searchMultiple(string $query, int $maxResults = 5): array
    {
        $apiKey = $this->getApiKey();

        if (! $apiKey) {
            return [];
        }

        try {
            $response = Http::get(self::API_BASE_URL.'/search', [
                'part' => 'snippet',
                'q' => $query,
                'type' => 'video',
                'maxResults' => $maxResults,
                'videoEmbeddable' => 'true',
                'relevanceLanguage' => 'en',
                'safeSearch' => 'moderate',
                'key' => $apiKey,
            ]);

            if ($response->successful()) {
                return $response->json('items', []);
            }

            return [];
        } catch (\Exception $e) {
            Log::error('YouTube API exception', [
                'message' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Get the embed URL for a video.
     */
    public function getEmbedUrl(string $videoId): string
    {
        return "https://www.youtube.com/embed/{$videoId}";
    }

    /**
     * Get responsive HTML embed code for a video.
     */
    public function getEmbedHtml(string $videoId, ?string $title = null): string
    {
        $embedUrl = $this->getEmbedUrl($videoId);
        $safeTitle = htmlspecialchars($title ?? 'YouTube video', ENT_QUOTES, 'UTF-8');

        return <<<HTML

<div style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; max-width: 100%;">
<iframe src="{$embedUrl}" title="{$safeTitle}" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: 0;" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>
</div>

HTML;
    }

    /**
     * Get the watch URL for a video.
     */
    public function getWatchUrl(string $videoId): string
    {
        return "https://www.youtube.com/watch?v={$videoId}";
    }

    /**
     * Extract video ID from a search result.
     */
    public function getVideoId(array $searchResult): ?string
    {
        return $searchResult['id']['videoId'] ?? null;
    }

    /**
     * Extract video title from a search result.
     */
    public function getVideoTitle(array $searchResult): ?string
    {
        return $searchResult['snippet']['title'] ?? null;
    }

    /**
     * Process article content and replace VIDEO placeholders with actual YouTube embeds.
     *
     * Supports placeholders like:
     * [VIDEO: Search for "query" on YouTube and embed...]
     * [VIDEO: query here]
     */
    public function processVideoPlaceholders(string $content): string
    {
        // Pattern to match [VIDEO: ...] placeholders
        $pattern = '/\[VIDEO:\s*(.+?)\]/i';

        return preg_replace_callback($pattern, function ($matches) {
            $placeholder = $matches[1];

            // Try to extract query from "Search for "query" on YouTube..." format
            if (preg_match('/Search for ["\']([^"\']+)["\']/i', $placeholder, $searchMatch)) {
                $query = trim($searchMatch[1]);
            } else {
                // Use the entire placeholder content as the query
                $query = trim($placeholder);
            }

            if (empty($query)) {
                return $matches[0]; // Return original if no query found
            }

            Log::info('Processing YouTube video placeholder', ['query' => $query]);

            $video = $this->search($query);

            if ($video) {
                $videoId = $this->getVideoId($video);
                $title = $this->getVideoTitle($video);

                if ($videoId) {
                    Log::info('Found YouTube video', ['videoId' => $videoId, 'title' => $title]);

                    return $this->getEmbedHtml($videoId, $title);
                }
            }

            Log::warning('No YouTube video found for query', ['query' => $query]);

            // Return original placeholder if search failed
            return $matches[0];
        }, $content);
    }

    /**
     * Check if the API key is configured and valid.
     */
    public function isConfigured(): bool
    {
        return ! empty($this->getApiKey());
    }

    /**
     * Get the YouTube API key for the current user.
     */
    private function getApiKey(): ?string
    {
        $user = $this->user ?? Auth::user();

        if (! $user) {
            return null;
        }

        $settings = $user->settings;

        if (! $settings) {
            return null;
        }

        return $settings->youtube_api_key;
    }
}
