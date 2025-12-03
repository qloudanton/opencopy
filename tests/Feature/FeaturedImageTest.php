<?php

use App\Models\AiProvider;
use App\Models\Article;
use App\Models\Image;
use App\Models\Keyword;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    // Use DALL-E 3 for tests (mocked response format)
    config(['services.openai.image_model' => 'dall-e-3']);
});

it('requires authentication to generate featured image', function () {
    $project = Project::factory()->create();
    $article = Article::factory()->create(['project_id' => $project->id]);

    $response = $this->postJson("/projects/{$project->id}/articles/{$article->id}/generate-featured-image", [
        'style' => 'illustration',
    ]);

    $response->assertUnauthorized();
});

it('forbids generating featured image for other users articles', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $otherUser->id]);
    $article = Article::factory()->create(['project_id' => $project->id]);

    $response = $this->actingAs($user)->postJson("/projects/{$project->id}/articles/{$article->id}/generate-featured-image", [
        'style' => 'illustration',
    ]);

    $response->assertForbidden();
});

it('validates style parameter', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $article = Article::factory()->create(['project_id' => $project->id]);
    AiProvider::factory()->create([
        'user_id' => $user->id,
        'is_active' => true,
    ]);

    $response = $this->actingAs($user)->postJson("/projects/{$project->id}/articles/{$article->id}/generate-featured-image", [
        'style' => 'invalid_style',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['style']);
});

it('returns error when no ai provider is configured', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $article = Article::factory()->create(['project_id' => $project->id]);

    $response = $this->actingAs($user)->postJson("/projects/{$project->id}/articles/{$article->id}/generate-featured-image", [
        'style' => 'illustration',
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'error' => 'No active AI provider with image support configured. Please add an AI provider in settings.',
        ]);
});

it('generates featured image with gradient fallback for non-ai-image providers', function () {
    $user = User::factory()->create();
    // Create a provider that supports images but isn't OpenAI/Gemini (will use gradient fallback)
    // The service checks if provider is in ['openai', 'gemini'] for AI generation
    $aiProvider = AiProvider::factory()->create([
        'user_id' => $user->id,
        'provider' => 'anthropic',
        'is_active' => true,
        'supports_image' => true, // Mark as supporting images for the cascade to find it
    ]);
    $project = Project::factory()->create([
        'user_id' => $user->id,
        'brand_color' => '#3B82F6',
        'image_style' => 'illustration',
    ]);
    $keyword = Keyword::factory()->create(['project_id' => $project->id]);
    $article = Article::factory()->create([
        'project_id' => $project->id,
        'keyword_id' => $keyword->id,
        'title' => 'Test Article Title',
    ]);

    $response = $this->actingAs($user)->postJson("/projects/{$project->id}/articles/{$article->id}/generate-featured-image", [
        'style' => 'illustration',
    ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'status' => 'queued',
            'message' => 'Featured image generation started',
        ]);

    // Run the queued job
    $this->artisan('queue:work', ['--once' => true]);

    // Verify image was created in database (uses gradient fallback since anthropic isn't in IMAGE_GENERATION_PROVIDERS)
    expect(Image::where('article_id', $article->id)->where('type', 'featured')->exists())->toBeTrue();
});

it('generates featured image with AI for openai providers', function () {
    $user = User::factory()->create();
    $aiProvider = AiProvider::factory()->create([
        'user_id' => $user->id,
        'provider' => 'openai',
        'api_key' => 'test-api-key',
        'is_active' => true,
        'supports_image' => true,
    ]);
    $project = Project::factory()->create([
        'user_id' => $user->id,
        'brand_color' => '#10B981',
        'image_style' => 'watercolor',
    ]);
    $keyword = Keyword::factory()->create(['project_id' => $project->id]);
    $article = Article::factory()->create([
        'project_id' => $project->id,
        'keyword_id' => $keyword->id,
        'title' => 'Beautiful Watercolor Art',
    ]);

    // Mock OpenAI DALL-E API response via Prism
    $openaiResponse = Http::response([
        'data' => [
            [
                // 1x1 transparent PNG in base64
                'b64_json' => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
            ],
        ],
    ]);

    Http::fake([
        'api.openai.com/*' => $openaiResponse,
        '*' => $openaiResponse, // Catch-all
    ]);

    // Request starts the job
    $response = $this->actingAs($user)->postJson("/projects/{$project->id}/articles/{$article->id}/generate-featured-image", [
        'style' => 'watercolor',
    ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'status' => 'queued',
            'message' => 'Featured image generation started',
        ]);

    // Run the queued job
    $this->artisan('queue:work', ['--once' => true]);

    // Verify image was created
    expect(Image::where('article_id', $article->id)->where('type', 'featured')->exists())->toBeTrue();
});

