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

        $service->generate($this->keyword, $this->aiProvider);
    }

    public function failed(\Throwable $exception): void
    {
        $this->keyword->update([
            'status' => 'failed',
            'error_message' => $exception->getMessage(),
        ]);
    }
}
