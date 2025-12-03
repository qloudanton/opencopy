<?php

use App\DTOs\Publishing\PublishResult;
use App\DTOs\Publishing\WebhookPayload;
use App\Enums\IntegrationType;
use App\Enums\PublicationStatus;
use App\Events\ArticlePublished;
use App\Events\ArticlePublishFailed;
use App\Models\Article;
use App\Models\Integration;
use App\Models\Publication;
use App\Services\Publishing\PublisherFactory;
use App\Services\Publishing\Publishers\WebhookPublisher;
use App\Services\Publishing\PublishingService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Event::fake([ArticlePublished::class, ArticlePublishFailed::class]);
});

describe('PublishResult DTO', function () {
    it('creates a successful result', function () {
        $result = PublishResult::success(
            externalId: '123',
            externalUrl: 'https://example.com/post/123',
            payload: ['test' => 'data'],
            response: ['id' => '123'],
            httpStatusCode: 200,
        );

        expect($result->isSuccessful())->toBeTrue()
            ->and($result->isFailed())->toBeFalse()
            ->and($result->status)->toBe(PublicationStatus::Published)
            ->and($result->externalId)->toBe('123')
            ->and($result->externalUrl)->toBe('https://example.com/post/123');
    });

    it('creates a failed result', function () {
        $result = PublishResult::failure(
            errorMessage: 'Connection refused',
            httpStatusCode: 500,
        );

        expect($result->isFailed())->toBeTrue()
            ->and($result->isSuccessful())->toBeFalse()
            ->and($result->status)->toBe(PublicationStatus::Failed)
            ->and($result->errorMessage)->toBe('Connection refused');
    });

    it('creates a pending result', function () {
        $result = PublishResult::pending();

        expect($result->status)->toBe(PublicationStatus::Pending)
            ->and($result->isSuccessful())->toBeFalse()
            ->and($result->isFailed())->toBeFalse();
    });

    it('converts to array for storage', function () {
        $result = PublishResult::success(externalId: '123');

        $array = $result->toArray();

        expect($array)->toHaveKeys(['status', 'external_id', 'external_url', 'error_message', 'payload_sent', 'response_received'])
            ->and($array['status'])->toBe('published')
            ->and($array['external_id'])->toBe('123');
    });
});

describe('WebhookPayload DTO', function () {
    it('creates a publish payload from an article', function () {
        $article = Article::factory()->create([
            'title' => 'Test Article',
            'slug' => 'test-article',
        ]);

        $payload = WebhookPayload::forPublish($article);

        expect($payload->eventType)->toBe('publish_articles')
            ->and($payload->articles)->toBeArray()
            ->and($payload->articles[0])->toHaveKeys(['id', 'title', 'slug', 'content_html', 'content_markdown'])
            ->and($payload->articles[0]['title'])->toBe('Test Article')
            ->and($payload->timestamp)->not->toBeEmpty();
    });

    it('creates a test payload', function () {
        $payload = WebhookPayload::forTest();

        expect($payload->eventType)->toBe('test')
            ->and($payload->articles[0]['title'])->toBe('Test Connection');
    });

    it('converts to proper JSON structure', function () {
        $payload = WebhookPayload::forTest();
        $array = $payload->toArray();

        expect($array)->toHaveKeys(['event_type', 'timestamp', 'data'])
            ->and($array['data'])->toHaveKey('articles')
            ->and($array['data']['articles'])->toBeArray()
            ->and($array['event_type'])->toBe('test');
    });
});

