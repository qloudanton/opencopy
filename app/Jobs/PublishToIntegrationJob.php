<?php

namespace App\Jobs;

use App\Contracts\Publishing\PublishableContract;
use App\DTOs\Publishing\PublishResult;
use App\Enums\PublicationStatus;
use App\Events\ArticlePublished;
use App\Events\ArticlePublishFailed;
use App\Models\Integration;
use App\Models\Publication;
use App\Services\Publishing\PublisherFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job for asynchronously publishing content to an integration.
 *
 * This job handles the actual publishing work, including retries,
 * error handling, and event dispatching.
 */
class PublishToIntegrationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     */
    public int $maxExceptions = 3;

    public function __construct(
        public PublishableContract $content,
        public Integration $integration,
        public Publication $publication,
    ) {}

    /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string
    {
        return "publish-{$this->publication->id}";
    }

    /**
     * Execute the job.
     */
    public function handle(PublisherFactory $factory): void
    {
        Log::info('PublishToIntegrationJob: Starting', [
            'publication_id' => $this->publication->id,
            'integration_id' => $this->integration->id,
            'content_id' => $this->content->getPublishableId(),
            'attempt' => $this->attempts(),
        ]);

        // Update status to publishing
        $this->publication->update(['status' => PublicationStatus::Publishing->value]);

        $publisher = $factory->make($this->integration);
        $result = $publisher->publish($this->content, $this->integration);

        $this->recordResult($result);
    }

    /**
     * Handle a job failure.
     */
    public function failed(?\Throwable $exception): void
    {
        Log::error('PublishToIntegrationJob: Failed permanently', [
            'publication_id' => $this->publication->id,
            'integration_id' => $this->integration->id,
            'exception' => $exception?->getMessage(),
        ]);

        $this->publication->update([
            'status' => PublicationStatus::Failed->value,
            'error_message' => $exception?->getMessage() ?? 'Job failed after maximum retries',
        ]);

        event(new ArticlePublishFailed(
            $this->publication->article,
            $this->integration,
            $this->publication,
            $exception?->getMessage() ?? 'Job failed after maximum retries'
        ));
    }

    /**
     * Record the publish result and dispatch appropriate events.
     */
    private function recordResult(PublishResult $result): void
    {
        $this->publication->update([
            'status' => $result->status->value,
            'external_id' => $result->externalId,
            'external_url' => $result->externalUrl,
            'payload_sent' => $result->payload,
            'response_received' => $result->response,
            'error_message' => $result->errorMessage,
            'published_at' => $result->isSuccessful() ? now() : null,
        ]);

        if ($result->isSuccessful()) {
            $this->integration->update(['last_connected_at' => now()]);

            event(new ArticlePublished(
                $this->publication->article,
                $this->integration,
                $this->publication
            ));

            Log::info('PublishToIntegrationJob: Published successfully', [
                'publication_id' => $this->publication->id,
                'external_id' => $result->externalId,
            ]);
        } else {
            // If we have retries left, let the job retry
            if ($this->attempts() < $this->tries) {
                Log::warning('PublishToIntegrationJob: Failed, will retry', [
                    'publication_id' => $this->publication->id,
                    'error' => $result->errorMessage,
                    'attempt' => $this->attempts(),
                ]);

                $this->release($this->backoff * $this->attempts());

                return;
            }

            event(new ArticlePublishFailed(
                $this->publication->article,
                $this->integration,
                $this->publication,
                $result->errorMessage ?? 'Unknown error'
            ));
        }
    }
}
