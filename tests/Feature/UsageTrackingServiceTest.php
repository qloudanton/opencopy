<?php

use App\Models\AiProvider;
use App\Models\Article;
use App\Models\Project;
use App\Models\UsageLog;
use App\Models\User;
use App\Services\UsageTrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(UsageTrackingService::class);
    $this->user = User::factory()->create();
    $this->project = Project::factory()->create(['user_id' => $this->user->id]);
    $this->aiProvider = AiProvider::factory()->create([
        'user_id' => $this->user->id,
        'provider' => 'openai',
        'model' => 'gpt-4o',
    ]);
    $this->article = Article::factory()->create(['project_id' => $this->project->id]);
});

it('calculates text cost for gpt-4o correctly', function () {
    // GPT-4o: $5/1M input, $20/1M output
    $inputTokens = 1000;
    $outputTokens = 2000;

    $cost = $this->service->calculateTextCost('gpt-4o', $inputTokens, $outputTokens);

    // Input: 1000/1M * $5 = $0.005
    // Output: 2000/1M * $20 = $0.04
    // Total: $0.045
    expect($cost)->toBeGreaterThan(0.044);
    expect($cost)->toBeLessThan(0.046);
});

it('calculates text cost for claude-3-5-sonnet correctly', function () {
    // Claude Sonnet: $3/1M input, $15/1M output
    $inputTokens = 2000;
    $outputTokens = 1000;

    $cost = $this->service->calculateTextCost('claude-3-5-sonnet', $inputTokens, $outputTokens);

    // Input: 2000/1M * $3 = $0.006
    // Output: 1000/1M * $15 = $0.015
    // Total: $0.021
    expect($cost)->toBeGreaterThan(0.020);
    expect($cost)->toBeLessThan(0.022);
});

it('calculates text cost for ollama models as free', function () {
    $cost = $this->service->calculateTextCost('llama3.1', 10000, 5000);

    expect($cost)->toBe(0.0);
});

it('calculates image cost for gpt-image-1 high quality', function () {
    $cost = $this->service->calculateImageCost('gpt-image-1', 1, '1536x1024', 'high');

    expect($cost)->toBe(0.25);
});

it('calculates image cost for dall-e-3 hd', function () {
    $cost = $this->service->calculateImageCost('dall-e-3', 1, '1792x1024', 'hd');

    expect($cost)->toBe(0.12);
});

it('calculates image cost for multiple images', function () {
    $cost = $this->service->calculateImageCost('dall-e-3', 3, '1024x1024', 'standard');

    // 3 * $0.04 = $0.12
    expect($cost)->toBe(0.12);
});

it('logs text generation usage', function () {
    $this->service->logTextGeneration(
        user: $this->user,
        article: $this->article,
        aiProvider: $this->aiProvider,
        model: 'gpt-4o',
        inputTokens: 1000,
        outputTokens: 2000,
        operation: 'article_generation',
        metadata: ['keyword' => 'test keyword']
    );

    expect(UsageLog::count())->toBe(1);

    $log = UsageLog::first();
    expect($log->user_id)->toBe($this->user->id);
    expect($log->article_id)->toBe($this->article->id);
    expect($log->ai_provider_id)->toBe($this->aiProvider->id);
    expect($log->operation)->toBe('article_generation');
    expect($log->model)->toBe('gpt-4o');
    expect($log->input_tokens)->toBe(1000);
    expect($log->output_tokens)->toBe(2000);
    expect((float) $log->estimated_cost)->toBeGreaterThan(0);
    expect($log->metadata)->toBe(['keyword' => 'test keyword']);
});

it('logs image generation usage', function () {
    $this->service->logImageGeneration(
        user: $this->user,
        article: $this->article,
        aiProvider: $this->aiProvider,
        model: 'gpt-image-1',
        imageCount: 1,
        size: '1536x1024',
        quality: 'high',
        operation: 'featured_image',
        metadata: ['style' => 'illustration']
    );

    expect(UsageLog::count())->toBe(1);

    $log = UsageLog::first();
    expect($log->user_id)->toBe($this->user->id);
    expect($log->article_id)->toBe($this->article->id);
    expect($log->operation)->toBe('featured_image');
    expect($log->model)->toBe('gpt-image-1');
    expect($log->image_count)->toBe(1);
    expect($log->image_size)->toBe('1536x1024');
    expect($log->image_quality)->toBe('high');
    expect((float) $log->estimated_cost)->toBe(0.25);
});

