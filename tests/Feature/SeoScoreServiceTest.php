<?php

use App\Models\Article;
use App\Models\Keyword;
use App\Models\Project;
use App\Services\SeoScoreService;

beforeEach(function () {
    $this->seoScoreService = new SeoScoreService;
});

test('calculate returns correct structure', function () {
    $article = Article::factory()->create([
        'title' => 'Test Article Title',
        'content_markdown' => '## Heading\n\nSome content here.',
    ]);

    $result = $this->seoScoreService->calculate($article);

    expect($result)->toHaveKeys(['score', 'breakdown'])
        ->and($result['breakdown'])->toHaveKeys([
            'keyword_optimization',
            'content_structure',
            'content_length',
            'meta_quality',
            'enrichment',
        ]);

    foreach ($result['breakdown'] as $category) {
        expect($category)->toHaveKeys(['score', 'max', 'details']);
    }
});

test('score is normalized to 0-100', function () {
    $article = Article::factory()->create();

    $result = $this->seoScoreService->calculate($article);

    expect($result['score'])->toBeGreaterThanOrEqual(0)
        ->and($result['score'])->toBeLessThanOrEqual(100);
});

describe('keyword optimization scoring', function () {
    test('returns zero when no keyword is associated', function () {
        $article = Article::factory()->withoutKeyword()->create([
            'title' => 'Test Title',
            'content_markdown' => 'Some content without keyword.',
        ]);

        $result = $this->seoScoreService->calculate($article);

        expect($result['breakdown']['keyword_optimization']['score'])->toBe(0)
            ->and($result['breakdown']['keyword_optimization']['details'])->toHaveKey('no_keyword');
    });

    test('scores keyword in title', function () {
        $project = Project::factory()->create();
        $keyword = Keyword::factory()->create([
            'project_id' => $project->id,
            'keyword' => 'best coffee',
        ]);
        $article = Article::factory()->create([
            'project_id' => $project->id,
            'keyword_id' => $keyword->id,
            'title' => 'The Best Coffee Brewing Methods',
            'meta_description' => 'Learn about brewing.',
            'content_markdown' => 'Some content here.',
        ]);

        $result = $this->seoScoreService->calculate($article);

        expect($result['breakdown']['keyword_optimization']['details']['keyword_in_title'])->toBeTrue();
    });

    test('scores keyword in meta description', function () {
        $project = Project::factory()->create();
        $keyword = Keyword::factory()->create([
            'project_id' => $project->id,
            'keyword' => 'coffee beans',
        ]);
        $article = Article::factory()->create([
            'project_id' => $project->id,
            'keyword_id' => $keyword->id,
            'title' => 'Brewing Guide',
            'meta_description' => 'Learn about the best coffee beans for your morning brew.',
            'content_markdown' => 'Content here.',
        ]);

        $result = $this->seoScoreService->calculate($article);

        expect($result['breakdown']['keyword_optimization']['details']['keyword_in_meta'])->toBeTrue();
    });

    test('scores keyword in first 150 words', function () {
        $project = Project::factory()->create();
        $keyword = Keyword::factory()->create([
            'project_id' => $project->id,
            'keyword' => 'espresso machine',
        ]);
        $article = Article::factory()->create([
            'project_id' => $project->id,
            'keyword_id' => $keyword->id,
            'title' => 'Home Brewing Guide',
            'meta_description' => 'A guide to brewing.',
            'content_markdown' => 'If you want great coffee at home, an espresso machine is essential. '.str_repeat('word ', 200),
        ]);

        $result = $this->seoScoreService->calculate($article);

        expect($result['breakdown']['keyword_optimization']['details']['keyword_in_first_150_words'])->toBeTrue();
    });

    test('scores keyword in H2 heading', function () {
        $project = Project::factory()->create();
        $keyword = Keyword::factory()->create([
            'project_id' => $project->id,
            'keyword' => 'french press',
        ]);
        $article = Article::factory()->create([
            'project_id' => $project->id,
            'keyword_id' => $keyword->id,
            'title' => 'Brewing Guide',
            'meta_description' => 'A brewing guide.',
            'content_markdown' => "## How to Use a French Press\n\nSome content here.",
        ]);

        $result = $this->seoScoreService->calculate($article);

        expect($result['breakdown']['keyword_optimization']['details']['keyword_in_h2'])->toBeTrue();
    });

    test('calculates keyword density', function () {
        $project = Project::factory()->create();
        $keyword = Keyword::factory()->create([
            'project_id' => $project->id,
            'keyword' => 'coffee',
        ]);
        // 100 words with "coffee" appearing twice = 2% density
        $content = 'coffee '.str_repeat('word ', 48).'coffee '.str_repeat('word ', 48);
        $article = Article::factory()->create([
            'project_id' => $project->id,
            'keyword_id' => $keyword->id,
            'title' => 'Test',
            'content_markdown' => $content,
        ]);

        $result = $this->seoScoreService->calculate($article);

        expect($result['breakdown']['keyword_optimization']['details']['keyword_density'])->toBeGreaterThan(0);
    });
});

