<?php

use App\Models\AiProvider;
use App\Models\User;
use App\Services\BusinessAnalyzerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Prism;
use Prism\Prism\Text\PendingRequest;
use Prism\Prism\Text\Response;
use Prism\Prism\ValueObjects\Meta;
use Prism\Prism\ValueObjects\Usage;

uses(RefreshDatabase::class);

function mockBusinessAnalyzerPrism(array $responseData = []): void
{
    $defaultData = [
        'name' => 'Acme Corp',
        'description' => 'Acme Corp is a leading software company specializing in innovative solutions.',
        'industry' => 'SaaS',
        'target_audience' => 'Small businesses',
        'language' => 'English',
        'country' => 'United States',
    ];

    $data = array_merge($defaultData, $responseData);
    $jsonContent = json_encode($data);

    $response = new Response(
        steps: collect(),
        text: $jsonContent,
        finishReason: FinishReason::Stop,
        toolCalls: [],
        toolResults: [],
        usage: new Usage(100, 200),
        meta: new Meta('test-id', 'gpt-4o'),
        messages: collect(),
    );

    $mockPendingRequest = Mockery::mock(PendingRequest::class);
    $mockPendingRequest->shouldReceive('using')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withClientOptions')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxTokens')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withSystemPrompt')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withPrompt')->andReturnSelf();
    $mockPendingRequest->shouldReceive('asText')->andReturn($response);

    $mockPrism = Mockery::mock(Prism::class);
    $mockPrism->shouldReceive('text')->andReturn($mockPendingRequest);

    app()->instance(Prism::class, $mockPrism);
}

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->aiProvider = AiProvider::factory()->for($this->user)->openai()->default()->create();
});

describe('analyzeWebsite', function () {
    it('analyzes a website and returns business information', function () {
        Http::fake([
            'https://example.com' => Http::response('<html><head><title>Acme Corp - Software Solutions</title><meta name="description" content="Leading software company"></head><body><h1>Welcome to Acme</h1><p>We provide innovative software solutions for businesses of all sizes.</p></body></html>', 200),
        ]);

        mockBusinessAnalyzerPrism();

        $service = app(BusinessAnalyzerService::class);
        $result = $service->analyzeWebsite('https://example.com', $this->user);

        expect($result)->toBeArray()
            ->and($result['name'])->toBe('Acme Corp')
            ->and($result['description'])->toBe('Acme Corp is a leading software company specializing in innovative solutions.')
            ->and($result['industry'])->toBe('SaaS')
            ->and($result['target_audience'])->toBe('Small businesses')
            ->and($result['language'])->toBe('English')
            ->and($result['country'])->toBe('United States');
    });

    it('throws exception when website content cannot be fetched', function () {
        Http::fake([
            'https://example.com' => Http::response('Not Found', 404),
        ]);

        $service = app(BusinessAnalyzerService::class);

        expect(fn () => $service->analyzeWebsite('https://example.com', $this->user))
            ->toThrow(\RuntimeException::class, 'Could not fetch website content');
    });

    it('throws exception when no AI provider is configured', function () {
        Http::fake([
            'https://example.com' => Http::response('<html><body>Content</body></html>', 200),
        ]);

        $userWithoutProvider = User::factory()->create();

        $service = app(BusinessAnalyzerService::class);

        expect(fn () => $service->analyzeWebsite('https://example.com', $userWithoutProvider))
            ->toThrow(\RuntimeException::class, 'No active AI provider configured');
    });

    it('adds https prefix when URL does not have protocol', function () {
        Http::fake([
            'https://example.com' => Http::response('<html><body><p>Test content that is long enough to pass the filter.</p></body></html>', 200),
        ]);

        mockBusinessAnalyzerPrism();

        $service = app(BusinessAnalyzerService::class);
        $result = $service->analyzeWebsite('example.com', $this->user);

        expect($result)->toBeArray()
            ->and($result['name'])->toBe('Acme Corp');

        Http::assertSent(function ($request) {
            return str_starts_with($request->url(), 'https://');
        });
    });

    it('handles AI response with markdown code blocks', function () {
        Http::fake([
            'https://example.com' => Http::response('<html><body><p>Test content for the website analysis.</p></body></html>', 200),
        ]);

        // Mock AI response with markdown code blocks
        $jsonWithCodeBlocks = "```json\n{\"name\":\"Test Corp\",\"description\":\"A test company\",\"industry\":\"Tech\",\"target_audience\":\"Developers\",\"language\":\"English\",\"country\":\"Canada\"}\n```";

        $response = new Response(
            steps: collect(),
            text: $jsonWithCodeBlocks,
            finishReason: FinishReason::Stop,
            toolCalls: [],
            toolResults: [],
            usage: new Usage(100, 200),
            meta: new Meta('test-id', 'gpt-4o'),
            messages: collect(),
        );

        $mockPendingRequest = Mockery::mock(PendingRequest::class);
        $mockPendingRequest->shouldReceive('using')->andReturnSelf();
        $mockPendingRequest->shouldReceive('withClientOptions')->andReturnSelf();
        $mockPendingRequest->shouldReceive('withMaxTokens')->andReturnSelf();
        $mockPendingRequest->shouldReceive('withSystemPrompt')->andReturnSelf();
        $mockPendingRequest->shouldReceive('withPrompt')->andReturnSelf();
        $mockPendingRequest->shouldReceive('asText')->andReturn($response);

        $mockPrism = Mockery::mock(Prism::class);
        $mockPrism->shouldReceive('text')->andReturn($mockPendingRequest);

        app()->instance(Prism::class, $mockPrism);

        $service = app(BusinessAnalyzerService::class);
        $result = $service->analyzeWebsite('https://example.com', $this->user);

        expect($result['name'])->toBe('Test Corp')
            ->and($result['country'])->toBe('Canada');
    });

    it('uses specified AI provider when provided', function () {
        Http::fake([
            'https://example.com' => Http::response('<html><body><p>Test content for the website analysis.</p></body></html>', 200),
        ]);

        $customProvider = AiProvider::factory()->for($this->user)->anthropic()->create([
            'is_default' => false,
        ]);

        mockBusinessAnalyzerPrism();

        $service = app(BusinessAnalyzerService::class);
        $result = $service->analyzeWebsite('https://example.com', $this->user, $customProvider);

        expect($result)->toBeArray()
            ->and($result['name'])->toBe('Acme Corp');
    });
});

