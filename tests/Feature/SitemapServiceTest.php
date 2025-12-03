<?php

use App\Models\Project;
use App\Models\ProjectPage;
use App\Models\User;
use App\Services\SitemapService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->service = app(SitemapService::class);
    $this->user = User::factory()->create();
    $this->project = Project::factory()->for($this->user)->create([
        'sitemap_url' => 'https://example.com/sitemap.xml',
        'auto_internal_linking' => true,
        'prioritize_blog_links' => true,
        'internal_links_per_article' => 3,
    ]);
});

describe('classifyPageType', function () {
    it('classifies blog URLs correctly', function () {
        expect($this->service->classifyPageType('https://example.com/blog/my-post'))->toBe('blog');
        expect($this->service->classifyPageType('https://example.com/posts/article'))->toBe('blog');
        expect($this->service->classifyPageType('https://example.com/articles/news'))->toBe('blog');
        expect($this->service->classifyPageType('https://example.com/news/latest'))->toBe('blog');
    });

    it('classifies product URLs correctly', function () {
        expect($this->service->classifyPageType('https://example.com/products/item'))->toBe('product');
        expect($this->service->classifyPageType('https://example.com/shop/category'))->toBe('product');
        expect($this->service->classifyPageType('https://example.com/store/item'))->toBe('product');
    });

    it('classifies service URLs correctly', function () {
        expect($this->service->classifyPageType('https://example.com/services/consulting'))->toBe('service');
        expect($this->service->classifyPageType('https://example.com/solutions/enterprise'))->toBe('service');
    });

    it('classifies landing URLs correctly', function () {
        expect($this->service->classifyPageType('https://example.com/landing/promo'))->toBe('landing');
        expect($this->service->classifyPageType('https://example.com/lp/offer'))->toBe('landing');
    });

    it('returns other for unrecognized URLs', function () {
        expect($this->service->classifyPageType('https://example.com/about-us'))->toBe('other');
        expect($this->service->classifyPageType('https://example.com/contact'))->toBe('other');
    });
});

describe('extractKeywordsFromUrl', function () {
    it('extracts keywords from URL paths', function () {
        $keywords = $this->service->extractKeywordsFromUrl('https://example.com/blog/how-to-write-seo-content');
        expect($keywords)->toContain('blog');
        expect($keywords)->toContain('how');
        expect($keywords)->toContain('write');
        expect($keywords)->toContain('seo');
        expect($keywords)->toContain('content');
    });

    it('filters out common stop words and short segments', function () {
        $keywords = $this->service->extractKeywordsFromUrl('https://example.com/the-best-way-to-do-it');
        expect($keywords)->not->toContain('the');
        expect($keywords)->not->toContain('to');
    });

    it('limits keywords to 10', function () {
        $keywords = $this->service->extractKeywordsFromUrl(
            'https://example.com/very/long/path/with/many/segments/that/exceeds/ten/keywords/here'
        );
        expect(count($keywords))->toBeLessThanOrEqual(10);
    });
});

describe('extractTitleFromUrl', function () {
    it('extracts title from last URL segment', function () {
        $title = $this->service->extractTitleFromUrl('https://example.com/blog/my-awesome-post');
        expect($title)->toBe('My Awesome Post');
    });

    it('handles underscores', function () {
        $title = $this->service->extractTitleFromUrl('https://example.com/products/product_name');
        expect($title)->toBe('Product Name');
    });

    it('returns empty for root URLs', function () {
        $title = $this->service->extractTitleFromUrl('https://example.com/');
        expect($title)->toBe('');
    });
});

describe('parseSitemapXml', function () {
    it('parses standard sitemap URLs', function () {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url>
        <loc>https://example.com/blog/post-1</loc>
        <lastmod>2024-01-15</lastmod>
        <priority>0.8</priority>
    </url>
    <url>
        <loc>https://example.com/blog/post-2</loc>
        <priority>0.6</priority>
    </url>
</urlset>
XML;

        $pages = $this->service->parseSitemapXml($xml);

        expect($pages)->toHaveCount(2);
        expect($pages[0]['url'])->toBe('https://example.com/blog/post-1');
        expect($pages[0]['priority'])->toBe(0.8);
        expect($pages[0]['lastmod'])->toBe('2024-01-15');
        expect($pages[1]['priority'])->toBe(0.6);
    });
});