describe('smart keyword matching', function () {
    test('matches plural variations', function () {
        $project = Project::factory()->create();
        $keyword = Keyword::factory()->create([
            'project_id' => $project->id,
            'keyword' => 'freelance invoice australia',
        ]);
        $article = Article::factory()->create([
            'project_id' => $project->id,
            'keyword_id' => $keyword->id,
            'title' => 'Freelance Invoices in Australia: Templates & Best Practice',
            'meta_description' => 'Learn about freelance invoicing requirements in Australia.',
            'content_markdown' => "## Freelance Invoices in Australia\n\nContent here about Australian invoicing.",
        ]);

        $result = $this->seoScoreService->calculate($article);

        expect($result['breakdown']['keyword_optimization']['details']['keyword_in_title'])->toBeTrue()
            ->and($result['breakdown']['keyword_optimization']['details']['keyword_in_meta'])->toBeTrue()
            ->and($result['breakdown']['keyword_optimization']['details']['keyword_in_h2'])->toBeTrue();
    });

    test('matches with stop words inserted', function () {
        $project = Project::factory()->create();
        $keyword = Keyword::factory()->create([
            'project_id' => $project->id,
            'keyword' => 'best coffee beans',
        ]);
        $article = Article::factory()->create([
            'project_id' => $project->id,
            'keyword_id' => $keyword->id,
            'title' => 'The Best Coffee Beans for Your Morning Brew',
            'meta_description' => 'A guide.',
            'content_markdown' => 'Content here.',
        ]);

        $result = $this->seoScoreService->calculate($article);

        expect($result['breakdown']['keyword_optimization']['details']['keyword_in_title'])->toBeTrue();
    });

    test('matches verb form variations', function () {
        $project = Project::factory()->create();
        $keyword = Keyword::factory()->create([
            'project_id' => $project->id,
            'keyword' => 'invoice template',
        ]);
        $article = Article::factory()->create([
            'project_id' => $project->id,
            'keyword_id' => $keyword->id,
            'title' => 'Invoicing Templates for Small Business',
            'meta_description' => 'A guide.',
            'content_markdown' => 'Content here.',
        ]);

        $result = $this->seoScoreService->calculate($article);

        expect($result['breakdown']['keyword_optimization']['details']['keyword_in_title'])->toBeTrue();
    });

    test('matches geographic variations', function () {
        $project = Project::factory()->create();
        $keyword = Keyword::factory()->create([
            'project_id' => $project->id,
            'keyword' => 'tax australia',
        ]);
        $article = Article::factory()->create([
            'project_id' => $project->id,
            'keyword_id' => $keyword->id,
            'title' => 'Australian Tax Guide for Freelancers',
            'meta_description' => 'A guide.',
            'content_markdown' => 'Content here.',
        ]);

        $result = $this->seoScoreService->calculate($article);

        expect($result['breakdown']['keyword_optimization']['details']['keyword_in_title'])->toBeTrue();
    });

    test('still matches exact phrases', function () {
        $project = Project::factory()->create();
        $keyword = Keyword::factory()->create([
            'project_id' => $project->id,
            'keyword' => 'coffee brewing',
        ]);
        $article = Article::factory()->create([
            'project_id' => $project->id,
            'keyword_id' => $keyword->id,
            'title' => 'Master Coffee Brewing at Home',
            'meta_description' => 'A guide.',
            'content_markdown' => 'Content here.',
        ]);

        $result = $this->seoScoreService->calculate($article);

        expect($result['breakdown']['keyword_optimization']['details']['keyword_in_title'])->toBeTrue();
    });

    test('requires all significant words to be present', function () {
        $project = Project::factory()->create();
        $keyword = Keyword::factory()->create([
            'project_id' => $project->id,
            'keyword' => 'freelance invoice australia',
        ]);
        $article = Article::factory()->create([
            'project_id' => $project->id,
            'keyword_id' => $keyword->id,
            'title' => 'Freelance Guide', // Missing "invoice" and "australia"
            'meta_description' => 'A guide.',
            'content_markdown' => 'Content here.',
        ]);

        $result = $this->seoScoreService->calculate($article);

        expect($result['breakdown']['keyword_optimization']['details']['keyword_in_title'])->toBeFalse();
    });
});

