<?php

namespace App\Jobs;

use App\Models\Article;
use App\Services\ArticleImageService;
use App\Services\YouTubeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Cache;

class EnrichArticleJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $backoff = 30;

    /**
     * Job timeout in seconds.
     * Set to 15 minutes to allow for multiple image generations.
     */
    public int $timeout = 900;

    public function __construct(
        public Article $article,
    ) {}

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('enrich-article-'.$this->article->id))->dontRelease(),
        ];
    }

    public static function statusCacheKey(int $articleId): string
    {
        return "article-enrichment-status:{$articleId}";
    }

    public function handle(ArticleImageService $imageService, YouTubeService $youTubeService): void
    {
        $cacheKey = self::statusCacheKey($this->article->id);

        Cache::put($cacheKey, [
            'status' => 'processing',
            'started_at' => now()->toIso8601String(),
            'results' => null,
        ], now()->addMinutes(30));

        $results = [
            'images' => ['processed' => 0, 'errors' => []],
            'videos' => ['processed' => 0, 'errors' => []],
        ];

        $project = $this->article->project;

        // Set enriching status if we have scheduled content
        $scheduledContent = $this->article->scheduledContent;
        $scheduledContent?->startEnriching();

        try {
            // Process inline image placeholders
            $imageProvider = $project->getEffectiveImageProvider();
            if ($imageProvider) {
                try {
                    $imageResult = $imageService->processArticleImages($this->article, $imageProvider);
                    $results['images']['processed'] = $imageResult['processed'] ?? 0;
                    $results['images']['errors'] = $imageResult['errors'] ?? [];
                } catch (\Exception $e) {
                    $results['images']['errors'][] = $e->getMessage();
                }
            } else {
                $results['images']['errors'][] = 'No image provider configured';
            }

            // Process video placeholders
            $user = $project->user;
            if ($user->settings?->hasYouTubeApiKey()) {
                try {
                    $content = $this->article->content_markdown ?? $this->article->content;
                    $processedContent = $youTubeService
                        ->forUser($user)
                        ->processVideoPlaceholders($content);

                    if ($processedContent !== $content) {
                        $this->article->update([
                            'content' => $processedContent,
                            'content_markdown' => $processedContent,
                        ]);
                        $results['videos']['processed'] = 1;
                    }
                } catch (\Exception $e) {
                    $results['videos']['errors'][] = $e->getMessage();
                }
            }

            // Restore status
            $scheduledContent?->completeEnriching();

            // Update cache with success status
            Cache::put($cacheKey, [
                'status' => 'completed',
                'completed_at' => now()->toIso8601String(),
                'results' => $results,
            ], now()->addMinutes(10));
        } catch (\Exception $e) {
            // Restore status even on error
            $scheduledContent?->completeEnriching();

            Cache::put($cacheKey, [
                'status' => 'failed',
                'failed_at' => now()->toIso8601String(),
                'error' => $e->getMessage(),
                'results' => $results,
            ], now()->addMinutes(10));

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        $cacheKey = self::statusCacheKey($this->article->id);

        Cache::put($cacheKey, [
            'status' => 'failed',
            'failed_at' => now()->toIso8601String(),
            'error' => $exception->getMessage(),
        ], now()->addMinutes(10));

        // Restore scheduled content status
        $this->article->scheduledContent?->completeEnriching();
    }
}
