<?php

use App\Models\AiProvider;
use App\Models\Article;
use App\Models\Image;
use App\Models\Keyword;
use App\Models\Project;
use App\Models\User;
use App\Services\ArticleImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    // Use DALL-E 3 for tests (mocked response format)
    config(['services.openai.image_model' => 'dall-e-3']);
});

describe('findImagePlaceholders', function () {
    it('finds placeholders with em dash separator', function () {
        $service = app(ArticleImageService::class);

        $content = 'Some text before [IMAGE: A cozy coffee shop interior – style: illustration] and after.';

        $placeholders = $service->findImagePlaceholders($content);

        expect($placeholders)->toHaveCount(1);
        expect($placeholders[0]['full_match'])->toBe('[IMAGE: A cozy coffee shop interior – style: illustration]');
        expect($placeholders[0]['description'])->toBe('A cozy coffee shop interior');
        expect($placeholders[0]['style'])->toBe('illustration');
    });

    it('finds placeholders with regular dash separator', function () {
        $service = app(ArticleImageService::class);

        $content = 'Text [IMAGE: Business meeting scene - style: cinematic] more text';

        $placeholders = $service->findImagePlaceholders($content);

        expect($placeholders)->toHaveCount(1);
        expect($placeholders[0]['description'])->toBe('Business meeting scene');
        expect($placeholders[0]['style'])->toBe('cinematic');
    });

    it('finds placeholders with comma separator', function () {
        $service = app(ArticleImageService::class);

        $content = 'Text [IMAGE: A clean Australian GST invoice template on a laptop screen, style: illustration] more text';

        $placeholders = $service->findImagePlaceholders($content);

        expect($placeholders)->toHaveCount(1);
        expect($placeholders[0]['description'])->toBe('A clean Australian GST invoice template on a laptop screen');
        expect($placeholders[0]['style'])->toBe('illustration');
    });

    it('finds multiple placeholders', function () {
        $service = app(ArticleImageService::class);

        $content = <<<'MD'
# Article Title

[IMAGE: First image description – style: sketch]

Some paragraph text here.

[IMAGE: Second image about technology - style: watercolor]

Another paragraph.

[IMAGE: Third image with people – style: photo]
MD;

        $placeholders = $service->findImagePlaceholders($content);

        expect($placeholders)->toHaveCount(3);
        expect($placeholders[0]['style'])->toBe('sketch');
        expect($placeholders[1]['style'])->toBe('watercolor');
        expect($placeholders[2]['style'])->toBe('photo');
    });

    it('returns empty array when no placeholders found', function () {
        $service = app(ArticleImageService::class);

        $content = 'This is a regular article without any image placeholders.';

        $placeholders = $service->findImagePlaceholders($content);

        expect($placeholders)->toBeEmpty();
    });

    it('handles various style types', function (string $style) {
        $service = app(ArticleImageService::class);

        $content = "[IMAGE: Test description – style: {$style}]";

        $placeholders = $service->findImagePlaceholders($content);

        expect($placeholders)->toHaveCount(1);
        expect($placeholders[0]['style'])->toBe(strtolower($style));
    })->with(['sketch', 'watercolor', 'illustration', 'cinematic', 'brand_text', 'photo', 'realistic', 'ILLUSTRATION', 'Photo']);

    it('handles extra whitespace in placeholders', function () {
        $service = app(ArticleImageService::class);

        $content = '[IMAGE:   Description with spaces   –   style:   illustration   ]';

        $placeholders = $service->findImagePlaceholders($content);

        expect($placeholders)->toHaveCount(1);
        expect($placeholders[0]['description'])->toBe('Description with spaces');
        expect($placeholders[0]['style'])->toBe('illustration');
    });

    it('finds INFOGRAPHIC placeholders', function () {
        $service = app(ArticleImageService::class);

        $content = '[INFOGRAPHIC: Workflow diagram showing the invoice lifecycle from "Client agreement" to "Invoice sent" to "Reminders" to "Payment received" to "Reconciliation & reporting".]';

        $placeholders = $service->findImagePlaceholders($content);

        expect($placeholders)->toHaveCount(1);
        expect($placeholders[0]['full_match'])->toBe($content);
        expect($placeholders[0]['description'])->toContain('Infographic:');
        expect($placeholders[0]['description'])->toContain('Workflow diagram');
        expect($placeholders[0]['style'])->toBe('illustration');
        expect($placeholders[0]['type'])->toBe('infographic');
    });

    it('finds DIAGRAM placeholders', function () {
        $service = app(ArticleImageService::class);

        $content = '[DIAGRAM: Database schema showing relationships between users, orders, and products tables]';

        $placeholders = $service->findImagePlaceholders($content);

        expect($placeholders)->toHaveCount(1);
        expect($placeholders[0]['description'])->toContain('Diagram:');
        expect($placeholders[0]['style'])->toBe('illustration');
        expect($placeholders[0]['type'])->toBe('diagram');
    });

    it('finds FLOWCHART placeholders', function () {
        $service = app(ArticleImageService::class);

        $content = '[FLOWCHART: Decision tree for choosing the right database]';

        $placeholders = $service->findImagePlaceholders($content);

        expect($placeholders)->toHaveCount(1);
        expect($placeholders[0]['type'])->toBe('flowchart');
        expect($placeholders[0]['style'])->toBe('illustration');
    });

    it('finds CHART and GRAPH placeholders', function () {
        $service = app(ArticleImageService::class);

        $content = <<<'MD'
[CHART: Bar chart comparing monthly revenue]

[GRAPH: Line graph showing user growth over time]
MD;

        $placeholders = $service->findImagePlaceholders($content);

        expect($placeholders)->toHaveCount(2);
        expect($placeholders[0]['type'])->toBe('chart');
        expect($placeholders[1]['type'])->toBe('graph');
    });

    it('finds SCREENSHOT and MOCKUP placeholders with realistic style', function () {
        $service = app(ArticleImageService::class);

        $content = <<<'MD'
[SCREENSHOT: Dashboard showing analytics metrics]

[MOCKUP: Mobile app login screen]
MD;

        $placeholders = $service->findImagePlaceholders($content);

        expect($placeholders)->toHaveCount(2);
        expect($placeholders[0]['type'])->toBe('screenshot');
        expect($placeholders[0]['style'])->toBe('realistic');
        expect($placeholders[1]['type'])->toBe('mockup');
        expect($placeholders[1]['style'])->toBe('realistic');
    });

    it('finds both IMAGE and visual asset placeholders in same content', function () {
        $service = app(ArticleImageService::class);

        $content = <<<'MD'
# Article

[IMAGE: Hero image of a team working – style: cinematic]

## Section 1

[INFOGRAPHIC: Steps to complete the process]

## Section 2

[DIAGRAM: System architecture overview]
MD;

        $placeholders = $service->findImagePlaceholders($content);

        expect($placeholders)->toHaveCount(3);
        expect($placeholders[0]['type'])->toBe('image');
        expect($placeholders[0]['style'])->toBe('cinematic');
        expect($placeholders[1]['type'])->toBe('infographic');
        expect($placeholders[2]['type'])->toBe('diagram');
    });

    it('handles visual asset placeholders case-insensitively', function () {
        $service = app(ArticleImageService::class);

        $content = <<<'MD'
[infographic: lowercase test]
[INFOGRAPHIC: UPPERCASE test]
[Infographic: Mixed case test]
MD;

        $placeholders = $service->findImagePlaceholders($content);

        expect($placeholders)->toHaveCount(3);
        foreach ($placeholders as $placeholder) {
            expect($placeholder['type'])->toBe('infographic');
        }
    });
});

