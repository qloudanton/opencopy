<?php

namespace App\Contracts\Publishing;

use App\DTOs\Publishing\PublishResult;
use App\Enums\IntegrationType;
use App\Models\Integration;

/**
 * Contract for integration publishers.
 *
 * Each integration type (Webhook, WordPress, etc.) implements this contract
 * to handle the specifics of publishing content to that platform.
 */
interface PublisherContract
{
    /**
     * Get the integration type this publisher handles.
     */
    public function type(): IntegrationType;

    /**
     * Publish content to the integration.
     *
     * This method should handle all the logic for sending content
     * to the external service, including authentication, payload
     * formatting, and error handling.
     */
    public function publish(PublishableContract $content, Integration $integration): PublishResult;

    /**
     * Test the integration connection.
     *
     * Sends a test request to verify credentials are valid
     * and the endpoint is reachable.
     */
    public function test(Integration $integration): PublishResult;

    /**
     * Validate that the integration has all required credentials.
     *
     * @return array<string> List of validation errors, empty if valid
     */
    public function validateCredentials(Integration $integration): array;
}
