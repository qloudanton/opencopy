<?php

namespace App\Jobs;

use App\Models\AiProvider;
use App\Models\Article;
use App\Services\FeaturedImageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GenerateFeaturedImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 300;

    public function __construct(
        public Article $article,
        public AiProvider $aiProvider,
        public ?string $style = null
    ) {}

    /**
     * Get the cache key for tracking job status.
     */
    public static function statusCacheKey(int $articleId): string
    {
        return "featured_image_generation:{$articleId}";
    }

    /**
     * Execute the job.
     */
    public function handle(FeaturedImageService $imageService): void
    {
        $cacheKey = self::statusCacheKey($this->article->id);

        // Get the scheduled content for this article (if any)
        $scheduledContent = $this->article->scheduledContent;

        try {
            Cache::put($cacheKey, [
                'status' => 'processing',
                'started_at' => now()->toIso8601String(),
            ], now()->addMinutes(10));

            // Set enriching status while generating featured image
            $scheduledContent?->startEnriching();

            $result = $imageService->generate($this->article, $this->aiProvider, $this->style);

            Cache::put($cacheKey, [
                'status' => 'completed',
                'image_id' => $result['image']->id,
                'image_url' => $result['url'],
                'completed_at' => now()->toIso8601String(),
            ], now()->addMinutes(10));

            // Restore previous status after featured image is generated
            $scheduledContent?->completeEnriching();

        } catch (\Exception $e) {
            Log::error('Featured image generation failed', [
                'article_id' => $this->article->id,
                'error' => $e->getMessage(),
            ]);

            Cache::put($cacheKey, [
                'status' => 'failed',
                'error' => $e->getMessage(),
                'failed_at' => now()->toIso8601String(),
            ], now()->addMinutes(10));

            // Restore previous status even on failure (don't leave stuck in enriching)
            $scheduledContent?->completeEnriching();
        }
    }
}