describe('WebhookPublisher', function () {
    it('validates required credentials', function () {
        $publisher = new WebhookPublisher;

        $integration = Integration::factory()->create([
            'type' => 'webhook',
            'credentials' => [],
        ]);

        $errors = $publisher->validateCredentials($integration);

        expect($errors)->toContain('Webhook URL is required')
            ->and($errors)->toContain('Access token is required');
    });

    it('validates HTTPS requirement', function () {
        $publisher = new WebhookPublisher;

        $integration = Integration::factory()->create([
            'type' => 'webhook',
            'credentials' => [
                'endpoint_url' => 'http://example.com/webhook',
                'access_token' => 'test-token',
            ],
        ]);

        $errors = $publisher->validateCredentials($integration);

        expect($errors)->toContain('Webhook URL must use HTTPS');
    });

    it('passes validation with valid credentials', function () {
        $publisher = new WebhookPublisher;

        $integration = Integration::factory()->webhook()->create();

        $errors = $publisher->validateCredentials($integration);

        expect($errors)->toBeEmpty();
    });

    it('publishes successfully to a webhook endpoint', function () {
        Http::fake([
            '*' => Http::response(['id' => 'ext-123', 'url' => 'https://blog.example.com/test-article'], 200),
        ]);

        $publisher = new WebhookPublisher;
        $article = Article::factory()->create();
        $integration = Integration::factory()->webhook()->create();

        $result = $publisher->publish($article, $integration);

        expect($result->isSuccessful())->toBeTrue()
            ->and($result->externalId)->toBe('ext-123')
            ->and($result->externalUrl)->toBe('https://blog.example.com/test-article')
            ->and($result->httpStatusCode)->toBe(200);

        Http::assertSent(function ($request) use ($integration) {
            return $request->hasHeader('Authorization', 'Bearer '.$integration->credentials['access_token'])
                && $request->hasHeader('Content-Type', 'application/json');
        });
    });

    it('handles webhook failures gracefully', function () {
        Http::fake([
            '*' => Http::response(['error' => 'Unauthorized'], 401),
        ]);

        $publisher = new WebhookPublisher;
        $article = Article::factory()->create();
        $integration = Integration::factory()->webhook()->create();

        $result = $publisher->publish($article, $integration);

        expect($result->isFailed())->toBeTrue()
            ->and($result->httpStatusCode)->toBe(401)
            ->and($result->errorMessage)->toContain('Unauthorized');
    });

    it('tests webhook connection successfully', function () {
        Http::fake([
            '*' => Http::response(['status' => 'ok'], 200),
        ]);

        $publisher = new WebhookPublisher;
        $integration = Integration::factory()->webhook()->create();

        $result = $publisher->test($integration);

        expect($result->isSuccessful())->toBeTrue();

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);

            return $body['event_type'] === 'test';
        });
    });
});

describe('PublisherFactory', function () {
    it('creates a webhook publisher', function () {
        $factory = app(PublisherFactory::class);

        $publisher = $factory->makeForType('webhook');

        expect($publisher)->toBeInstanceOf(WebhookPublisher::class)
            ->and($publisher->type())->toBe(IntegrationType::Webhook);
    });

    it('throws exception for unsupported type', function () {
        $factory = app(PublisherFactory::class);

        $factory->makeForType('unsupported');
    })->throws(InvalidArgumentException::class, 'No publisher registered for integration type: unsupported');

    it('checks if type is supported', function () {
        $factory = app(PublisherFactory::class);

        expect($factory->supports('webhook'))->toBeTrue()
            ->and($factory->supports('wordpress'))->toBeFalse();
    });

    it('creates publisher from integration model', function () {
        $factory = app(PublisherFactory::class);
        $integration = Integration::factory()->webhook()->create();

        $publisher = $factory->make($integration);

        expect($publisher)->toBeInstanceOf(WebhookPublisher::class);
    });
});