it('generates featured image with AI for gemini providers', function () {
    $user = User::factory()->create();
    $aiProvider = AiProvider::factory()->create([
        'user_id' => $user->id,
        'provider' => 'gemini',
        'api_key' => 'test-api-key',
        'is_active' => true,
        'supports_image' => true,
    ]);
    $project = Project::factory()->create([
        'user_id' => $user->id,
        'brand_color' => '#8B5CF6',
        'image_style' => 'illustration',
    ]);
    $keyword = Keyword::factory()->create(['project_id' => $project->id]);
    $article = Article::factory()->create([
        'project_id' => $project->id,
        'keyword_id' => $keyword->id,
        'title' => 'Modern Illustration Design',
    ]);

    // Mock Gemini Imagen API response via Prism (catch all Google API patterns)
    $geminiResponse = Http::response([
        'predictions' => [
            [
                'bytesBase64Encoded' => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
            ],
        ],
    ]);

    Http::fake([
        'generativelanguage.googleapis.com/*' => $geminiResponse,
        '*googleapis.com/*' => $geminiResponse,
        '*google.com/*' => $geminiResponse,
        '*' => $geminiResponse, // Catch-all for any other endpoints Prism might use
    ]);

    // Request starts the job
    $response = $this->actingAs($user)->postJson("/projects/{$project->id}/articles/{$article->id}/generate-featured-image", [
        'style' => 'illustration',
    ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'status' => 'queued',
            'message' => 'Featured image generation started',
        ]);

    // Run the queued job
    $this->artisan('queue:work', ['--once' => true]);

    // Verify image was created
    expect(Image::where('article_id', $article->id)->where('type', 'featured')->exists())->toBeTrue();
});

it('replaces existing featured image when regenerating', function () {
    $user = User::factory()->create();
    $aiProvider = AiProvider::factory()->create([
        'user_id' => $user->id,
        'provider' => 'anthropic',
        'is_active' => true,
        'supports_image' => true, // Mark as supporting images for the cascade to find it
    ]);
    $project = Project::factory()->create(['user_id' => $user->id]);
    $article = Article::factory()->create(['project_id' => $project->id]);

    // Create existing featured image
    $existingImage = Image::factory()->featured()->create([
        'project_id' => $project->id,
        'article_id' => $article->id,
        'path' => 'featured-images/old-image.png',
    ]);

    $response = $this->actingAs($user)->postJson("/projects/{$project->id}/articles/{$article->id}/generate-featured-image", [
        'style' => 'sketch',
    ]);

    $response->assertOk()
        ->assertJson([
            'status' => 'queued',
        ]);

    // Run the queued job
    $this->artisan('queue:work', ['--once' => true]);

    // Verify old image was deleted
    expect(Image::find($existingImage->id))->toBeNull();

    // Verify new image was created
    $newImage = Image::where('article_id', $article->id)->where('type', 'featured')->first();
    expect($newImage)->not->toBeNull();
    expect($newImage->id)->not->toBe($existingImage->id);
});

it('requires authentication to delete featured image', function () {
    $project = Project::factory()->create();
    $article = Article::factory()->create(['project_id' => $project->id]);

    $response = $this->deleteJson("/projects/{$project->id}/articles/{$article->id}/featured-image");

    $response->assertUnauthorized();
});

it('deletes featured image', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $article = Article::factory()->create(['project_id' => $project->id]);

    $image = Image::factory()->featured()->create([
        'project_id' => $project->id,
        'article_id' => $article->id,
        'path' => 'featured-images/test-image.png',
    ]);

    $response = $this->actingAs($user)->deleteJson("/projects/{$project->id}/articles/{$article->id}/featured-image");

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Featured image deleted',
        ]);

    expect(Image::find($image->id))->toBeNull();
});

it('returns 404 when deleting non-existent featured image', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $article = Article::factory()->create(['project_id' => $project->id]);

    $response = $this->actingAs($user)->deleteJson("/projects/{$project->id}/articles/{$article->id}/featured-image");

    $response->assertNotFound()
        ->assertJson([
            'error' => 'No featured image found',
        ]);
});

