<?php

namespace App\Services\Publishing;

use App\Contracts\Publishing\PublishableContract;
use App\DTOs\Publishing\PublishResult;
use App\Enums\PublicationStatus;
use App\Events\ArticlePublished;
use App\Events\ArticlePublishFailed;
use App\Jobs\PublishToIntegrationJob;
use App\Models\Article;
use App\Models\Integration;
use App\Models\Publication;
use Illuminate\Support\Collection;

/**
 * Orchestrates publishing content to integrations.
 *
 * This service is the main entry point for all publishing operations.
 * It coordinates between publishers, manages publication records,
 * and handles both sync and async publishing.
 */
class PublishingService
{
    public function __construct(
        private readonly PublisherFactory $factory,
    ) {}

    /**
     * Publish content to a single integration synchronously.
     */
    public function publish(PublishableContract $content, Integration $integration): Publication
    {
        $publication = $this->createOrUpdatePublication($content, $integration, PublicationStatus::Publishing);

        $publisher = $this->factory->make($integration);
        $result = $publisher->publish($content, $integration);

        return $this->recordResult($publication, $result, $integration);
    }

    /**
     * Publish content to multiple integrations synchronously.
     *
     * @param  Collection<int, Integration>|array<Integration>  $integrations
     * @return Collection<int, Publication>
     */
    public function publishToMany(PublishableContract $content, Collection|array $integrations): Collection
    {
        $integrations = collect($integrations);

        return $integrations->map(
            fn (Integration $integration) => $this->publish($content, $integration)
        );
    }

    /**
     * Queue content for async publishing to a single integration.
     */
    public function publishAsync(PublishableContract $content, Integration $integration): Publication
    {
        $publication = $this->createOrUpdatePublication($content, $integration, PublicationStatus::Pending);

        PublishToIntegrationJob::dispatch($content, $integration, $publication);

        return $publication;
    }

    /**
     * Queue content for async publishing to multiple integrations.
     *
     * @param  Collection<int, Integration>|array<Integration>  $integrations
     * @return Collection<int, Publication>
     */
    public function publishToManyAsync(PublishableContract $content, Collection|array $integrations): Collection
    {
        $integrations = collect($integrations);

        return $integrations->map(
            fn (Integration $integration) => $this->publishAsync($content, $integration)
        );
    }

    /**
     * Publish an article to all active integrations for its project.
     *
     * @return Collection<int, Publication>
     */
    public function publishToAllActive(Article $article, bool $async = true): Collection
    {
        $integrations = $article->project->integrations()
            ->where('is_active', true)
            ->get();

        return $async
            ? $this->publishToManyAsync($article, $integrations)
            : $this->publishToMany($article, $integrations);
    }

    /**
     * Test an integration connection.
     */
    public function test(Integration $integration): PublishResult
    {
        $publisher = $this->factory->make($integration);

        $result = $publisher->test($integration);

        if ($result->isSuccessful()) {
            $integration->update(['last_connected_at' => now()]);
        }

        return $result;
    }

    /**
     * Retry a failed publication.
     */
    public function retry(Publication $publication): Publication
    {
        $article = $publication->article;
        $integration = $publication->integration;

        if (! $article || ! $integration) {
            throw new \InvalidArgumentException('Publication missing article or integration');
        }

        $publication->update(['status' => PublicationStatus::Publishing->value]);

        $publisher = $this->factory->make($integration);
        $result = $publisher->publish($article, $integration);

        return $this->recordResult($publication, $result, $integration);
    }

    /**
     * Get publication history for an article.
     *
     * @return Collection<int, Publication>
     */
    public function getPublicationHistory(Article $article): Collection
    {
        return $article->publications()
            ->with('integration')
            ->latest()
            ->get();
    }

    /**
     * Create or update a publication record.
     */
    private function createOrUpdatePublication(
        PublishableContract $content,
        Integration $integration,
        PublicationStatus $status,
    ): Publication {
        return Publication::updateOrCreate(
            [
                'article_id' => $content->getPublishableId(),
                'integration_id' => $integration->id,
            ],
            [
                'status' => $status->value,
                'error_message' => null,
            ]
        );
    }

    /**
     * Record the result of a publish attempt.
     */
    private function recordResult(
        Publication $publication,
        PublishResult $result,
        Integration $integration,
    ): Publication {
        $publication->update([
            'status' => $result->status->value,
            'external_id' => $result->externalId,
            'external_url' => $result->externalUrl,
            'payload_sent' => $result->payload,
            'response_received' => $result->response,
            'error_message' => $result->errorMessage,
            'published_at' => $result->isSuccessful() ? now() : null,
        ]);

        // Update integration last_connected_at on success
        if ($result->isSuccessful()) {
            $integration->update(['last_connected_at' => now()]);

            // Fire success event
            if ($publication->article) {
                event(new ArticlePublished($publication->article, $integration, $publication));
            }
        } else {
            // Fire failure event
            if ($publication->article) {
                event(new ArticlePublishFailed(
                    $publication->article,
                    $integration,
                    $publication,
                    $result->errorMessage ?? 'Unknown error'
                ));
            }
        }

        return $publication->fresh();
    }
}