describe('processArticleImages', function () {
    it('returns zero images when no placeholders exist', function () {
        $user = User::factory()->create();
        $aiProvider = AiProvider::factory()->create([
            'user_id' => $user->id,
            'provider' => 'openai',
            'is_active' => true,
        ]);
        $project = Project::factory()->create(['user_id' => $user->id]);
        $article = Article::factory()->create([
            'project_id' => $project->id,
            'content' => 'Regular content without placeholders.',
            'content_markdown' => 'Regular content without placeholders.',
        ]);

        $service = app(ArticleImageService::class);
        $result = $service->processArticleImages($article, $aiProvider);

        expect($result['images_generated'])->toBe(0);
        expect($result['errors'])->toBeEmpty();
    });

    it('processes single placeholder with openai', function () {
        $user = User::factory()->create();
        $aiProvider = AiProvider::factory()->create([
            'user_id' => $user->id,
            'provider' => 'openai',
            'api_key' => 'test-key',
            'is_active' => true,
        ]);
        $project = Project::factory()->create([
            'user_id' => $user->id,
            'brand_color' => '#3B82F6',
        ]);
        $keyword = Keyword::factory()->create(['project_id' => $project->id]);
        $article = Article::factory()->create([
            'project_id' => $project->id,
            'keyword_id' => $keyword->id,
            'content' => 'Text before [IMAGE: Coffee shop scene – style: illustration] text after.',
            'content_markdown' => 'Text before [IMAGE: Coffee shop scene – style: illustration] text after.',
        ]);

        $openaiResponse = Http::response([
            'data' => [
                [
                    'b64_json' => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
                ],
            ],
        ]);

        Http::fake([
            'api.openai.com/*' => $openaiResponse,
            '*' => $openaiResponse, // Catch-all for any other endpoints
        ]);

        $service = app(ArticleImageService::class);
        $result = $service->processArticleImages($article, $aiProvider);

        expect($result['images_generated'])->toBe(1);
        expect($result['errors'])->toBeEmpty();

        $article->refresh();
        expect($article->content_markdown)->not->toContain('[IMAGE:');
        expect($article->content_markdown)->toContain('![Coffee shop scene]');
    });

    it('processes multiple placeholders', function () {
        $user = User::factory()->create();
        $aiProvider = AiProvider::factory()->create([
            'user_id' => $user->id,
            'provider' => 'openai',
            'api_key' => 'test-key',
            'is_active' => true,
        ]);
        $project = Project::factory()->create(['user_id' => $user->id]);
        $keyword = Keyword::factory()->create(['project_id' => $project->id]);
        $article = Article::factory()->create([
            'project_id' => $project->id,
            'keyword_id' => $keyword->id,
            'content' => '[IMAGE: First – style: sketch] middle [IMAGE: Second – style: watercolor]',
            'content_markdown' => '[IMAGE: First – style: sketch] middle [IMAGE: Second – style: watercolor]',
        ]);

        $openaiResponse = Http::response([
            'data' => [
                [
                    'b64_json' => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
                ],
            ],
        ]);

        Http::fake([
            'api.openai.com/*' => $openaiResponse,
            '*' => $openaiResponse, // Catch-all for any other endpoints
        ]);

        $service = app(ArticleImageService::class);
        $result = $service->processArticleImages($article, $aiProvider);

        expect($result['images_generated'])->toBe(2);
        expect($result['errors'])->toBeEmpty();
        expect(Image::where('article_id', $article->id)->where('type', 'inline')->count())->toBe(2);
    });

    it('uses placeholder fallback for non-image providers', function () {
        $user = User::factory()->create();
        $aiProvider = AiProvider::factory()->create([
            'user_id' => $user->id,
            'provider' => 'anthropic',
            'is_active' => true,
        ]);
        $project = Project::factory()->create(['user_id' => $user->id]);
        $article = Article::factory()->create([
            'project_id' => $project->id,
            'content' => '[IMAGE: Test description – style: illustration]',
            'content_markdown' => '[IMAGE: Test description – style: illustration]',
        ]);

        $service = app(ArticleImageService::class);
        $result = $service->processArticleImages($article, $aiProvider);

        expect($result['images_generated'])->toBe(1);
        expect($result['errors'])->toBeEmpty();

        $image = Image::where('article_id', $article->id)->first();
        expect($image)->not->toBeNull();
        expect($image->type)->toBe('inline');
    });

    it('handles api errors gracefully', function () {
        $user = User::factory()->create();
        $aiProvider = AiProvider::factory()->create([
            'user_id' => $user->id,
            'provider' => 'openai',
            'api_key' => 'test-key',
            'is_active' => true,
        ]);
        $project = Project::factory()->create(['user_id' => $user->id]);
        $article = Article::factory()->create([
            'project_id' => $project->id,
            'content' => '[IMAGE: Test – style: illustration]',
            'content_markdown' => '[IMAGE: Test – style: illustration]',
        ]);

        Http::fake([
            'api.openai.com/*' => Http::response(['error' => 'Rate limited'], 429),
        ]);

        $service = app(ArticleImageService::class);
        $result = $service->processArticleImages($article, $aiProvider);

        expect($result['images_generated'])->toBe(0);
        expect($result['errors'])->not->toBeEmpty();
        expect($result['errors'][0]['placeholder'])->toContain('[IMAGE:');
    });

    it('creates image records with correct metadata', function () {
        $user = User::factory()->create();
        $aiProvider = AiProvider::factory()->create([
            'user_id' => $user->id,
            'provider' => 'anthropic',
            'is_active' => true,
        ]);
        $project = Project::factory()->create([
            'user_id' => $user->id,
            'brand_color' => '#10B981',
        ]);
        $article = Article::factory()->create([
            'project_id' => $project->id,
            'content' => '[IMAGE: A beautiful sunset – style: watercolor]',
            'content_markdown' => '[IMAGE: A beautiful sunset – style: watercolor]',
        ]);

        $service = app(ArticleImageService::class);
        $service->processArticleImages($article, $aiProvider);

        $image = Image::where('article_id', $article->id)->first();

        expect($image->project_id)->toBe($project->id);
        expect($image->type)->toBe('inline');
        expect($image->source)->toBe('ai_generated');
        expect($image->alt_text)->toBe('A beautiful sunset');
        expect($image->metadata['style'])->toBe('watercolor');
        expect($image->metadata['original_description'])->toBe('A beautiful sunset');
        expect($image->metadata['brand_color'])->toBe('#10B981');
    });
});

