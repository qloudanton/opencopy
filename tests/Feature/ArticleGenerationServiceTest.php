<?php

use App\Enums\ContentStatus;
use App\Models\AiProvider;
use App\Models\Article;
use App\Models\Keyword;
use App\Models\Project;
use App\Models\ScheduledContent;
use App\Models\User;
use App\Services\ArticleGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Prism;
use Prism\Prism\Text\PendingRequest;
use Prism\Prism\Text\Response;
use Prism\Prism\ValueObjects\Meta;
use Prism\Prism\ValueObjects\Usage;

uses(RefreshDatabase::class);

function mockPrismResponse(string $content = ''): void
{
    $defaultContent = <<<'MD'
---
title: Test Article Title
meta_description: This is a test meta description for the generated article.
---

## Introduction

This is a test article about the keyword.

## Main Content

Lorem ipsum dolor sit amet, consectetur adipiscing elit.

## Conclusion

This concludes our test article.
MD;

    $content = $content ?: $defaultContent;

    $response = new Response(
        steps: collect(),
        text: $content,
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

it('generates an article from scheduled content', function () {
    mockPrismResponse();

    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $aiProvider = AiProvider::factory()->for($user)->openai()->default()->create();
    $keyword = Keyword::factory()->for($project)->create([
        'keyword' => 'best coffee makers',
    ]);
    $scheduledContent = ScheduledContent::factory()
        ->withKeyword($keyword)
        ->scheduled()
        ->create();

    $service = app(ArticleGenerationService::class);
    $article = $service->generate($scheduledContent);

    expect($article)->toBeInstanceOf(Article::class)
        ->and($article->title)->toBe('Test Article Title')
        ->and($article->meta_description)->toBe('This is a test meta description for the generated article.')
        ->and($article->project_id)->toBe($project->id)
        ->and($article->keyword_id)->toBe($keyword->id)
        ->and($article->ai_provider_id)->toBe($aiProvider->id)
        ->and($article->generated_at)->not->toBeNull();

    expect($scheduledContent->fresh()->status)->toBe(ContentStatus::InReview);
});

it('uses the default ai provider when none specified', function () {
    mockPrismResponse();

    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $defaultProvider = AiProvider::factory()->for($user)->default()->create();
    AiProvider::factory()->for($user)->create(['is_default' => false]);
    $keyword = Keyword::factory()->for($project)->create();
    $scheduledContent = ScheduledContent::factory()
        ->withKeyword($keyword)
        ->scheduled()
        ->create();

    $service = app(ArticleGenerationService::class);
    $article = $service->generate($scheduledContent);

    expect($article->ai_provider_id)->toBe($defaultProvider->id);
});

it('uses first active provider when no default set', function () {
    mockPrismResponse();

    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $activeProvider = AiProvider::factory()->for($user)->create([
        'is_default' => false,
        'is_active' => true,
    ]);
    $keyword = Keyword::factory()->for($project)->create();
    $scheduledContent = ScheduledContent::factory()
        ->withKeyword($keyword)
        ->scheduled()
        ->create();

    $service = app(ArticleGenerationService::class);
    $article = $service->generate($scheduledContent);

    expect($article->ai_provider_id)->toBe($activeProvider->id);
});

it('throws exception when no ai provider configured', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $keyword = Keyword::factory()->for($project)->create();
    $scheduledContent = ScheduledContent::factory()
        ->withKeyword($keyword)
        ->scheduled()
        ->create();

    $service = app(ArticleGenerationService::class);
    $service->generate($scheduledContent);
})->throws(RuntimeException::class, 'No active AI provider configured');

it('throws exception when specified provider is inactive', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $inactiveProvider = AiProvider::factory()->for($user)->create(['is_active' => false]);
    $keyword = Keyword::factory()->for($project)->create();
    $scheduledContent = ScheduledContent::factory()
        ->withKeyword($keyword)
        ->scheduled()
        ->create();

    $service = app(ArticleGenerationService::class);
    $service->generate($scheduledContent, $inactiveProvider);
})->throws(RuntimeException::class, 'The selected AI provider is not active');

it('throws exception when provider belongs to different user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $otherProvider = AiProvider::factory()->for($otherUser)->create();
    $keyword = Keyword::factory()->for($project)->create();
    $scheduledContent = ScheduledContent::factory()
        ->withKeyword($keyword)
        ->scheduled()
        ->create();

    $service = app(ArticleGenerationService::class);
    $service->generate($scheduledContent, $otherProvider);
})->throws(RuntimeException::class, 'The AI provider does not belong to the project owner');

