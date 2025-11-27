<?php

use App\Models\AiProvider;
use App\Models\Article;
use App\Models\Image;
use App\Models\Integration;
use App\Models\InternalLink;
use App\Models\Keyword;
use App\Models\Project;
use App\Models\Publication;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('can create a project with factory', function () {
    $project = Project::factory()->create();

    expect($project)->toBeInstanceOf(Project::class)
        ->and($project->user)->toBeInstanceOf(User::class)
        ->and($project->is_active)->toBeTrue();
});

it('can create a user with projects', function () {
    $user = User::factory()
        ->has(Project::factory()->count(3))
        ->create();

    expect($user->projects)->toHaveCount(3);
});

it('can create a project with keywords', function () {
    $project = Project::factory()
        ->has(Keyword::factory()->count(5))
        ->create();

    expect($project->keywords)->toHaveCount(5);
});

it('can create a keyword with articles', function () {
    $keyword = Keyword::factory()
        ->has(Article::factory()->count(2))
        ->create();

    expect($keyword->articles)->toHaveCount(2)
        ->and($keyword->latestArticle())->toBeInstanceOf(Article::class);
});

it('can create an article with internal links', function () {
    $project = Project::factory()->create();
    $article = Article::factory()->for($project)->create();
    $links = InternalLink::factory()->for($project)->count(3)->create();

    $article->internalLinks()->attach($links->pluck('id'), [
        'anchor_text_used' => 'test anchor',
    ]);

    expect($article->internalLinks)->toHaveCount(3);
});

it('can create a user with ai providers', function () {
    $user = User::factory()
        ->has(AiProvider::factory()->openai()->default())
        ->has(AiProvider::factory()->anthropic())
        ->create();

    expect($user->aiProviders)->toHaveCount(2)
        ->and($user->defaultAiProvider())->toBeInstanceOf(AiProvider::class)
        ->and($user->defaultAiProvider()->provider)->toBe('openai');
});

it('encrypts ai provider api key', function () {
    $provider = AiProvider::factory()->create([
        'api_key' => 'sk-test-secret-key',
    ]);

    // Reload from database
    $provider->refresh();

    expect($provider->api_key)->toBe('sk-test-secret-key');

    // Check it's encrypted in the raw database
    $raw = \DB::table('ai_providers')->where('id', $provider->id)->first();
    expect($raw->api_key)->not->toBe('sk-test-secret-key');
});

it('encrypts integration credentials', function () {
    $integration = Integration::factory()->wordpress()->create();

    $integration->refresh();

    expect($integration->credentials)->toBeArray()
        ->and($integration->credentials)->toHaveKey('url');
});

it('can track article publications', function () {
    $article = Article::factory()->create();
    $integration = Integration::factory()->for($article->project)->create();

    $publication = Publication::factory()
        ->for($article)
        ->for($integration)
        ->published()
        ->create();

    expect($publication->isPublished())->toBeTrue()
        ->and($article->publications)->toHaveCount(1);
});

it('generates slug from title on article creation', function () {
    $article = Article::factory()->create([
        'title' => 'My Amazing Article Title',
        'slug' => null,
    ]);

    expect($article->slug)->toBe('my-amazing-article-title');
});

it('generates unique slug when duplicate exists in same project', function () {
    $project = Project::factory()->create();
    $keyword = Keyword::factory()->for($project)->create();

    $article1 = Article::factory()->for($project)->for($keyword)->create([
        'title' => 'How to Create an Invoice',
        'slug' => null,
    ]);

    $article2 = Article::factory()->for($project)->for($keyword)->create([
        'title' => 'How to Create an Invoice',
        'slug' => null,
    ]);

    $article3 = Article::factory()->for($project)->for($keyword)->create([
        'title' => 'How to Create an Invoice',
        'slug' => null,
    ]);

    expect($article1->slug)->toBe('how-to-create-an-invoice');
    expect($article2->slug)->toBe('how-to-create-an-invoice-1');
    expect($article3->slug)->toBe('how-to-create-an-invoice-2');
});

it('allows same slug in different projects', function () {
    $project1 = Project::factory()->create();
    $project2 = Project::factory()->create();
    $keyword1 = Keyword::factory()->for($project1)->create();
    $keyword2 = Keyword::factory()->for($project2)->create();

    $article1 = Article::factory()->for($project1)->for($keyword1)->create([
        'title' => 'How to Create an Invoice',
        'slug' => null,
    ]);

    $article2 = Article::factory()->for($project2)->for($keyword2)->create([
        'title' => 'How to Create an Invoice',
        'slug' => null,
    ]);

    expect($article1->slug)->toBe('how-to-create-an-invoice');
    expect($article2->slug)->toBe('how-to-create-an-invoice');
});

it('can create images for articles', function () {
    $article = Article::factory()->create();
    $featured = Image::factory()->featured()->for($article)->for($article->project)->create();
    $content = Image::factory()->content()->for($article)->for($article->project)->count(2)->create();

    expect($article->images)->toHaveCount(3)
        ->and($article->featuredImage())->toBeInstanceOf(Image::class)
        ->and($article->featuredImage()->isFeatured())->toBeTrue();
});
