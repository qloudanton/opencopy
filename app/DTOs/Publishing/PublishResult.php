<?php

namespace App\DTOs\Publishing;

use App\Enums\PublicationStatus;

/**
 * Immutable result object for publish operations.
 *
 * Contains the outcome of a publish attempt, including success/failure
 * status, any external identifiers, and error information.
 */
readonly class PublishResult
{
    /**
     * @param  array<string, mixed>|null  $payload  The payload that was sent
     * @param  array<string, mixed>|null  $response  The response received
     * @param  array<string, string>|null  $requestHeaders  Headers sent with the request
     * @param  array<string, mixed>|null  $responseHeaders  Headers received in response
     */
    public function __construct(
        public PublicationStatus $status,
        public ?string $externalId = null,
        public ?string $externalUrl = null,
        public ?string $errorMessage = null,
        public ?array $payload = null,
        public ?array $response = null,
        public ?int $httpStatusCode = null,
        public ?string $requestUrl = null,
        public ?string $requestMethod = null,
        public ?array $requestHeaders = null,
        public ?array $responseHeaders = null,
    ) {}

    /**
     * Create a successful publish result.
     *
     * @param  array<string, mixed>|null  $payload
     * @param  array<string, mixed>|null  $response
     * @param  array<string, string>|null  $requestHeaders
     * @param  array<string, mixed>|null  $responseHeaders
     */
    public static function success(
        ?string $externalId = null,
        ?string $externalUrl = null,
        ?array $payload = null,
        ?array $response = null,
        ?int $httpStatusCode = null,
        ?string $requestUrl = null,
        ?string $requestMethod = null,
        ?array $requestHeaders = null,
        ?array $responseHeaders = null,
    ): self {
        return new self(
            status: PublicationStatus::Published,
            externalId: $externalId,
            externalUrl: $externalUrl,
            payload: $payload,
            response: $response,
            httpStatusCode: $httpStatusCode,
            requestUrl: $requestUrl,
            requestMethod: $requestMethod,
            requestHeaders: $requestHeaders,
            responseHeaders: $responseHeaders,
        );
    }

    /**
     * Create a failed publish result.
     *
     * @param  array<string, mixed>|null  $payload
     * @param  array<string, mixed>|null  $response
     * @param  array<string, string>|null  $requestHeaders
     * @param  array<string, mixed>|null  $responseHeaders
     */
    public static function failure(
        string $errorMessage,
        ?array $payload = null,
        ?array $response = null,
        ?int $httpStatusCode = null,
        ?string $requestUrl = null,
        ?string $requestMethod = null,
        ?array $requestHeaders = null,
        ?array $responseHeaders = null,
    ): self {
        return new self(
            status: PublicationStatus::Failed,
            errorMessage: $errorMessage,
            payload: $payload,
            response: $response,
            httpStatusCode: $httpStatusCode,
            requestUrl: $requestUrl,
            requestMethod: $requestMethod,
            requestHeaders: $requestHeaders,
            responseHeaders: $responseHeaders,
        );
    }

    /**
     * Create a pending result (for async operations).
     */
    public static function pending(): self
    {
        return new self(status: PublicationStatus::Pending);
    }

    public function isSuccessful(): bool
    {
        return $this->status === PublicationStatus::Published;
    }

    public function isFailed(): bool
    {
        return $this->status === PublicationStatus::Failed;
    }

    /**
     * Convert to array for storage in Publication model.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'external_id' => $this->externalId,
            'external_url' => $this->externalUrl,
            'error_message' => $this->errorMessage,
            'payload_sent' => $this->payload,
            'response_received' => $this->response,
        ];
    }

    /**
     * Convert to detailed debug array for developer view.
     *
     * @return array<string, mixed>
     */
    public function toDebugArray(): array
    {
        return [
            'success' => $this->isSuccessful(),
            'message' => $this->isSuccessful()
                ? 'Connection successful!'
                : ($this->errorMessage ?? 'Connection failed'),
            'request' => [
                'method' => $this->requestMethod ?? 'POST',
                'url' => $this->requestUrl,
                'headers' => $this->sanitizeHeaders($this->requestHeaders ?? []),
                'body' => $this->payload,
            ],
            'response' => [
                'status_code' => $this->httpStatusCode,
                'status_text' => $this->getStatusText(),
                'headers' => $this->responseHeaders,
                'body' => $this->response,
            ],
        ];
    }

    /**
     * Get HTTP status text for the status code.
     */
    private function getStatusText(): ?string
    {
        if ($this->httpStatusCode === null) {
            return null;
        }

        $statusTexts = [
            200 => 'OK',
            201 => 'Created',
            202 => 'Accepted',
            204 => 'No Content',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            422 => 'Unprocessable Entity',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
        ];

        return $statusTexts[$this->httpStatusCode] ?? 'Unknown';
    }

    /**
     * Sanitize headers for display (mask sensitive values).
     *
     * @param  array<string, string>  $headers
     * @return array<string, string>
     */
    private function sanitizeHeaders(array $headers): array
    {
        $sensitiveKeys = ['authorization', 'x-api-key', 'api-key', 'x-auth-token'];

        return collect($headers)
            ->mapWithKeys(function ($value, $key) use ($sensitiveKeys) {
                $lowerKey = strtolower($key);

                if (in_array($lowerKey, $sensitiveKeys, true)) {
                    // Show type of auth but mask the actual value
                    if (str_starts_with(strtolower($value), 'bearer ')) {
                        return [$key => 'Bearer ••••••••'];
                    }

                    return [$key => '••••••••'];
                }

                return [$key => $value];
            })
            ->all();
    }
}
