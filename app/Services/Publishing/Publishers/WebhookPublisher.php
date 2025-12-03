<?php

namespace App\Services\Publishing\Publishers;

use App\Contracts\Publishing\PublishableContract;
use App\DTOs\Publishing\PublishResult;
use App\DTOs\Publishing\WebhookPayload;
use App\Enums\IntegrationType;
use App\Models\Integration;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * Publisher for webhook integrations.
 *
 * Sends content as JSON POST requests with Bearer token authentication.
 */
class WebhookPublisher extends AbstractPublisher
{
    private const DEFAULT_TIMEOUT = 30;

    private const DEFAULT_RETRY_TIMES = 3;

    private const DEFAULT_RETRY_DELAY = 100;

    public function type(): IntegrationType
    {
        return IntegrationType::Webhook;
    }

    /**
     * @return array<string>
     */
    public function validateCredentials(Integration $integration): array
    {
        $errors = [];

        $endpointUrl = $this->credential($integration, 'endpoint_url');
        $accessToken = $this->credential($integration, 'access_token');

        if (empty($endpointUrl)) {
            $errors[] = 'Webhook URL is required';
        } elseif (! filter_var($endpointUrl, FILTER_VALIDATE_URL)) {
            $errors[] = 'Webhook URL must be a valid URL';
        } elseif (! str_starts_with($endpointUrl, 'https://')) {
            $errors[] = 'Webhook URL must use HTTPS';
        }

        if (empty($accessToken)) {
            $errors[] = 'Access token is required';
        }

        return $errors;
    }

    protected function doPublish(PublishableContract $content, Integration $integration): PublishResult
    {
        $payload = WebhookPayload::forPublish($content);

        return $this->sendWebhook($integration, $payload);
    }

    protected function doTest(Integration $integration): PublishResult
    {
        $payload = WebhookPayload::forTest();

        return $this->sendWebhook($integration, $payload);
    }

    /**
     * Send the webhook request with retry logic.
     */
    private function sendWebhook(Integration $integration, WebhookPayload $payload): PublishResult
    {
        $endpointUrl = $this->credential($integration, 'endpoint_url');
        $accessToken = $this->credential($integration, 'access_token');
        $timeout = $this->setting($integration, 'timeout', self::DEFAULT_TIMEOUT);
        $retryTimes = $this->setting($integration, 'retry_times', self::DEFAULT_RETRY_TIMES);
        $retryDelay = $this->setting($integration, 'retry_delay', self::DEFAULT_RETRY_DELAY);

        $payloadArray = $payload->toArray();
        $requestHeaders = $this->buildHeaders($integration, $accessToken);

        try {
            $response = Http::timeout($timeout)
                ->retry($retryTimes, $retryDelay, function (\Exception $e) {
                    // Only retry on connection errors or 5xx responses
                    return $e instanceof ConnectionException
                        || ($e instanceof RequestException && $e->response->serverError());
                })
                ->withHeaders($requestHeaders)
                ->post($endpointUrl, $payloadArray);

            $responseHeaders = $this->extractResponseHeaders($response);

            if ($response->successful()) {
                return PublishResult::success(
                    externalId: $response->json('id') ?? $response->json('data.id'),
                    externalUrl: $response->json('url') ?? $response->json('data.url'),
                    payload: $payloadArray,
                    response: $response->json() ?? ['body' => $response->body()],
                    httpStatusCode: $response->status(),
                    requestUrl: $endpointUrl,
                    requestMethod: 'POST',
                    requestHeaders: $requestHeaders,
                    responseHeaders: $responseHeaders,
                );
            }

            return PublishResult::failure(
                errorMessage: $this->parseErrorMessage($response),
                payload: $payloadArray,
                response: $response->json() ?? ['body' => $response->body()],
                httpStatusCode: $response->status(),
                requestUrl: $endpointUrl,
                requestMethod: 'POST',
                requestHeaders: $requestHeaders,
                responseHeaders: $responseHeaders,
            );
        } catch (ConnectionException $e) {
            return PublishResult::failure(
                errorMessage: 'Connection failed: '.$e->getMessage(),
                payload: $payloadArray,
                requestUrl: $endpointUrl,
                requestMethod: 'POST',
                requestHeaders: $requestHeaders,
            );
        } catch (RequestException $e) {
            return PublishResult::failure(
                errorMessage: 'Request failed: '.$e->getMessage(),
                payload: $payloadArray,
                response: $e->response?->json() ?? ['body' => $e->response?->body()],
                httpStatusCode: $e->response?->status(),
                requestUrl: $endpointUrl,
                requestMethod: 'POST',
                requestHeaders: $requestHeaders,
                responseHeaders: $e->response ? $this->extractResponseHeaders($e->response) : null,
            );
        }
    }

    /**
     * Extract response headers as a simple associative array.
     *
     * @return array<string, string>
     */
    private function extractResponseHeaders(\Illuminate\Http\Client\Response $response): array
    {
        $headers = [];

        foreach ($response->headers() as $name => $values) {
            $headers[$name] = implode(', ', $values);
        }

        return $headers;
    }

    /**
     * Build the request headers.
     *
     * @return array<string, string>
     */
    private function buildHeaders(Integration $integration, string $accessToken): array
    {
        $headers = [
            'Authorization' => "Bearer {$accessToken}",
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'User-Agent' => 'OpenCopy/1.0',
        ];

        // Allow custom headers from settings
        $customHeaders = $this->setting($integration, 'headers', []);

        if (is_array($customHeaders)) {
            $headers = array_merge($headers, $customHeaders);
        }

        return $headers;
    }

    /**
     * Parse error message from response.
     */
    private function parseErrorMessage(\Illuminate\Http\Client\Response $response): string
    {
        $json = $response->json();

        if (isset($json['message'])) {
            return $json['message'];
        }

        if (isset($json['error'])) {
            return is_string($json['error']) ? $json['error'] : json_encode($json['error']);
        }

        if (isset($json['errors'])) {
            return is_array($json['errors'])
                ? implode(', ', array_map(fn ($e) => is_string($e) ? $e : json_encode($e), $json['errors']))
                : $json['errors'];
        }

        return "HTTP {$response->status()}: {$response->reason()}";
    }
}