it('updates scheduled content status to generating then in_review on success', function () {
    mockPrismResponse();

    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    AiProvider::factory()->for($user)->default()->create();
    $keyword = Keyword::factory()->for($project)->create();
    $scheduledContent = ScheduledContent::factory()
        ->withKeyword($keyword)
        ->scheduled()
        ->create();

    $service = app(ArticleGenerationService::class);
    $service->generate($scheduledContent);

    expect($scheduledContent->fresh()->status)->toBe(ContentStatus::InReview);
});

it('updates scheduled content status to failed on error', function () {
    $mockPendingRequest = Mockery::mock(PendingRequest::class);
    $mockPendingRequest->shouldReceive('using')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withClientOptions')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withMaxTokens')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withSystemPrompt')->andReturnSelf();
    $mockPendingRequest->shouldReceive('withPrompt')->andReturnSelf();
    $mockPendingRequest->shouldReceive('asText')->andThrow(new Exception('API Error'));

    $mockPrism = Mockery::mock(Prism::class);
    $mockPrism->shouldReceive('text')->andReturn($mockPendingRequest);

    app()->instance(Prism::class, $mockPrism);

    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    AiProvider::factory()->for($user)->default()->create();
    $keyword = Keyword::factory()->for($project)->create();
    $scheduledContent = ScheduledContent::factory()
        ->withKeyword($keyword)
        ->scheduled()
        ->create();

    $service = app(ArticleGenerationService::class);

    try {
        $service->generate($scheduledContent);
    } catch (Exception) {
        // Expected
    }

    expect($scheduledContent->fresh()->status)->toBe(ContentStatus::Failed)
        ->and($scheduledContent->fresh()->error_message)->toBe('API Error');
});

it('stores generation metadata', function () {
    mockPrismResponse();

    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $aiProvider = AiProvider::factory()->for($user)->openai()->default()->create();
    $keyword = Keyword::factory()->for($project)->create([
        'keyword' => 'test keyword',
        'secondary_keywords' => ['related', 'terms'],
        'target_word_count' => 2000,
    ]);
    $scheduledContent = ScheduledContent::factory()
        ->withKeyword($keyword)
        ->scheduled()
        ->create();

    $service = app(ArticleGenerationService::class);
    $article = $service->generate($scheduledContent);

    expect($article->generation_metadata)
        ->toHaveKey('provider', 'openai')
        ->toHaveKey('model', 'gpt-4o')
        ->toHaveKey('keyword', 'test keyword')
        ->toHaveKey('secondary_keywords')
        ->toHaveKey('target_word_count', 2000);
});

it('parses title from markdown heading when no frontmatter', function () {
    mockPrismResponse("# My Article Title\n\nThis is the content.");

    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    AiProvider::factory()->for($user)->default()->create();
    $keyword = Keyword::factory()->for($project)->create(['keyword' => 'fallback keyword']);
    $scheduledContent = ScheduledContent::factory()
        ->withKeyword($keyword)
        ->scheduled()
        ->create();

    $service = app(ArticleGenerationService::class);
    $article = $service->generate($scheduledContent);

    expect($article->title)->toBe('My Article Title');
});

it('uses keyword as title when no title found', function () {
    mockPrismResponse('Just some content without any heading.');

    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    AiProvider::factory()->for($user)->default()->create();
    $keyword = Keyword::factory()->for($project)->create(['keyword' => 'my keyword']);
    $scheduledContent = ScheduledContent::factory()
        ->withKeyword($keyword)
        ->scheduled()
        ->create();

    $service = app(ArticleGenerationService::class);
    $article = $service->generate($scheduledContent);

    expect($article->title)->toBe('my keyword');
});

it('calculates word count and reading time', function () {
    $content = <<<'MD'
---
title: Test
meta_description: Test description
---

This is a test article with some content to count words.
We need enough words to make the reading time meaningful.
MD;

    mockPrismResponse($content);

    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    AiProvider::factory()->for($user)->default()->create();
    $keyword = Keyword::factory()->for($project)->create();
    $scheduledContent = ScheduledContent::factory()
        ->withKeyword($keyword)
        ->scheduled()
        ->create();

    $service = app(ArticleGenerationService::class);
    $article = $service->generate($scheduledContent);

    expect($article->word_count)->toBeGreaterThan(0)
        ->and($article->reading_time_minutes)->toBeGreaterThanOrEqual(1);
});
