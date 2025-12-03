<?php

namespace App\DTOs\Publishing;

use App\Contracts\Publishing\PublishableContract;

/**
 * Webhook payload
 *
 * This DTO ensures consistent payload structure across all webhook deliveries.
 */
readonly class WebhookPayload
{
    /**
     * @param  array<int, array<string, mixed>>  $articles
     */
    public function __construct(
        public string $eventType,
        public string $timestamp,
        public array $articles,
    ) {}

    /**
     * Create a publish_articles payload from publishable content.
     */
    public static function forPublish(PublishableContract $content): self
    {
        return new self(
            eventType: 'publish_articles',
            timestamp: now()->toIso8601String(),
            articles: [$content->toPublishableArray()],
        );
    }

    /**
     * Create a test payload for connection testing.
     */
    public static function forTest(): self
    {
        return new self(
            eventType: 'test',
            timestamp: now()->toIso8601String(),
            articles: [
                [
                    'id' => 0,
                    'title' => 'Test Connection',
                    'slug' => 'test-connection',
                    'content_html' => '<p>This is a test payload to verify your webhook endpoint.</p>',
                    'content_markdown' => 'This is a test payload to verify your webhook endpoint.',
                    'meta_description' => 'Test payload',
                    'created_at' => now()->toIso8601String(),
                ],
            ],
        );
    }

    /**
     * Convert to array for JSON encoding.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'event_type' => $this->eventType,
            'timestamp' => $this->timestamp,
            'data' => [
                'articles' => $this->articles,
            ],
        ];
    }

    /**
     * Convert to JSON string.
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    }
}