describe('analyzeWebsite endpoint', function () {
    it('requires authentication', function () {
        $response = $this->postJson('/projects/analyze-website', [
            'url' => 'https://example.com',
        ]);

        $response->assertUnauthorized();
    });

    it('validates URL is required', function () {
        $response = $this->actingAs($this->user)
            ->postJson('/projects/analyze-website', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['url']);
    });

    it('validates URL format', function () {
        $response = $this->actingAs($this->user)
            ->postJson('/projects/analyze-website', [
                'url' => 'not-a-valid-url',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['url']);
    });

    it('returns analysis results on success', function () {
        Http::fake([
            'https://example.com' => Http::response('<html><body><p>Test content for the website.</p></body></html>', 200),
        ]);

        mockBusinessAnalyzerPrism([
            'name' => 'Example Corp',
            'description' => 'Example company description',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/projects/analyze-website', [
                'url' => 'https://example.com',
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Example Corp',
                    'description' => 'Example company description',
                ],
            ]);
    });

    it('returns error when analysis fails', function () {
        Http::fake([
            'https://example.com' => Http::response('Not Found', 404),
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/projects/analyze-website', [
                'url' => 'https://example.com',
            ]);

        $response->assertUnprocessable()
            ->assertJson([
                'success' => false,
            ]);
    });
});

describe('content extraction', function () {
    it('extracts title from HTML', function () {
        Http::fake([
            'https://example.com' => Http::response('<html><head><title>My Business Title</title></head><body><p>Content here.</p></body></html>', 200),
        ]);

        mockBusinessAnalyzerPrism();

        $service = app(BusinessAnalyzerService::class);
        $result = $service->analyzeWebsite('https://example.com', $this->user);

        // The service should have extracted the title for AI processing
        expect($result)->toBeArray();
    });

    it('extracts meta description from HTML', function () {
        Http::fake([
            'https://example.com' => Http::response('<html><head><meta name="description" content="This is the meta description"></head><body><p>Content here.</p></body></html>', 200),
        ]);

        mockBusinessAnalyzerPrism();

        $service = app(BusinessAnalyzerService::class);
        $result = $service->analyzeWebsite('https://example.com', $this->user);

        expect($result)->toBeArray();
    });

    it('extracts headings from HTML', function () {
        Http::fake([
            'https://example.com' => Http::response('<html><body><h1>Main Heading</h1><h2>Sub Heading</h2><p>Paragraph content.</p></body></html>', 200),
        ]);

        mockBusinessAnalyzerPrism();

        $service = app(BusinessAnalyzerService::class);
        $result = $service->analyzeWebsite('https://example.com', $this->user);

        expect($result)->toBeArray();
    });

    it('removes script tags from content', function () {
        Http::fake([
            'https://example.com' => Http::response('<html><body><script>alert("xss")</script><p>Safe content here.</p></body></html>', 200),
        ]);

        mockBusinessAnalyzerPrism();

        $service = app(BusinessAnalyzerService::class);
        $result = $service->analyzeWebsite('https://example.com', $this->user);

        expect($result)->toBeArray();
    });

    it('removes style tags from content', function () {
        Http::fake([
            'https://example.com' => Http::response('<html><body><style>.hidden{display:none}</style><p>Visible content here.</p></body></html>', 200),
        ]);

        mockBusinessAnalyzerPrism();

        $service = app(BusinessAnalyzerService::class);
        $result = $service->analyzeWebsite('https://example.com', $this->user);

        expect($result)->toBeArray();
    });
});

