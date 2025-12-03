<?php

namespace App\Jobs;

use App\Enums\ContentStatus;
use App\Models\AiProvider;
use App\Models\ScheduledContent;
use App\Services\ArticleGenerationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class GenerateArticleJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    /**
     * Job timeout in seconds.
     * Set to 30 minutes to allow for:
     * - AI text generation (1-2 min)
     * - Multiple inline image generation (30-60 sec each)
     * - Video placeholder processing
     * - SEO score calculation
     */
    public int $timeout = 1800;

    public function __construct(
        public ScheduledContent $scheduledContent,
        public ?AiProvider $aiProvider = null,
    ) {}

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            // Prevent overlapping jobs for the same scheduled content
            (new WithoutOverlapping($this->scheduledContent->id))->dontRelease(),
        ];
    }

    public function handle(ArticleGenerationService $service): void
    {
        // Skip if this scheduled content is not in queued state
        if ($this->scheduledContent->status !== ContentStatus::Queued) {
            return;
        }

        // Skip if this scheduled content already has an article
        if ($this->scheduledContent->article_id !== null) {
            return;
        }

        // Ensure we have a keyword to generate from
        if (! $this->scheduledContent->keyword_id) {
            $this->scheduledContent->failGeneration('No keyword associated with this scheduled content.');

            return;
        }

        $article = $service->generate($this->scheduledContent, $this->aiProvider);

        $project = $this->scheduledContent->project;
        $featuredImageDispatched = false;

        // Queue featured image generation if enabled and project has image style configured
        if ($article && $project->generate_featured_image && $project->image_style) {
            // Get the project's effective image provider (Project → Account Default → Any Active)
            $imageProvider = $project->getEffectiveImageProvider();

            if ($imageProvider) {
                GenerateFeaturedImageJob::dispatch($article, $imageProvider);
                $featuredImageDispatched = true;
            }
        }

        // Handle auto-publish settings
        if ($article) {
            $this->handleAutoPublish($article, $project, $featuredImageDispatched);
        }
    }

    protected function handleAutoPublish($article, $project, bool $featuredImageDispatched): void
    {
        // If skip_review is enabled, auto-approve the article
        if ($project->skip_review) {
            $this->scheduledContent->refresh();
            if ($this->scheduledContent->status === \App\Enums\ContentStatus::InReview) {
                $this->scheduledContent->approve();
            }
        }

        // Handle auto-publish based on project setting
        switch ($project->auto_publish) {
            case 'immediate':
                // Publish immediately (with delay if featured image is being generated)
                $delay = $featuredImageDispatched ? now()->addMinutes(3) : null;
                PublishArticleJob::dispatch($article)->delay($delay);
                break;

            case 'scheduled':
                // Content will be picked up by the scheduler command
                // Just ensure it's approved if skip_review is enabled
                break;

            case 'manual':
            default:
                // Do nothing - user will manually publish
                break;
        }
    }

    public function failed(\Throwable $exception): void
    {
        // Mark the scheduled content as failed
        $this->scheduledContent->failGeneration($exception->getMessage());
    }
}
