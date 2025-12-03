<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectPage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SitemapService
{
    protected array $blogPatterns = [
        '/blog/',
        '/posts/',
        '/articles/',
        '/news/',
        '/resources/',
    ];

    protected array $productPatterns = [
        '/product/',
        '/products/',
        '/shop/',
        '/store/',
        '/item/',
    ];

    protected array $servicePatterns = [
        '/service/',
        '/services/',
        '/solutions/',
    ];

    protected array $landingPatterns = [
        '/landing/',
        '/lp/',
        '/campaign/',
    ];

    public function fetchAndParseSitemap(Project $project): array
    {
        $sitemapUrl = $project->sitemap_url;

        if (! $sitemapUrl) {
            throw new \InvalidArgumentException('Project does not have a sitemap URL configured.');
        }

        try {
            $response = Http::timeout(30)->get($sitemapUrl);

            if (! $response->successful()) {
                throw new \RuntimeException("Failed to fetch sitemap: HTTP {$response->status()}");
            }

            $xml = $response->body();
            $pages = $this->parseSitemapXml($xml);

            $project->update(['sitemap_last_fetched_at' => now()]);

            return $pages;

        } catch (\Exception $e) {
            Log::error('Sitemap fetch failed', [
                'project_id' => $project->id,
                'sitemap_url' => $sitemapUrl,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function parseSitemapXml(string $xml): array
    {
        $pages = [];

        try {
            $doc = new \SimpleXMLElement($xml);

            // Handle sitemap index (multiple sitemaps)
            if (isset($doc->sitemap)) {
                foreach ($doc->sitemap as $sitemap) {
                    $subSitemapUrl = (string) $sitemap->loc;
                    $subPages = $this->fetchAndParseSitemapFromUrl($subSitemapUrl);
                    $pages = array_merge($pages, $subPages);
                }
            }

            // Handle regular sitemap with URLs
            if (isset($doc->url)) {
                foreach ($doc->url as $url) {
                    $pages[] = [
                        'url' => (string) $url->loc,
                        'lastmod' => isset($url->lastmod) ? (string) $url->lastmod : null,
                        'priority' => isset($url->priority) ? (float) $url->priority : 0.5,
                    ];
                }
            }

        } catch (\Exception $e) {
            Log::error('Sitemap XML parsing failed', ['error' => $e->getMessage()]);
            throw new \RuntimeException('Failed to parse sitemap XML: '.$e->getMessage());
        }

        return $pages;
    }

    protected function fetchAndParseSitemapFromUrl(string $url): array
    {
        try {
            $response = Http::timeout(30)->get($url);

            if ($response->successful()) {
                return $this->parseSitemapXml($response->body());
            }
        } catch (\Exception $e) {
            Log::warning('Failed to fetch sub-sitemap', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
        }

        return [];
    }

    public function syncPagesToProject(Project $project, array $pages): array
    {
        $stats = [
            'created' => 0,
            'updated' => 0,
            'total' => count($pages),
        ];

        foreach ($pages as $pageData) {
            $url = $pageData['url'];
            $pageType = $this->classifyPageType($url);
            $keywords = $this->extractKeywordsFromUrl($url);
            $title = $this->extractTitleFromUrl($url);

            $existingPage = ProjectPage::where('project_id', $project->id)
                ->where('url', $url)
                ->first();

            if ($existingPage) {
                $existingPage->update([
                    'priority' => $pageData['priority'] ?? 0.5,
                    'last_modified_at' => $pageData['lastmod'] ? new \DateTime($pageData['lastmod']) : null,
                    'last_fetched_at' => now(),
                ]);
                $stats['updated']++;
            } else {
                ProjectPage::create([
                    'project_id' => $project->id,
                    'url' => $url,
                    'title' => $title,
                    'page_type' => $pageType,
                    'keywords' => $keywords,
                    'priority' => $pageData['priority'] ?? 0.5,
                    'link_count' => 0,
                    'is_active' => true,
                    'last_modified_at' => $pageData['lastmod'] ? new \DateTime($pageData['lastmod']) : null,
                    'last_fetched_at' => now(),
                ]);
                $stats['created']++;
            }
        }

        return $stats;
    }

    public function classifyPageType(string $url): string
    {
        $urlLower = strtolower($url);

        foreach ($this->blogPatterns as $pattern) {
            if (Str::contains($urlLower, $pattern)) {
                return 'blog';
            }
        }

        foreach ($this->productPatterns as $pattern) {
            if (Str::contains($urlLower, $pattern)) {
                return 'product';
            }
        }

        foreach ($this->servicePatterns as $pattern) {
            if (Str::contains($urlLower, $pattern)) {
                return 'service';
            }
        }

        foreach ($this->landingPatterns as $pattern) {
            if (Str::contains($urlLower, $pattern)) {
                return 'landing';
            }
        }

        return 'other';
    }

    public function extractKeywordsFromUrl(string $url): array
    {
        $parsed = parse_url($url);
        $path = $parsed['path'] ?? '';

        // Remove file extensions
        $path = preg_replace('/\.[a-zA-Z]{2,4}$/', '', $path);

        // Split by common delimiters
        $segments = preg_split('/[\/\-_]/', $path);

        // Filter and clean keywords
        $keywords = collect($segments)
            ->map(fn ($segment) => trim($segment))
            ->filter(fn ($segment) => strlen($segment) > 2)
            ->filter(fn ($segment) => ! is_numeric($segment))
            ->filter(fn ($segment) => ! in_array(strtolower($segment), [
                'www', 'http', 'https', 'com', 'org', 'net', 'html', 'php', 'aspx',
                'the', 'and', 'for', 'with', 'this', 'that',
            ]))
            ->values()
            ->toArray();

        return array_slice($keywords, 0, 10);
    }

    public function extractTitleFromUrl(string $url): string
    {
        $parsed = parse_url($url);
        $path = $parsed['path'] ?? '';

        // Get the last path segment
        $segments = array_filter(explode('/', $path));
        $lastSegment = end($segments);

        if (! $lastSegment) {
            return '';
        }

        // Remove file extension
        $lastSegment = preg_replace('/\.[a-zA-Z]{2,4}$/', '', $lastSegment);

        // Convert hyphens/underscores to spaces and title case
        $title = str_replace(['-', '_'], ' ', $lastSegment);
        $title = Str::title($title);

        return $title;
    }

    public function getRelevantPagesForArticle(
        Project $project,
        string $articleContent,
        array $keywords,
        int $limit = 5
    ): Collection {
        // Get all active pages for this project
        $query = $project->pages()
            ->active();

        // Prioritize blog posts if configured
        if ($project->prioritize_blog_links) {
            $query->orderByRaw("CASE WHEN page_type = 'blog' THEN 0 ELSE 1 END");
        }

        // Prioritize least-linked pages for even distribution
        $query->orderBy('link_count', 'asc');

        // Get candidate pages
        $candidates = $query->get();

        // Score pages based on keyword relevance
        $scored = $candidates->map(function ($page) use ($keywords, $articleContent) {
            $score = $this->calculateRelevanceScore($page, $keywords, $articleContent);

            return [
                'page' => $page,
                'score' => $score,
            ];
        });

        // Sort by score and return top pages
        return $scored
            ->sortByDesc('score')
            ->take($limit)
            ->pluck('page');
    }

    protected function calculateRelevanceScore(ProjectPage $page, array $keywords, string $articleContent): float
    {
        $score = 0.0;

        // Base score from sitemap priority (scaled 0-30 points)
        // Priority 1.0 = 30 points, Priority 0.5 = 15 points, Priority 0.1 = 3 points
        $score += $page->priority * 30;

        // Bonus for high-priority pages (priority >= 0.8 gets extra boost)
        if ($page->priority >= 0.8) {
            $score += 15;
        }

        // Keyword matching
        $pageKeywords = $page->keywords ?? [];
        $pageTitle = strtolower($page->title ?? '');
        $pageUrl = strtolower($page->url);

        foreach ($keywords as $keyword) {
            $keywordLower = strtolower($keyword);

            // Check if keyword appears in page keywords
            foreach ($pageKeywords as $pageKeyword) {
                if (Str::contains(strtolower($pageKeyword), $keywordLower) ||
                    Str::contains($keywordLower, strtolower($pageKeyword))) {
                    $score += 20;
                }
            }

            // Check if keyword appears in page title
            if (Str::contains($pageTitle, $keywordLower)) {
                $score += 15;
            }

            // Check if keyword appears in URL
            if (Str::contains($pageUrl, $keywordLower)) {
                $score += 10;
            }
        }

        // Bonus for blog posts (often more relevant for internal linking)
        if ($page->page_type === 'blog') {
            $score += 5;
        }

        // Penalty for already heavily linked pages (for distribution)
        $score -= $page->link_count * 0.5;

        return max(0, $score);
    }

    public function incrementLinkCount(ProjectPage $page): void
    {
        $page->increment('link_count');
    }
}