describe('syncPagesToProject', function () {
    it('creates new pages from sitemap data', function () {
        $pages = [
            ['url' => 'https://example.com/blog/post-1', 'priority' => 0.8, 'lastmod' => '2024-01-15'],
            ['url' => 'https://example.com/products/item-1', 'priority' => 0.6, 'lastmod' => null],
        ];

        $stats = $this->service->syncPagesToProject($this->project, $pages);

        expect($stats['created'])->toBe(2);
        expect($stats['updated'])->toBe(0);
        expect($this->project->pages()->count())->toBe(2);

        $blogPage = $this->project->pages()->where('url', 'https://example.com/blog/post-1')->first();
        expect($blogPage->page_type)->toBe('blog');
        expect($blogPage->priority)->toBe('0.80');
    });

    it('updates existing pages', function () {
        ProjectPage::factory()->for($this->project)->create([
            'url' => 'https://example.com/blog/post-1',
            'priority' => 0.5,
        ]);

        $pages = [
            ['url' => 'https://example.com/blog/post-1', 'priority' => 0.9, 'lastmod' => '2024-01-20'],
        ];

        $stats = $this->service->syncPagesToProject($this->project, $pages);

        expect($stats['created'])->toBe(0);
        expect($stats['updated'])->toBe(1);

        $page = $this->project->pages()->first();
        expect($page->priority)->toBe('0.90');
    });
});

describe('getRelevantPagesForArticle', function () {
    beforeEach(function () {
        // Create some test pages
        ProjectPage::factory()->for($this->project)->blog()->create([
            'title' => 'SEO Best Practices',
            'keywords' => ['seo', 'best', 'practices'],
            'link_count' => 0,
        ]);
        ProjectPage::factory()->for($this->project)->blog()->create([
            'title' => 'Content Marketing Guide',
            'keywords' => ['content', 'marketing', 'guide'],
            'link_count' => 5,
        ]);
        ProjectPage::factory()->for($this->project)->product()->create([
            'title' => 'SEO Tool',
            'keywords' => ['seo', 'tool'],
            'link_count' => 2,
        ]);
    });

    it('returns relevant pages based on keywords', function () {
        $pages = $this->service->getRelevantPagesForArticle(
            $this->project,
            'Article about SEO optimization',
            ['seo', 'optimization'],
            3
        );

        expect($pages)->toHaveCount(3);
        // SEO pages should rank higher
        expect($pages->first()->title)->toContain('SEO');
    });

    it('prioritizes blog posts when configured', function () {
        $pages = $this->service->getRelevantPagesForArticle(
            $this->project,
            'General article',
            ['general'],
            3
        );

        // Blog posts should come first when prioritize_blog_links is true
        $blogCount = $pages->filter(fn ($p) => $p->page_type === 'blog')->count();
        expect($blogCount)->toBeGreaterThanOrEqual(1);
    });

    it('respects limit parameter', function () {
        $pages = $this->service->getRelevantPagesForArticle(
            $this->project,
            'Test article',
            ['test'],
            2
        );

        expect($pages)->toHaveCount(2);
    });
});

describe('fetchAndParseSitemap', function () {
    it('fetches and parses sitemap from URL', function () {
        $sitemapXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url>
        <loc>https://example.com/page-1</loc>
        <priority>0.8</priority>
    </url>
</urlset>
XML;

        Http::fake([
            'https://example.com/sitemap.xml' => Http::response($sitemapXml, 200),
        ]);

        $pages = $this->service->fetchAndParseSitemap($this->project);

        expect($pages)->toHaveCount(1);
        expect($pages[0]['url'])->toBe('https://example.com/page-1');
        expect($this->project->fresh()->sitemap_last_fetched_at)->not->toBeNull();
    });

    it('throws exception when sitemap URL is not configured', function () {
        $project = Project::factory()->for($this->user)->create(['sitemap_url' => null]);

        expect(fn () => $this->service->fetchAndParseSitemap($project))
            ->toThrow(\InvalidArgumentException::class);
    });

    it('throws exception on HTTP error', function () {
        Http::fake([
            'https://example.com/sitemap.xml' => Http::response('Not Found', 404),
        ]);

        expect(fn () => $this->service->fetchAndParseSitemap($this->project))
            ->toThrow(\RuntimeException::class);
    });
});

describe('incrementLinkCount', function () {
    it('increments the link count for a page', function () {
        $page = ProjectPage::factory()->for($this->project)->create(['link_count' => 5]);

        $this->service->incrementLinkCount($page);

        expect($page->fresh()->link_count)->toBe(6);
    });
});

