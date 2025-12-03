<?php

namespace App\Services\Publishing;

use App\Contracts\Publishing\PublisherContract;
use App\Models\Integration;
use App\Services\Publishing\Publishers\WebhookPublisher;
use InvalidArgumentException;

/**
 * Factory for creating publisher instances.
 *
 * Resolves the appropriate publisher implementation based on
 * the integration type. New publishers are registered here.
 */
class PublisherFactory
{
    /**
     * @var array<string, class-string<PublisherContract>>
     */
    private array $publishers = [
        'webhook' => WebhookPublisher::class,
        // 'wordpress' => WordPressPublisher::class,
        // 'webflow' => WebflowPublisher::class,
    ];

    /**
     * Create a publisher for the given integration.
     */
    public function make(Integration $integration): PublisherContract
    {
        return $this->makeForType($integration->type);
    }

    /**
     * Create a publisher for the given type.
     */
    public function makeForType(string $type): PublisherContract
    {
        if (! isset($this->publishers[$type])) {
            throw new InvalidArgumentException(
                "No publisher registered for integration type: {$type}"
            );
        }

        return app($this->publishers[$type]);
    }

    /**
     * Check if a publisher exists for the given type.
     */
    public function supports(string $type): bool
    {
        return isset($this->publishers[$type]);
    }

    /**
     * Register a publisher for an integration type.
     *
     * @param  class-string<PublisherContract>  $publisherClass
     */
    public function register(string $type, string $publisherClass): void
    {
        $this->publishers[$type] = $publisherClass;
    }

    /**
     * Get all registered publisher types.
     *
     * @return array<string>
     */
    public function registeredTypes(): array
    {
        return array_keys($this->publishers);
    }
}