describe('content structure scoring', function () {
    test('scores H2 headings count', function () {
        $article = Article::factory()->withoutKeyword()->create([
            'content_markdown' => "## Heading One\n\nContent\n\n## Heading Two\n\nContent\n\n## Heading Three\n\nContent",
        ]);

        $result = $this->seoScoreService->calculate($article);

        expect($result['breakdown']['content_structure']['details']['h2_count'])->toBe(3);
    });

    test('scores H3 headings count', function () {
        $article = Article::factory()->withoutKeyword()->create([
            'content_markdown' => "## Main\n\n### Sub One\n\nContent\n\n### Sub Two\n\nContent",
        ]);

        $result = $this->seoScoreService->calculate($article);

        expect($result['breakdown']['content_structure']['details']['h3_count'])->toBe(2);
    });

    test('detects bullet lists', function () {
        $article = Article::factory()->withoutKeyword()->create([
            'content_markdown' => "## Heading\n\n- Item one\n- Item two\n- Item three",
        ]);

        $result = $this->seoScoreService->calculate($article);

        expect($result['breakdown']['content_structure']['details']['has_lists'])->toBeTrue();
    });

    test('detects numbered lists', function () {
        $article = Article::factory()->withoutKeyword()->create([
            'content_markdown' => "## Heading\n\n1. First item\n2. Second item\n3. Third item",
        ]);

        $result = $this->seoScoreService->calculate($article);

        expect($result['breakdown']['content_structure']['details']['has_lists'])->toBeTrue();
    });

    test('detects tables', function () {
        $article = Article::factory()->withoutKeyword()->create([
            'content_markdown' => "## Heading\n\n| Column 1 | Column 2 |\n|----------|----------|\n| Data 1   | Data 2   |",
        ]);

        $result = $this->seoScoreService->calculate($article);

        expect($result['breakdown']['content_structure']['details']['has_tables'])->toBeTrue();
    });

    test('detects FAQ section', function () {
        $article = Article::factory()->withoutKeyword()->create([
            'content_markdown' => "## Heading\n\nContent\n\n## Frequently Asked Questions\n\nQ: Question?\nA: Answer.",
        ]);

        $result = $this->seoScoreService->calculate($article);

        expect($result['breakdown']['content_structure']['details']['has_faq'])->toBeTrue();
    });
});

describe('content length scoring', function () {
    test('scores based on word count', function () {
        $article = Article::factory()->withoutKeyword()->create([
            'word_count' => 1500,
            'content_markdown' => str_repeat('word ', 1500),
        ]);

        $result = $this->seoScoreService->calculate($article);

        expect($result['breakdown']['content_length']['details']['word_count'])->toBe(1500);
    });

    test('uses target word count from keyword when available', function () {
        $project = Project::factory()->create();
        $keyword = Keyword::factory()->create([
            'project_id' => $project->id,
            'keyword' => 'test keyword',
            'target_word_count' => 2000,
        ]);
        $article = Article::factory()->create([
            'project_id' => $project->id,
            'keyword_id' => $keyword->id,
            'word_count' => 1800,
        ]);

        $result = $this->seoScoreService->calculate($article);

        expect($result['breakdown']['content_length']['details']['target_word_count'])->toBe(2000);
    });
});

describe('meta quality scoring', function () {
    test('scores ideal title length 50-60 chars', function () {
        $article = Article::factory()->withoutKeyword()->create([
            'title' => str_repeat('a', 55), // 55 chars - ideal
        ]);

        $result = $this->seoScoreService->calculate($article);

        expect($result['breakdown']['meta_quality']['details']['title_length'])->toBe(55)
            ->and($result['breakdown']['meta_quality']['score'])->toBeGreaterThanOrEqual(6);
    });

    test('scores ideal meta description length 150-160 chars', function () {
        $article = Article::factory()->withoutKeyword()->create([
            'meta_description' => str_repeat('a', 155), // 155 chars - ideal
        ]);

        $result = $this->seoScoreService->calculate($article);

        expect($result['breakdown']['meta_quality']['details']['meta_description_length'])->toBe(155);
    });

    test('gives partial credit for acceptable title length', function () {
        $article = Article::factory()->withoutKeyword()->create([
            'title' => str_repeat('a', 45), // 45 chars - acceptable
        ]);

        $result = $this->seoScoreService->calculate($article);

        expect($result['breakdown']['meta_quality']['score'])->toBeGreaterThan(0);
    });
});

