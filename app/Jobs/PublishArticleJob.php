<?php

namespace App\Jobs;

use App\Enums\ContentStatus;
use App\Models\Article;
use App\Services\Publishing\PublishingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class PublishArticleJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public int $timeout = 120;

    /**
     * @param  array<int>|null  $integrationIds  Specific integration IDs to publish to, or null for all active
     */
    public function __construct(
        public Article $article,
        public ?array $integrationIds = null,
    ) {}

    public function handle(PublishingService $publishingService): void
    {
        $project = $this->article->project;

        Log::info('[PublishArticle] Starting auto-publish', [
            'article_id' => $this->article->id,
            'project_id' => $project->id,
            'integration_ids' => $this->integrationIds,
        ]);

        // Get integrations to publish to
        $integrations = $project->integrations()
            ->where('is_active', true)
            ->when($this->integrationIds, function ($query, $ids) {
                return $query->whereIn('id', $ids);
            })
            ->get();

        if ($integrations->isEmpty()) {
            Log::info('[PublishArticle] No active integrations found, skipping', [
                'article_id' => $this->article->id,
            ]);

            return;
        }

        // Publish to all integrations (sync, since we're already in a job)
        $publications = $publishingService->publishToMany($this->article, $integrations);

        $successful = $publications->filter(fn ($p) => $p->status === 'published')->count();
        $failed = $publications->count() - $successful;

        Log::info('[PublishArticle] Publish completed', [
            'article_id' => $this->article->id,
            'total_integrations' => $integrations->count(),
            'successful' => $successful,
            'failed' => $failed,
        ]);

        // Update ScheduledContent status to Published if all succeeded
        if ($failed === 0 && $successful > 0) {
            $scheduledContent = $this->article->scheduledContent;
            if ($scheduledContent && $scheduledContent->status !== ContentStatus::Published) {
                $scheduledContent->publish();
                Log::info('[PublishArticle] ScheduledContent marked as published', [
                    'scheduled_content_id' => $scheduledContent->id,
                ]);
            }
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('[PublishArticle] Job failed', [
            'article_id' => $this->article->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