it('gets article cost breakdown correctly', function () {
    // Log text generation
    $this->service->logTextGeneration(
        user: $this->user,
        article: $this->article,
        aiProvider: $this->aiProvider,
        model: 'gpt-4o',
        inputTokens: 1000,
        outputTokens: 2000,
        operation: 'article_generation'
    );

    // Log featured image
    $this->service->logImageGeneration(
        user: $this->user,
        article: $this->article,
        aiProvider: $this->aiProvider,
        model: 'dall-e-3',
        imageCount: 1,
        size: '1792x1024',
        quality: 'hd',
        operation: 'featured_image'
    );

    // Log inline images
    $this->service->logImageGeneration(
        user: $this->user,
        article: $this->article,
        aiProvider: $this->aiProvider,
        model: 'dall-e-3',
        imageCount: 2,
        size: '1792x1024',
        quality: 'hd',
        operation: 'inline_image'
    );

    $breakdown = $this->service->getArticleCostBreakdown($this->article);

    expect($breakdown['text_generation'])->toBeGreaterThan(0);
    expect($breakdown['image_generation'])->toBeGreaterThan(0);
    expect($breakdown['total'])->toBe($breakdown['text_generation'] + $breakdown['image_generation']);
    expect(count($breakdown['details']))->toBe(3);
});

it('calculates user monthly cost', function () {
    // Create logs for current month
    $this->service->logTextGeneration(
        user: $this->user,
        article: $this->article,
        aiProvider: $this->aiProvider,
        model: 'gpt-4o',
        inputTokens: 10000,
        outputTokens: 20000
    );

    $this->service->logImageGeneration(
        user: $this->user,
        article: $this->article,
        aiProvider: $this->aiProvider,
        model: 'dall-e-3',
        imageCount: 5,
        size: '1792x1024',
        quality: 'hd'
    );

    $monthlyCost = $this->service->getUserMonthlyCost($this->user);

    expect($monthlyCost)->toBeGreaterThan(0);
});

it('separates costs by user correctly', function () {
    $otherUser = User::factory()->create();
    $otherProject = Project::factory()->create(['user_id' => $otherUser->id]);
    $otherArticle = Article::factory()->create(['project_id' => $otherProject->id]);

    // Log for main user
    $this->service->logTextGeneration(
        user: $this->user,
        article: $this->article,
        aiProvider: $this->aiProvider,
        model: 'gpt-4o',
        inputTokens: 1000,
        outputTokens: 2000
    );

    // Log for other user
    $this->service->logTextGeneration(
        user: $otherUser,
        article: $otherArticle,
        aiProvider: $this->aiProvider,
        model: 'gpt-4o',
        inputTokens: 5000,
        outputTokens: 10000
    );

    $mainUserCost = $this->service->getUserMonthlyCost($this->user);
    $otherUserCost = $this->service->getUserMonthlyCost($otherUser);

    expect($otherUserCost)->toBeGreaterThan($mainUserCost);
});

it('article has usageLogs relationship', function () {
    $this->service->logTextGeneration(
        user: $this->user,
        article: $this->article,
        aiProvider: $this->aiProvider,
        model: 'gpt-4o',
        inputTokens: 1000,
        outputTokens: 2000
    );

    expect($this->article->usageLogs()->count())->toBe(1);
    expect($this->article->usageLogs->first()->model)->toBe('gpt-4o');
});

it('article totalCost method works correctly', function () {
    $this->service->logTextGeneration(
        user: $this->user,
        article: $this->article,
        aiProvider: $this->aiProvider,
        model: 'gpt-4o',
        inputTokens: 1000,
        outputTokens: 2000
    );

    $this->service->logImageGeneration(
        user: $this->user,
        article: $this->article,
        aiProvider: $this->aiProvider,
        model: 'dall-e-3',
        imageCount: 1,
        size: '1792x1024',
        quality: 'hd'
    );

    $totalCost = $this->article->totalCost();

    expect($totalCost)->toBeGreaterThan(0);
    expect($totalCost)->toBe($this->article->usageLogs()->sum('estimated_cost'));
});

it('uses fallback pricing for unknown models', function () {
    $cost = $this->service->calculateTextCost('unknown-model-xyz', 1000, 2000);

    // Should use default mid-tier pricing: $3/1M input, $15/1M output
    expect($cost)->toBeGreaterThan(0);
});