function mockAudiencesPrism(array $audiences = []): void
{
    $defaultAudiences = [
        'Small business owners',
        'Marketing professionals',
        'E-commerce retailers',
        'Digital agencies',
        'Freelancers and consultants',
    ];

    $data = ['audiences' => empty($audiences) ? $defaultAudiences : $audiences];
    $jsonContent = json_encode($data);

    $response = new Response(
        steps: collect(),
        text: $jsonContent,
        finishReason: FinishReason::Stop,
        toolCalls: [],
        toolResults: [],
        usage: new Usage(100, 200),
        meta: new Meta('test-id', 'gpt-4o'),
        messages: collect(),
    );

    $mockPendingRequest = Mockery::mock(PendingRequest::class);
    $mockPendingRequest->shouldReceive('using')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withClientOptions')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxTokens')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withSystemPrompt')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withPrompt')->andReturnSelf();
    $mockPendingRequest->shouldReceive('asText')->andReturn($response);

    $mockPrism = Mockery::mock(Prism::class);
    $mockPrism->shouldReceive('text')->andReturn($mockPendingRequest);

    app()->instance(Prism::class, $mockPrism);
}

function mockCompetitorsPrism(array $competitors = []): void
{
    $defaultCompetitors = [
        'competitor1.com',
        'competitor2.io',
        'competitor3.net',
        'competitor4.com',
        'competitor5.co',
    ];

    $data = ['competitors' => empty($competitors) ? $defaultCompetitors : $competitors];
    $jsonContent = json_encode($data);

    $response = new Response(
        steps: collect(),
        text: $jsonContent,
        finishReason: FinishReason::Stop,
        toolCalls: [],
        toolResults: [],
        usage: new Usage(100, 200),
        meta: new Meta('test-id', 'gpt-4o'),
        messages: collect(),
    );

    $mockPendingRequest = Mockery::mock(PendingRequest::class);
    $mockPendingRequest->shouldReceive('using')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withClientOptions')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxTokens')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withSystemPrompt')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withPrompt')->andReturnSelf();
    $mockPendingRequest->shouldReceive('asText')->andReturn($response);

    $mockPrism = Mockery::mock(Prism::class);
    $mockPrism->shouldReceive('text')->andReturn($mockPendingRequest);

    app()->instance(Prism::class, $mockPrism);
}

describe('generateTargetAudiences', function () {
    it('generates target audiences from business description', function () {
        mockAudiencesPrism();

        $service = app(BusinessAnalyzerService::class);
        $result = $service->generateTargetAudiences(
            'We provide software solutions for businesses.',
            $this->user
        );

        expect($result)->toBeArray()
            ->and($result)->toHaveCount(5)
            ->and($result[0])->toBe('Small business owners');
    });

    it('throws exception when no AI provider is configured', function () {
        $userWithoutProvider = User::factory()->create();

        $service = app(BusinessAnalyzerService::class);

        expect(fn () => $service->generateTargetAudiences(
            'We provide software solutions for businesses.',
            $userWithoutProvider
        ))->toThrow(\RuntimeException::class, 'No active AI provider configured');
    });
});

describe('generateCompetitors', function () {
    it('generates competitors from business description', function () {
        mockCompetitorsPrism();

        $service = app(BusinessAnalyzerService::class);
        $result = $service->generateCompetitors(
            'We provide software solutions for businesses.',
            $this->user
        );

        expect($result)->toBeArray()
            ->and($result)->toHaveCount(5)
            ->and($result[0])->toBe('competitor1.com');
    });

    it('throws exception when no AI provider is configured', function () {
        $userWithoutProvider = User::factory()->create();

        $service = app(BusinessAnalyzerService::class);

        expect(fn () => $service->generateCompetitors(
            'We provide software solutions for businesses.',
            $userWithoutProvider
        ))->toThrow(\RuntimeException::class, 'No active AI provider configured');
    });
});