describe('generateInlineImage', function () {
    it('defaults to illustration style for invalid styles', function () {
        $user = User::factory()->create();
        $aiProvider = AiProvider::factory()->create([
            'user_id' => $user->id,
            'provider' => 'anthropic',
            'is_active' => true,
        ]);
        $project = Project::factory()->create(['user_id' => $user->id]);
        $article = Article::factory()->create(['project_id' => $project->id]);

        $service = app(ArticleImageService::class);
        $image = $service->generateInlineImage('Test description', 'invalid_style', $article, $aiProvider);

        expect($image->metadata['style'])->toBe('illustration');
    });

    it('stores images with correct dimensions', function () {
        $user = User::factory()->create();
        $aiProvider = AiProvider::factory()->create([
            'user_id' => $user->id,
            'provider' => 'anthropic',
            'is_active' => true,
        ]);
        $project = Project::factory()->create(['user_id' => $user->id]);
        $article = Article::factory()->create(['project_id' => $project->id]);

        $service = app(ArticleImageService::class);
        $image = $service->generateInlineImage('Test', 'illustration', $article, $aiProvider);

        expect($image->width)->toBe(1200);
        expect($image->height)->toBe(800);
    });

    it('generates images with gemini provider', function () {
        $user = User::factory()->create();
        $aiProvider = AiProvider::factory()->create([
            'user_id' => $user->id,
            'provider' => 'gemini',
            'api_key' => 'test-key',
            'is_active' => true,
        ]);
        $project = Project::factory()->create(['user_id' => $user->id]);
        $keyword = Keyword::factory()->create(['project_id' => $project->id]);
        $article = Article::factory()->create([
            'project_id' => $project->id,
            'keyword_id' => $keyword->id,
        ]);

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
            '*' => $geminiResponse,
        ]);

        $service = app(ArticleImageService::class);
        $image = $service->generateInlineImage('Tech workspace', 'illustration', $article, $aiProvider);

        expect($image)->toBeInstanceOf(Image::class);
        expect($image->type)->toBe('inline');
    });
});

describe('style prompts', function () {
    it('builds prompts for all valid styles', function (string $style) {
        $user = User::factory()->create();
        $aiProvider = AiProvider::factory()->create([
            'user_id' => $user->id,
            'provider' => 'anthropic',
            'is_active' => true,
        ]);
        $project = Project::factory()->create([
            'user_id' => $user->id,
            'brand_color' => '#3B82F6',
        ]);
        $article = Article::factory()->create(['project_id' => $project->id]);

        $service = app(ArticleImageService::class);
        $image = $service->generateInlineImage('Test description', $style, $article, $aiProvider);

        expect($image)->toBeInstanceOf(Image::class);
        expect($image->metadata['style'])->toBe($style);
        expect($image->prompt)->not->toBeEmpty();
    })->with(['sketch', 'watercolor', 'illustration', 'cinematic', 'brand_text', 'photo', 'realistic']);
});