describe('PublishingService', function () {
    it('publishes to a single integration', function () {
        Http::fake([
            '*' => Http::response(['id' => 'ext-123'], 200),
        ]);

        $service = app(PublishingService::class);
        $article = Article::factory()->create();
        $integration = Integration::factory()->webhook()->create([
            'project_id' => $article->project_id,
        ]);

        $publication = $service->publish($article, $integration);

        expect($publication)->toBeInstanceOf(Publication::class)
            ->and($publication->status)->toBe(PublicationStatus::Published->value)
            ->and($publication->article_id)->toBe($article->id)
            ->and($publication->integration_id)->toBe($integration->id)
            ->and($publication->external_id)->toBe('ext-123');

        Event::assertDispatched(ArticlePublished::class, function ($event) use ($article, $integration) {
            return $event->article->id === $article->id
                && $event->integration->id === $integration->id;
        });
    });

    it('publishes to multiple integrations', function () {
        Http::fake([
            '*' => Http::response(['id' => 'ext-123'], 200),
        ]);

        $service = app(PublishingService::class);
        $article = Article::factory()->create();

        $integrations = Integration::factory()->webhook()->count(3)->create([
            'project_id' => $article->project_id,
        ]);

        $publications = $service->publishToMany($article, $integrations);

        expect($publications)->toHaveCount(3)
            ->and($publications->every(fn ($p) => $p->isPublished()))->toBeTrue();

        Event::assertDispatchedTimes(ArticlePublished::class, 3);
    });

    it('handles publish failures', function () {
        Http::fake([
            '*' => Http::response(['error' => 'Server error'], 500),
        ]);

        $service = app(PublishingService::class);
        $article = Article::factory()->create();
        $integration = Integration::factory()->webhook()->create([
            'project_id' => $article->project_id,
        ]);

        $publication = $service->publish($article, $integration);

        expect($publication->isFailed())->toBeTrue()
            ->and($publication->error_message)->not->toBeEmpty();

        Event::assertDispatched(ArticlePublishFailed::class, function ($event) {
            return $event->errorMessage !== null;
        });
    });

    it('tests integration connection', function () {
        Http::fake([
            '*' => Http::response(['status' => 'ok'], 200),
        ]);

        $service = app(PublishingService::class);
        $integration = Integration::factory()->webhook()->create();

        $result = $service->test($integration);

        expect($result->isSuccessful())->toBeTrue();

        $integration->refresh();
        expect($integration->last_connected_at)->not->toBeNull();
    });

    it('retries failed publications', function () {
        Http::fake([
            '*' => Http::response(['id' => 'retry-success'], 200),
        ]);

        $service = app(PublishingService::class);
        $article = Article::factory()->create();
        $integration = Integration::factory()->webhook()->create([
            'project_id' => $article->project_id,
        ]);

        $publication = Publication::factory()->create([
            'article_id' => $article->id,
            'integration_id' => $integration->id,
            'status' => 'failed',
            'error_message' => 'Previous failure',
        ]);

        $retried = $service->retry($publication);

        expect($retried->isPublished())->toBeTrue()
            ->and($retried->external_id)->toBe('retry-success')
            ->and($retried->error_message)->toBeNull();
    });

    it('updates or creates publication record', function () {
        // Use sequence to return different responses on consecutive calls
        Http::fake([
            '*' => Http::sequence()
                ->push(['id' => 'ext-123'], 200)
                ->push(['id' => 'ext-456'], 200),
        ]);

        $service = app(PublishingService::class);
        $article = Article::factory()->create();
        $integration = Integration::factory()->webhook()->create([
            'project_id' => $article->project_id,
        ]);

        // First publish
        $service->publish($article, $integration);
        expect(Publication::count())->toBe(1);

        $firstPublication = Publication::first();
        expect($firstPublication->external_id)->toBe('ext-123');

        // Re-publish should update, not create new
        $service->publish($article, $integration);
        expect(Publication::count())->toBe(1);

        $publication = Publication::first();
        expect($publication->external_id)->toBe('ext-456');
    });

    it('gets publication history for article', function () {
        $service = app(PublishingService::class);
        $article = Article::factory()->create();

        $integrations = Integration::factory()->webhook()->count(2)->create([
            'project_id' => $article->project_id,
        ]);

        foreach ($integrations as $integration) {
            Publication::factory()->create([
                'article_id' => $article->id,
                'integration_id' => $integration->id,
            ]);
        }

        $history = $service->getPublicationHistory($article);

        expect($history)->toHaveCount(2)
            ->and($history->first()->integration)->not->toBeNull();
    });
});