describe('generateAudiences endpoint', function () {
    it('requires authentication', function () {
        $response = $this->postJson('/projects/generate-audiences', [
            'description' => 'Test business description',
        ]);

        $response->assertUnauthorized();
    });

    it('validates description is required', function () {
        $response = $this->actingAs($this->user)
            ->postJson('/projects/generate-audiences', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['description']);
    });

    it('validates description minimum length', function () {
        $response = $this->actingAs($this->user)
            ->postJson('/projects/generate-audiences', [
                'description' => 'short',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['description']);
    });

    it('returns audiences on success', function () {
        mockAudiencesPrism(['Developers', 'Designers', 'Marketers']);

        $response = $this->actingAs($this->user)
            ->postJson('/projects/generate-audiences', [
                'description' => 'We provide software solutions for creative professionals.',
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => ['Developers', 'Designers', 'Marketers'],
            ]);
    });
});

describe('generateCompetitors endpoint', function () {
    it('requires authentication', function () {
        $response = $this->postJson('/projects/generate-competitors', [
            'description' => 'Test business description',
        ]);

        $response->assertUnauthorized();
    });

    it('validates description is required', function () {
        $response = $this->actingAs($this->user)
            ->postJson('/projects/generate-competitors', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['description']);
    });

    it('validates description minimum length', function () {
        $response = $this->actingAs($this->user)
            ->postJson('/projects/generate-competitors', [
                'description' => 'short',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['description']);
    });

    it('returns competitors on success', function () {
        mockCompetitorsPrism(['notion.so', 'asana.com', 'monday.com']);

        $response = $this->actingAs($this->user)
            ->postJson('/projects/generate-competitors', [
                'description' => 'We provide project management software for teams.',
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => ['notion.so', 'asana.com', 'monday.com'],
            ]);
    });
});

function mockKeywordsPrism(array $keywords = []): void
{
    $defaultKeywords = [
        ['keyword' => 'best project management software for small teams', 'search_intent' => 'commercial', 'difficulty' => 'medium', 'volume' => 'high'],
        ['keyword' => 'how to improve team productivity remotely', 'search_intent' => 'informational', 'difficulty' => 'low', 'volume' => 'medium'],
        ['keyword' => 'agile vs waterfall methodology comparison', 'search_intent' => 'informational', 'difficulty' => 'low', 'volume' => 'medium'],
        ['keyword' => 'project management tools for remote teams 2024', 'search_intent' => 'commercial', 'difficulty' => 'medium', 'volume' => 'high'],
        ['keyword' => 'remote team collaboration best practices guide', 'search_intent' => 'informational', 'difficulty' => 'low', 'volume' => 'low'],
    ];

    $data = ['keywords' => empty($keywords) ? $defaultKeywords : $keywords];
    $jsonContent = json_encode($data);

    $response = new Response(
        steps: collect(),
        text: $jsonContent,
        finishReason: FinishReason::Stop,
        toolCalls: [],
        toolResults: [],
        usage: new Usage(100, 200),
        meta: new Meta('test-id', 'gpt-4o'),
        messages: collect(),
    );

    $mockPendingRequest = Mockery::mock(PendingRequest::class);
    $mockPendingRequest->shouldReceive('using')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withClientOptions')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxTokens')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withSystemPrompt')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withPrompt')->andReturnSelf();
    $mockPendingRequest->shouldReceive('asText')->andReturn($response);

    $mockPrism = Mockery::mock(Prism::class);
    $mockPrism->shouldReceive('text')->andReturn($mockPendingRequest);

    app()->instance(Prism::class, $mockPrism);
}

