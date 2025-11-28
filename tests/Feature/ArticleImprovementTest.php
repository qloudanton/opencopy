<?php

use App\Models\AiProvider;
use App\Models\Article;
use App\Models\Keyword;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Prism;
use Prism\Prism\Text\PendingRequest;
use Prism\Prism\Text\Response;
use Prism\Prism\ValueObjects\Meta;
use Prism\Prism\ValueObjects\Usage;

uses(RefreshDatabase::class);

function mockImprovementResponse(string $content): void
{
    $response = new Response(
        steps: collect(),
        text: $content,
        finishReason: FinishReason::Stop,
        toolCalls: [],
        toolResults: [],
        usage: new Usage(50, 100),
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

it('requires authentication to access improve endpoint', function () {
    $project = Project::factory()->create();
    $article = Article::factory()->create(['project_id' => $project->id]);

    $response = $this->postJson("/projects/{$project->id}/articles/{$article->id}/improve", [
        'improvement_type' => 'add_keyword_to_title',
    ]);

    $response->assertUnauthorized();
});

it('forbids access to articles from other users projects', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $otherUser->id]);
    $article = Article::factory()->create(['project_id' => $project->id]);

    $response = $this->actingAs($user)->postJson("/projects/{$project->id}/articles/{$article->id}/improve", [
        'improvement_type' => 'add_keyword_to_title',
    ]);

    $response->assertForbidden();
});

it('validates improvement type is required', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $article = Article::factory()->create(['project_id' => $project->id]);

    $response = $this->actingAs($user)->postJson("/projects/{$project->id}/articles/{$article->id}/improve", []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['improvement_type']);
});

it('validates improvement type is valid', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $article = Article::factory()->create(['project_id' => $project->id]);

    $response = $this->actingAs($user)->postJson("/projects/{$project->id}/articles/{$article->id}/improve", [
        'improvement_type' => 'invalid_type',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['improvement_type']);
});

it('returns error when no ai provider is configured', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $article = Article::factory()->create(['project_id' => $project->id]);

    $response = $this->actingAs($user)->postJson("/projects/{$project->id}/articles/{$article->id}/improve", [
        'improvement_type' => 'add_keyword_to_title',
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'error' => 'No active AI provider configured. Please add an AI provider in settings.',
        ]);
});

it('improves article title with keyword', function () {
    $user = User::factory()->create();
    $aiProvider = AiProvider::factory()->create([
        'user_id' => $user->id,
        'is_active' => true,
        'is_default' => true,
    ]);
    $project = Project::factory()->create(['user_id' => $user->id]);
    $keyword = Keyword::factory()->create(['project_id' => $project->id, 'keyword' => 'test keyword']);
    $article = Article::factory()->create([
        'project_id' => $project->id,
        'keyword_id' => $keyword->id,
        'title' => 'Original Title',
    ]);

    mockImprovementResponse('New Title with Test Keyword');

    $response = $this->actingAs($user)->postJson("/projects/{$project->id}/articles/{$article->id}/improve", [
        'improvement_type' => 'add_keyword_to_title',
    ]);

    $response->assertOk()
        ->assertJson([
            'field' => 'title',
            'value' => 'New Title with Test Keyword',
            'message' => 'Title updated to include keyword',
        ]);
});

it('improves article meta description', function () {
    $user = User::factory()->create();
    $aiProvider = AiProvider::factory()->create([
        'user_id' => $user->id,
        'is_active' => true,
        'is_default' => true,
    ]);
    $project = Project::factory()->create(['user_id' => $user->id]);
    $keyword = Keyword::factory()->create(['project_id' => $project->id, 'keyword' => 'seo']);
    $article = Article::factory()->create([
        'project_id' => $project->id,
        'keyword_id' => $keyword->id,
        'meta_description' => 'Original meta',
    ]);

    mockImprovementResponse('Learn about SEO best practices in this comprehensive guide to improving your website rankings.');

    $response = $this->actingAs($user)->postJson("/projects/{$project->id}/articles/{$article->id}/improve", [
        'improvement_type' => 'add_keyword_to_meta',
    ]);

    $response->assertOk()
        ->assertJson([
            'field' => 'meta_description',
            'message' => 'Meta description updated to include keyword',
        ]);
});

it('adds faq section to content', function () {
    $user = User::factory()->create();
    $aiProvider = AiProvider::factory()->create([
        'user_id' => $user->id,
        'is_active' => true,
        'is_default' => true,
    ]);
    $project = Project::factory()->create(['user_id' => $user->id]);
    $keyword = Keyword::factory()->create(['project_id' => $project->id]);
    $article = Article::factory()->create([
        'project_id' => $project->id,
        'keyword_id' => $keyword->id,
        'content_markdown' => '# Original Content',
    ]);

    $faqContent = <<<'FAQ'
## FAQ

### What is the topic?
This is an answer.

### How does it work?
This explains how it works.
FAQ;

    mockImprovementResponse($faqContent);

    $response = $this->actingAs($user)->postJson("/projects/{$project->id}/articles/{$article->id}/improve", [
        'improvement_type' => 'add_faq_section',
    ]);

    $response->assertOk()
        ->assertJson([
            'field' => 'content',
            'message' => 'FAQ section added to content',
        ]);

    // Verify the original content is preserved
    expect($response->json('value'))->toContain('# Original Content');
    expect($response->json('value'))->toContain('## FAQ');
});