describe('Article PublishableContract', function () {
    it('implements all required methods', function () {
        $article = Article::factory()->create([
            'title' => 'My Test Article',
            'slug' => 'my-test-article',
            'content' => '<p>HTML content</p>',
            'content_markdown' => '**Markdown content**',
            'meta_description' => 'SEO description',
            'excerpt' => 'Short excerpt',
        ]);

        expect($article->getPublishableId())->toBe($article->id)
            ->and($article->getPublishableTitle())->toBe('My Test Article')
            ->and($article->getPublishableSlug())->toBe('my-test-article')
            ->and($article->getPublishableHtml())->toBe('<p>HTML content</p>')
            ->and($article->getPublishableMarkdown())->toBe('**Markdown content**')
            ->and($article->getPublishableMetaDescription())->toBe('SEO description')
            ->and($article->getPublishableExcerpt())->toBe('Short excerpt')
            ->and($article->getPublishableCreatedAt())->toBeInstanceOf(DateTimeInterface::class);
    });

    it('converts to publishable array', function () {
        $article = Article::factory()->create();

        $array = $article->toPublishableArray();

        expect($array)->toHaveKeys([
            'id',
            'title',
            'slug',
            'content_html',
            'content_markdown',
            'meta_description',
            'excerpt',
            'image_url',
            'tags',
            'created_at',
            'word_count',
            'reading_time_minutes',
        ]);
    });

    it('gets tags from keyword', function () {
        $article = Article::factory()->create();
        $article->keyword->update([
            'keyword' => 'main keyword',
            'secondary_keywords' => ['secondary one', 'secondary two'],
        ]);

        $tags = $article->getPublishableTags();

        expect($tags)->toContain('main keyword')
            ->and($tags)->toContain('secondary one')
            ->and($tags)->toContain('secondary two');
    });
});

describe('Integration Model', function () {
    it('identifies integration type correctly', function () {
        $integration = Integration::factory()->webhook()->create();

        expect($integration->isWebhook())->toBeTrue()
            ->and($integration->isWordPress())->toBeFalse()
            ->and($integration->integrationType())->toBe(IntegrationType::Webhook);
    });

    it('checks if publisher is available', function () {
        $webhook = Integration::factory()->webhook()->create();
        $wordpress = Integration::factory()->wordpress()->create();

        expect($webhook->hasPublisher())->toBeTrue()
            ->and($wordpress->hasPublisher())->toBeFalse();
    });

    it('gets and sets credentials', function () {
        $integration = Integration::factory()->webhook()->create();

        expect($integration->getCredential('endpoint_url'))->not->toBeEmpty()
            ->and($integration->getCredential('nonexistent', 'default'))->toBe('default');

        $integration->setCredential('new_key', 'new_value');
        expect($integration->credentials['new_key'])->toBe('new_value');
    });

    it('scopes to active integrations', function () {
        Integration::factory()->webhook()->create(['is_active' => true]);
        Integration::factory()->webhook()->create(['is_active' => false]);

        expect(Integration::active()->count())->toBe(1);
    });

    it('scopes to specific type', function () {
        Integration::factory()->webhook()->create();
        Integration::factory()->wordpress()->create();

        expect(Integration::ofType('webhook')->count())->toBe(1)
            ->and(Integration::webhooks()->count())->toBe(1);
    });
});

describe('Publication Model', function () {
    it('identifies status correctly', function () {
        $published = Publication::factory()->create(['status' => 'published']);
        $failed = Publication::factory()->create(['status' => 'failed']);
        $pending = Publication::factory()->create(['status' => 'pending']);

        expect($published->isPublished())->toBeTrue()
            ->and($failed->isFailed())->toBeTrue()
            ->and($pending->isPending())->toBeTrue();
    });

    it('determines if publication can be retried', function () {
        $failed = Publication::factory()->create(['status' => 'failed']);
        $published = Publication::factory()->create(['status' => 'published']);

        expect($failed->canRetry())->toBeTrue()
            ->and($published->canRetry())->toBeFalse();
    });

    it('scopes to different statuses', function () {
        Publication::factory()->create(['status' => 'published']);
        Publication::factory()->create(['status' => 'failed']);
        Publication::factory()->create(['status' => 'pending']);

        expect(Publication::successful()->count())->toBe(1)
            ->and(Publication::failed()->count())->toBe(1)
            ->and(Publication::pending()->count())->toBe(1);
    });
});
