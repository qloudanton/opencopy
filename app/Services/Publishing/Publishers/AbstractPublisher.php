<?php

namespace App\Services\Publishing\Publishers;

use App\Contracts\Publishing\PublishableContract;
use App\Contracts\Publishing\PublisherContract;
use App\DTOs\Publishing\PublishResult;
use App\Enums\IntegrationType;
use App\Models\Integration;
use Illuminate\Support\Facades\Log;

/**
 * Base class for all publishers with common functionality.
 */
abstract class AbstractPublisher implements PublisherContract
{
    /**
     * Get the integration type this publisher handles.
     */
    abstract public function type(): IntegrationType;

    /**
     * Perform the actual publish operation.
     *
     * Subclasses implement this to handle platform-specific publishing logic.
     */
    abstract protected function doPublish(PublishableContract $content, Integration $integration): PublishResult;

    /**
     * Perform the actual test operation.
     *
     * Subclasses implement this to handle platform-specific testing logic.
     */
    abstract protected function doTest(Integration $integration): PublishResult;

    /**
     * Publish content with logging and error handling.
     */
    public function publish(PublishableContract $content, Integration $integration): PublishResult
    {
        $this->logAttempt('publish', $integration, [
            'content_id' => $content->getPublishableId(),
            'content_title' => $content->getPublishableTitle(),
        ]);

        try {
            $validationErrors = $this->validateCredentials($integration);

            if (! empty($validationErrors)) {
                return PublishResult::failure(
                    'Invalid credentials: '.implode(', ', $validationErrors)
                );
            }

            $result = $this->doPublish($content, $integration);

            $this->logResult('publish', $integration, $result);

            return $result;
        } catch (\Throwable $e) {
            $this->logError('publish', $integration, $e);

            return PublishResult::failure(
                errorMessage: $e->getMessage(),
            );
        }
    }

    /**
     * Test connection with logging and error handling.
     */
    public function test(Integration $integration): PublishResult
    {
        $this->logAttempt('test', $integration);

        try {
            $validationErrors = $this->validateCredentials($integration);

            if (! empty($validationErrors)) {
                return PublishResult::failure(
                    'Invalid credentials: '.implode(', ', $validationErrors)
                );
            }

            $result = $this->doTest($integration);

            $this->logResult('test', $integration, $result);

            return $result;
        } catch (\Throwable $e) {
            $this->logError('test', $integration, $e);

            return PublishResult::failure(
                errorMessage: $e->getMessage(),
            );
        }
    }

    /**
     * Get a credential value from the integration.
     */
    protected function credential(Integration $integration, string $key, mixed $default = null): mixed
    {
        return $integration->credentials[$key] ?? $default;
    }

    /**
     * Get a setting value from the integration.
     */
    protected function setting(Integration $integration, string $key, mixed $default = null): mixed
    {
        return $integration->settings[$key] ?? $default;
    }

    /**
     * Log a publish/test attempt.
     *
     * @param  array<string, mixed>  $context
     */
    protected function logAttempt(string $operation, Integration $integration, array $context = []): void
    {
        Log::info("Publishing: Starting {$operation}", [
            'integration_id' => $integration->id,
            'integration_name' => $integration->name,
            'integration_type' => $integration->type,
            ...$context,
        ]);
    }

    /**
     * Log the result of a publish/test operation.
     */
    protected function logResult(string $operation, Integration $integration, PublishResult $result): void
    {
        $level = $result->isSuccessful() ? 'info' : 'warning';

        Log::{$level}("Publishing: {$operation} completed", [
            'integration_id' => $integration->id,
            'integration_name' => $integration->name,
            'status' => $result->status->value,
            'external_id' => $result->externalId,
            'error' => $result->errorMessage,
        ]);
    }

    /**
     * Log an error during publish/test.
     */
    protected function logError(string $operation, Integration $integration, \Throwable $e): void
    {
        Log::error("Publishing: {$operation} failed with exception", [
            'integration_id' => $integration->id,
            'integration_name' => $integration->name,
            'exception' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
    }
}