describe('enrichment scoring', function () {
    test('detects markdown images', function () {
        $article = Article::factory()->withoutKeyword()->create([
            'content_markdown' => "## Heading\n\n![Alt text](image.jpg)\n\nContent here.",
        ]);

        $result = $this->seoScoreService->calculate($article);

        expect($result['breakdown']['enrichment']['details']['has_images'])->toBeTrue();
    });

    test('detects image placeholders', function () {
        $article = Article::factory()->withoutKeyword()->create([
            'content_markdown' => "## Heading\n\n[IMAGE: A beautiful sunset]\n\nContent here.",
        ]);

        $result = $this->seoScoreService->calculate($article);

        expect($result['breakdown']['enrichment']['details']['has_images'])->toBeTrue();
    });

    test('detects markdown links', function () {
        $article = Article::factory()->withoutKeyword()->create([
            'content_markdown' => "## Heading\n\nCheck out [this resource](https://example.com) for more info.",
        ]);

        $result = $this->seoScoreService->calculate($article);

        expect($result['breakdown']['enrichment']['details']['has_links'])->toBeTrue();
    });
});

describe('calculateAndSave', function () {
    test('saves score to article', function () {
        $article = Article::factory()->create([
            'seo_score' => null,
            'generation_metadata' => null,
        ]);

        $score = $this->seoScoreService->calculateAndSave($article);

        $article->refresh();

        expect($score)->toBeGreaterThanOrEqual(0)
            ->and($score)->toBeLessThanOrEqual(100)
            ->and($article->seo_score)->toBe($score);
    });

    test('saves breakdown to generation_metadata', function () {
        $article = Article::factory()->create([
            'generation_metadata' => ['provider' => 'openai', 'model' => 'gpt-4o'],
        ]);

        $this->seoScoreService->calculateAndSave($article);

        $article->refresh();

        expect($article->generation_metadata)->toHaveKey('seo_breakdown')
            ->and($article->generation_metadata)->toHaveKey('provider')
            ->and($article->generation_metadata['provider'])->toBe('openai');
    });

    test('preserves existing generation_metadata', function () {
        $article = Article::factory()->create([
            'generation_metadata' => [
                'provider' => 'openai',
                'model' => 'gpt-4o',
                'tokens_used' => 5000,
            ],
        ]);

        $this->seoScoreService->calculateAndSave($article);

        $article->refresh();

        expect($article->generation_metadata['provider'])->toBe('openai')
            ->and($article->generation_metadata['model'])->toBe('gpt-4o')
            ->and($article->generation_metadata['tokens_used'])->toBe(5000)
            ->and($article->generation_metadata)->toHaveKey('seo_breakdown');
    });

    test('returns the calculated score', function () {
        $article = Article::factory()->create();

        $score = $this->seoScoreService->calculateAndSave($article);

        expect($score)->toBeInt()
            ->and($score)->toBeGreaterThanOrEqual(0)
            ->and($score)->toBeLessThanOrEqual(100);
    });
});

describe('high scoring article', function () {
    test('well-optimized article scores high', function () {
        $project = Project::factory()->create();
        $keyword = Keyword::factory()->create([
            'project_id' => $project->id,
            'keyword' => 'coffee brewing',
            'target_word_count' => 1500,
        ]);

        $content = <<<'MARKDOWN'
If you want to master the art of coffee brewing, you've come to the right place. This comprehensive guide covers everything you need to know about coffee brewing techniques.

## Why Coffee Brewing Matters

Understanding proper coffee brewing techniques is essential for any coffee enthusiast.

## Essential Coffee Brewing Equipment

| Equipment | Purpose | Price Range |
|-----------|---------|-------------|
| Grinder | Fresh grounds | $30-200 |
| Scale | Precision | $15-50 |
| Kettle | Water control | $25-150 |

## Step-by-Step Coffee Brewing Guide

### Choosing Your Beans

Select high-quality beans for the best results.

### Grinding Technique

- Use a burr grinder for consistency
- Grind just before brewing
- Match grind size to method

### Water Temperature

The ideal temperature is between 195-205°F.

## Frequently Asked Questions

**Q: How long should I brew coffee?**
A: It depends on your method, typically 3-5 minutes.

Check out [this brewing chart](https://example.com/chart) for more details.

![Coffee brewing setup](brewing.jpg)
MARKDOWN;

        $wordCount = str_word_count(strip_tags($content));

        $article = Article::factory()->create([
            'project_id' => $project->id,
            'keyword_id' => $keyword->id,
            'title' => 'Master Coffee Brewing: Complete Guide for Beginners',
            'meta_description' => 'Learn the art of coffee brewing with our comprehensive guide. Master techniques, equipment, and tips for the perfect cup every time.',
            'content_markdown' => $content,
            'word_count' => $wordCount,
        ]);

        $result = $this->seoScoreService->calculate($article);

        // Well-optimized content should score above 70
        expect($result['score'])->toBeGreaterThan(70);
    });
});