describe('getRelevantPagesForArticle respects active status', function () {
    it('excludes inactive pages from results', function () {
        // Create an active page with high priority
        ProjectPage::factory()->for($this->project)->create([
            'title' => 'Active High Priority',
            'priority' => 1.0,
            'is_active' => true,
            'link_count' => 0,
        ]);

        // Create an inactive page with high priority
        ProjectPage::factory()->for($this->project)->create([
            'title' => 'Inactive High Priority',
            'priority' => 1.0,
            'is_active' => false,
            'link_count' => 0,
        ]);

        $pages = $this->service->getRelevantPagesForArticle(
            $this->project,
            'Test article',
            ['test'],
            10
        );

        // Should only return the active page
        expect($pages->pluck('title')->toArray())->toContain('Active High Priority');
        expect($pages->pluck('title')->toArray())->not->toContain('Inactive High Priority');
    });

    it('does not include manually deactivated pages even with keyword match', function () {
        ProjectPage::factory()->for($this->project)->create([
            'title' => 'SEO Guide Active',
            'keywords' => ['seo', 'guide'],
            'priority' => 0.8,
            'is_active' => true,
            'link_count' => 0,
        ]);

        ProjectPage::factory()->for($this->project)->create([
            'title' => 'SEO Tutorial Inactive',
            'keywords' => ['seo', 'tutorial'],
            'priority' => 0.9,
            'is_active' => false,
            'link_count' => 0,
        ]);

        $pages = $this->service->getRelevantPagesForArticle(
            $this->project,
            'Article about SEO',
            ['seo'],
            10
        );

        $titles = $pages->pluck('title')->toArray();
        expect($titles)->toContain('SEO Guide Active');
        expect($titles)->not->toContain('SEO Tutorial Inactive');
    });
});

describe('getRelevantPagesForArticle prioritizes by sitemap priority', function () {
    it('ranks high-priority pages higher than low-priority pages', function () {
        // Low priority page
        ProjectPage::factory()->for($this->project)->create([
            'title' => 'Low Priority Page',
            'priority' => 0.3,
            'is_active' => true,
            'link_count' => 0,
            'keywords' => [],
        ]);

        // High priority page
        ProjectPage::factory()->for($this->project)->create([
            'title' => 'High Priority Page',
            'priority' => 0.9,
            'is_active' => true,
            'link_count' => 0,
            'keywords' => [],
        ]);

        // Medium priority page
        ProjectPage::factory()->for($this->project)->create([
            'title' => 'Medium Priority Page',
            'priority' => 0.5,
            'is_active' => true,
            'link_count' => 0,
            'keywords' => [],
        ]);

        $pages = $this->service->getRelevantPagesForArticle(
            $this->project,
            'Generic article content',
            ['generic'],
            3
        );

        $titles = $pages->pluck('title')->toArray();

        // High priority should be first
        expect($titles[0])->toBe('High Priority Page');
    });

    it('gives extra boost to pages with priority >= 0.8', function () {
        // Page with priority 0.79 (no boost)
        ProjectPage::factory()->for($this->project)->create([
            'title' => 'Just Under Threshold',
            'priority' => 0.79,
            'is_active' => true,
            'link_count' => 0,
            'keywords' => [],
        ]);

        // Page with priority 0.8 (gets boost)
        ProjectPage::factory()->for($this->project)->create([
            'title' => 'At Threshold',
            'priority' => 0.8,
            'is_active' => true,
            'link_count' => 0,
            'keywords' => [],
        ]);

        $pages = $this->service->getRelevantPagesForArticle(
            $this->project,
            'Test article',
            ['test'],
            2
        );

        $titles = $pages->pluck('title')->toArray();

        // Page at threshold should rank higher due to the bonus
        expect($titles[0])->toBe('At Threshold');
    });

    it('balances priority with keyword relevance', function () {
        // Low priority but exact keyword match
        ProjectPage::factory()->for($this->project)->create([
            'title' => 'SEO Best Practices',
            'url' => 'https://example.com/seo-guide',
            'priority' => 0.3,
            'is_active' => true,
            'link_count' => 0,
            'keywords' => ['seo', 'best', 'practices'],
        ]);

        // High priority but no keyword match
        ProjectPage::factory()->for($this->project)->create([
            'title' => 'About Us',
            'url' => 'https://example.com/about',
            'priority' => 1.0,
            'is_active' => true,
            'link_count' => 0,
            'keywords' => ['company', 'about'],
        ]);

        $pages = $this->service->getRelevantPagesForArticle(
            $this->project,
            'Article about SEO optimization',
            ['seo', 'optimization'],
            2
        );

        $titles = $pages->pluck('title')->toArray();

        // Both factors matter - the SEO page should rank well due to keyword match
        // even though priority is lower
        expect($titles)->toContain('SEO Best Practices');
    });
});
