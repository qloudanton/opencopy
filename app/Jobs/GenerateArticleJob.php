<?php

namespace App\Jobs;

use App\Models\AiProvider;
use App\Models\Keyword;
use App\Services\ArticleGenerationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class GenerateArticleJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public int $timeout = 300;

    public function __construct(
        public Keyword $keyword,
        public ?AiProvider $aiProvider = null,
    ) {}

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->keyword->id))->dontRelease(),
        ];
    }

    public function handle(ArticleGenerationService $service): void
    {
        if ($this->keyword->status === 'completed') {
            return;
        }

        $article = $service->generate($this->keyword, $this->aiProvider);

        // Queue featured image generation if enabled and project has image style configured
        $project = $this->keyword->project;
        if ($article && $project->generate_featured_image && $project->image_style) {
            // Get the project's effective image provider (Project → Account Default → Any Active)
            $imageProvider = $project->getEffectiveImageProvider();

            if ($imageProvider) {
                GenerateFeaturedImageJob::dispatch($article, $imageProvider);
            }
        }
    }

    public function failed(\Throwable $exception): void
    {
        $this->keyword->update([
            'status' => 'failed',
            'error_message' => $exception->getMessage(),
        ]);
    }
}