it('returns 404 for article not belonging to project', function () {
    $user = User::factory()->create();
    $project1 = Project::factory()->create(['user_id' => $user->id]);
    $project2 = Project::factory()->create(['user_id' => $user->id]);
    $article = Article::factory()->create(['project_id' => $project2->id]);
    AiProvider::factory()->create([
        'user_id' => $user->id,
        'is_active' => true,
    ]);

    $response = $this->actingAs($user)->postJson("/projects/{$project1->id}/articles/{$article->id}/generate-featured-image", [
        'style' => 'illustration',
    ]);

    $response->assertNotFound();
});

it('uses project default style when style not specified', function () {
    $user = User::factory()->create();
    $aiProvider = AiProvider::factory()->create([
        'user_id' => $user->id,
        'provider' => 'anthropic',
        'is_active' => true,
        'supports_image' => true,
    ]);
    $project = Project::factory()->create([
        'user_id' => $user->id,
        'image_style' => 'sketch',
    ]);
    $article = Article::factory()->create(['project_id' => $project->id]);

    $response = $this->actingAs($user)->postJson("/projects/{$project->id}/articles/{$article->id}/generate-featured-image");

    $response->assertOk()
        ->assertJson(['status' => 'queued']);

    // Run the queued job
    $this->artisan('queue:work', ['--once' => true]);

    $image = Image::where('article_id', $article->id)->where('type', 'featured')->first();
    expect($image->metadata['style'])->toBe('sketch');
});

it('supports all style options', function (string $style) {
    $user = User::factory()->create();
    $aiProvider = AiProvider::factory()->create([
        'user_id' => $user->id,
        'provider' => 'anthropic',
        'is_active' => true,
        'supports_image' => true,
    ]);
    $project = Project::factory()->create(['user_id' => $user->id]);
    $article = Article::factory()->create(['project_id' => $project->id]);

    $response = $this->actingAs($user)->postJson("/projects/{$project->id}/articles/{$article->id}/generate-featured-image", [
        'style' => $style,
    ]);

    $response->assertOk()
        ->assertJson(['status' => 'queued']);

    // Run the queued job
    $this->artisan('queue:work', ['--once' => true]);

    $image = Image::where('article_id', $article->id)->where('type', 'featured')->first();
    expect($image->metadata['style'])->toBe($style);
})->with(['sketch', 'watercolor', 'illustration', 'cinematic', 'brand_text']);

it('stores image with correct dimensions', function () {
    $user = User::factory()->create();
    $aiProvider = AiProvider::factory()->create([
        'user_id' => $user->id,
        'provider' => 'anthropic',
        'is_active' => true,
        'supports_image' => true,
    ]);
    $project = Project::factory()->create(['user_id' => $user->id]);
    $article = Article::factory()->create(['project_id' => $project->id]);

    $response = $this->actingAs($user)->postJson("/projects/{$project->id}/articles/{$article->id}/generate-featured-image");

    $response->assertOk()
        ->assertJson(['status' => 'queued']);

    // Run the queued job
    $this->artisan('queue:work', ['--once' => true]);

    $image = Image::where('article_id', $article->id)->where('type', 'featured')->first();
    expect($image->width)->toBe(1312);
    expect($image->height)->toBe(736);
});

it('includes featured image in article edit page', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $article = Article::factory()->create(['project_id' => $project->id]);
    $image = Image::factory()->featured()->create([
        'project_id' => $project->id,
        'article_id' => $article->id,
    ]);

    $response = $this->actingAs($user)->get("/projects/{$project->id}/articles/{$article->id}/edit");

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Articles/Edit')
            ->has('featuredImage')
            ->where('featuredImage.id', $image->id)
        );
});

it('returns null featured image when none exists', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $article = Article::factory()->create(['project_id' => $project->id]);

    $response = $this->actingAs($user)->get("/projects/{$project->id}/articles/{$article->id}/edit");

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Articles/Edit')
            ->where('featuredImage', null)
        );
});

it('includes featured image in article show page', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $article = Article::factory()->create(['project_id' => $project->id]);
    $image = Image::factory()->featured()->create([
        'project_id' => $project->id,
        'article_id' => $article->id,
    ]);

    $response = $this->actingAs($user)->get("/projects/{$project->id}/articles/{$article->id}");

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Articles/Show')
            ->has('featuredImage')
            ->where('featuredImage.id', $image->id)
        );
});

it('returns null featured image on show page when none exists', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $article = Article::factory()->create(['project_id' => $project->id]);

    $response = $this->actingAs($user)->get("/projects/{$project->id}/articles/{$article->id}");

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Articles/Show')
            ->where('featuredImage', null)
        );
});