it('adds table to content', function () {
    $user = User::factory()->create();
    $aiProvider = AiProvider::factory()->create([
        'user_id' => $user->id,
        'is_active' => true,
        'is_default' => true,
    ]);
    $project = Project::factory()->create(['user_id' => $user->id]);
    $keyword = Keyword::factory()->create(['project_id' => $project->id]);
    $article = Article::factory()->create([
        'project_id' => $project->id,
        'keyword_id' => $keyword->id,
        'content_markdown' => '# Original Content',
    ]);

    $tableContent = <<<'TABLE'
## Comparison Table

| Feature | Option A | Option B |
|---------|----------|----------|
| Price   | $10      | $20      |
| Speed   | Fast     | Faster   |
TABLE;

    mockImprovementResponse($tableContent);

    $response = $this->actingAs($user)->postJson("/projects/{$project->id}/articles/{$article->id}/improve", [
        'improvement_type' => 'add_table',
    ]);

    $response->assertOk()
        ->assertJson([
            'field' => 'content',
            'message' => 'Comparison table added to content',
        ]);
});

it('uses non-default ai provider when default is not set', function () {
    $user = User::factory()->create();
    $aiProvider = AiProvider::factory()->create([
        'user_id' => $user->id,
        'is_active' => true,
        'is_default' => false,
    ]);
    $project = Project::factory()->create(['user_id' => $user->id]);
    $keyword = Keyword::factory()->create(['project_id' => $project->id]);
    $article = Article::factory()->create([
        'project_id' => $project->id,
        'keyword_id' => $keyword->id,
    ]);

    mockImprovementResponse('Improved Title');

    $response = $this->actingAs($user)->postJson("/projects/{$project->id}/articles/{$article->id}/improve", [
        'improvement_type' => 'add_keyword_to_title',
    ]);

    $response->assertOk();
});

it('returns 404 for article not belonging to project', function () {
    $user = User::factory()->create();
    $project1 = Project::factory()->create(['user_id' => $user->id]);
    $project2 = Project::factory()->create(['user_id' => $user->id]);
    $article = Article::factory()->create(['project_id' => $project2->id]);

    $response = $this->actingAs($user)->postJson("/projects/{$project1->id}/articles/{$article->id}/improve", [
        'improvement_type' => 'add_keyword_to_title',
    ]);

    $response->assertNotFound();
});

it('supports all valid improvement types', function (string $improvementType) {
    $user = User::factory()->create();
    $aiProvider = AiProvider::factory()->create([
        'user_id' => $user->id,
        'is_active' => true,
        'is_default' => true,
    ]);
    $project = Project::factory()->create(['user_id' => $user->id]);
    $keyword = Keyword::factory()->create(['project_id' => $project->id]);
    $article = Article::factory()->create([
        'project_id' => $project->id,
        'keyword_id' => $keyword->id,
        'content_markdown' => "# Title\n\n## Section\n\nSome content here.",
    ]);

    mockImprovementResponse('Improved content');

    $response = $this->actingAs($user)->postJson("/projects/{$project->id}/articles/{$article->id}/improve", [
        'improvement_type' => $improvementType,
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'field',
            'value',
            'message',
        ]);
})->with([
    'add_keyword_to_title',
    'add_keyword_to_meta',
    'add_faq_section',
    'add_table',
    'add_h2_headings',
    'add_lists',
    'optimize_title_length',
    'optimize_meta_length',
    'add_keyword_to_h2',
    'add_keyword_to_intro',
]);

it('can recalculate seo score with current form data', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $keyword = Keyword::factory()->create([
        'project_id' => $project->id,
        'keyword' => 'test keyword',
        'target_word_count' => 1500,
    ]);
    $article = Article::factory()->create([
        'project_id' => $project->id,
        'keyword_id' => $keyword->id,
        'title' => 'Original Title',
        'meta_description' => 'Original meta',
        'content_markdown' => 'Original content',
    ]);

    $response = $this->actingAs($user)->postJson(
        "/projects/{$project->id}/articles/{$article->id}/recalculate-seo",
        [
            'title' => 'New Title with Test Keyword for Better SEO Results',
            'meta_description' => 'This is a new meta description that includes the test keyword and is the optimal length for search engines.',
            'content' => "## Introduction to Test Keyword\n\nThis is content about the test keyword.\n\n## Why Test Keyword Matters\n\nMore content here.",
        ],
    );

    $response->assertOk()
        ->assertJsonStructure([
            'score',
            'breakdown' => [
                'keyword_optimization',
                'content_structure',
                'content_length',
                'meta_quality',
                'enrichment',
            ],
        ]);

    expect($response->json('score'))->toBeInt()->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(100);
});

it('recalculate seo requires authentication', function () {
    $project = Project::factory()->create();
    $article = Article::factory()->create(['project_id' => $project->id]);

    $response = $this->postJson(
        "/projects/{$project->id}/articles/{$article->id}/recalculate-seo",
        [
            'title' => 'Test',
            'meta_description' => 'Test',
            'content' => 'Test',
        ],
    );

    $response->assertUnauthorized();
});

it('recalculate seo validates required fields', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $article = Article::factory()->create(['project_id' => $project->id]);

    $response = $this->actingAs($user)->postJson(
        "/projects/{$project->id}/articles/{$article->id}/recalculate-seo",
        [],
    );

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['title', 'content']);
});