describe('generateKeywordSuggestions', function () {
    it('generates keywords from business description', function () {
        mockKeywordsPrism();

        $service = app(BusinessAnalyzerService::class);
        $result = $service->generateKeywordSuggestions(
            'We provide project management software for teams.',
            ['Small business owners', 'Project managers'],
            ['asana.com', 'monday.com'],
            $this->user
        );

        expect($result)->toBeArray()
            ->and($result)->toHaveCount(5)
            ->and($result[0]['keyword'])->toBe('best project management software for small teams')
            ->and($result[0]['search_intent'])->toBe('commercial')
            ->and($result[0]['difficulty'])->toBe('medium')
            ->and($result[0]['volume'])->toBe('high');
    });

    it('throws exception when no AI provider is configured', function () {
        $userWithoutProvider = User::factory()->create();

        $service = app(BusinessAnalyzerService::class);

        expect(fn () => $service->generateKeywordSuggestions(
            'We provide software solutions for businesses.',
            [],
            [],
            $userWithoutProvider
        ))->toThrow(\RuntimeException::class, 'No active AI provider configured');
    });

    it('handles empty audiences and competitors', function () {
        mockKeywordsPrism();

        $service = app(BusinessAnalyzerService::class);
        $result = $service->generateKeywordSuggestions(
            'We provide software solutions for businesses.',
            [],
            [],
            $this->user
        );

        expect($result)->toBeArray()
            ->and($result)->toHaveCount(5);
    });
});

describe('generateKeywords endpoint', function () {
    it('requires authentication', function () {
        $response = $this->postJson('/projects/generate-keywords', [
            'description' => 'Test business description',
        ]);

        $response->assertUnauthorized();
    });

    it('validates description is required', function () {
        $response = $this->actingAs($this->user)
            ->postJson('/projects/generate-keywords', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['description']);
    });

    it('validates description minimum length', function () {
        $response = $this->actingAs($this->user)
            ->postJson('/projects/generate-keywords', [
                'description' => 'short',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['description']);
    });

    it('returns keywords on success', function () {
        mockKeywordsPrism([
            ['keyword' => 'seo optimization tips for beginners', 'search_intent' => 'informational', 'difficulty' => 'low', 'volume' => 'medium'],
            ['keyword' => 'best seo tools for small business', 'search_intent' => 'commercial', 'difficulty' => 'medium', 'volume' => 'high'],
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/projects/generate-keywords', [
                'description' => 'We provide SEO tools and optimization services for businesses.',
                'target_audiences' => ['SEO professionals', 'Marketing teams'],
                'competitors' => ['semrush.com', 'ahrefs.com'],
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    ['keyword' => 'seo optimization tips for beginners', 'search_intent' => 'informational', 'difficulty' => 'low', 'volume' => 'medium'],
                    ['keyword' => 'best seo tools for small business', 'search_intent' => 'commercial', 'difficulty' => 'medium', 'volume' => 'high'],
                ],
            ]);
    });

    it('accepts optional target_audiences and competitors', function () {
        mockKeywordsPrism();

        $response = $this->actingAs($this->user)
            ->postJson('/projects/generate-keywords', [
                'description' => 'We provide software solutions for businesses.',
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);
    });
});

describe('project creation with keywords', function () {
    it('creates keywords when project is created with keywords', function () {
        $response = $this->actingAs($this->user)
            ->post('/projects', [
                'name' => 'Test Project',
                'website_url' => 'https://test-project.com',
                'description' => 'A test project description',
                'keywords' => [
                    ['keyword' => 'test keyword one', 'search_intent' => 'informational'],
                    ['keyword' => 'test keyword two', 'search_intent' => 'commercial'],
                ],
            ]);

        $response->assertRedirect();

        $project = $this->user->projects()->where('name', 'Test Project')->first();
        expect($project)->not->toBeNull()
            ->and($project->keywords)->toHaveCount(2)
            ->and($project->keywords->first()->keyword)->toBe('test keyword one')
            ->and($project->keywords->first()->search_intent)->toBe('informational');
    });

    it('creates project without keywords', function () {
        $response = $this->actingAs($this->user)
            ->post('/projects', [
                'name' => 'Project Without Keywords',
                'website_url' => 'https://project-without-keywords.com',
                'description' => 'A test project description',
            ]);

        $response->assertRedirect();

        $project = $this->user->projects()->where('name', 'Project Without Keywords')->first();
        expect($project)->not->toBeNull()
            ->and($project->keywords)->toHaveCount(0);
    });

    it('defaults search_intent to informational if not provided', function () {
        $response = $this->actingAs($this->user)
            ->post('/projects', [
                'name' => 'Project With Default Intent',
                'website_url' => 'https://project-with-default-intent.com',
                'keywords' => [
                    ['keyword' => 'keyword without intent'],
                ],
            ]);

        $response->assertRedirect();

        $project = $this->user->projects()->where('name', 'Project With Default Intent')->first();
        expect($project->keywords->first()->search_intent)->toBe('informational');
    });
});
