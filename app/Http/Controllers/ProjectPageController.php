<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectPage;
use App\Services\SitemapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectPageController extends Controller
{
    public function __construct(
        protected SitemapService $sitemapService
    ) {}

    public function index(Request $request, Project $project): Response
    {
        $this->authorize('view', $project);

        $query = $project->pages();

        // Filter by page type
        if ($request->has('page_type') && $request->page_type !== 'all') {
            $query->where('page_type', $request->page_type);
        }

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Search by URL or title
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('url', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%");
            });
        }

        $pages = $query
            ->orderBy('link_count', 'asc')
            ->orderBy('priority', 'desc')
            ->paginate(50)
            ->withQueryString();

        // Get stats
        $stats = [
            'total' => $project->pages()->count(),
            'active' => $project->pages()->active()->count(),
            'by_type' => [
                'blog' => $project->pages()->active()->byType('blog')->count(),
                'product' => $project->pages()->active()->byType('product')->count(),
                'service' => $project->pages()->active()->byType('service')->count(),
                'landing' => $project->pages()->active()->byType('landing')->count(),
                'other' => $project->pages()->active()->byType('other')->count(),
            ],
            'total_links_distributed' => $project->pages()->sum('link_count'),
            'last_fetched' => $project->sitemap_last_fetched_at?->toIso8601String(),
        ];

        return Inertia::render('Projects/Pages/Index', [
            'project' => $project,
            'pages' => $pages,
            'stats' => $stats,
            'filters' => [
                'search' => $request->input('search', ''),
                'page_type' => $request->input('page_type', 'all'),
                'is_active' => $request->input('is_active'),
            ],
            'pageTypes' => [
                ['value' => 'all', 'label' => 'All Types'],
                ['value' => 'blog', 'label' => 'Blog Posts'],
                ['value' => 'product', 'label' => 'Products'],
                ['value' => 'service', 'label' => 'Services'],
                ['value' => 'landing', 'label' => 'Landing Pages'],
                ['value' => 'other', 'label' => 'Other'],
            ],
        ]);
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'url' => 'required|url|max:2048',
            'title' => 'nullable|string|max:255',
            'page_type' => 'required|in:blog,product,service,landing,other',
            'priority' => 'nullable|numeric|min:0|max:1',
            'keywords' => 'nullable|array',
            'keywords.*' => 'string|max:100',
        ]);

        // Check if URL already exists for this project
        $existingPage = $project->pages()->where('url', $validated['url'])->first();
        if ($existingPage) {
            return response()->json([
                'success' => false,
                'message' => 'This URL already exists in your internal pages.',
            ], 422);
        }

        $page = $project->pages()->create([
            'url' => $validated['url'],
            'title' => $validated['title'] ?? $this->sitemapService->extractTitleFromUrl($validated['url']),
            'page_type' => $validated['page_type'],
            'priority' => $validated['priority'] ?? 0.5,
            'keywords' => $validated['keywords'] ?? $this->sitemapService->extractKeywordsFromUrl($validated['url']),
            'link_count' => 0,
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'page' => $page,
            'message' => 'Page added successfully.',
        ]);
    }

    public function sync(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        if (! $project->sitemap_url) {
            return response()->json([
                'success' => false,
                'message' => 'Please configure a sitemap URL first.',
            ], 422);
        }

        try {
            $pages = $this->sitemapService->fetchAndParseSitemap($project);
            $stats = $this->sitemapService->syncPagesToProject($project, $pages);

            return response()->json([
                'success' => true,
                'message' => "Sitemap synced successfully. Created: {$stats['created']}, Updated: {$stats['updated']}",
                'stats' => $stats,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to sync sitemap: '.$e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, Project $project, ProjectPage $page): JsonResponse
    {
        $this->authorize('update', $project);

        // Ensure page belongs to project
        if ($page->project_id !== $project->id) {
            abort(404);
        }

        $validated = $request->validate([
            'url' => 'sometimes|url|max:2048',
            'title' => 'nullable|string|max:255',
            'page_type' => 'required|in:blog,product,service,landing,other',
            'priority' => 'nullable|numeric|min:0|max:1',
            'keywords' => 'nullable|array',
            'keywords.*' => 'string|max:100',
            'is_active' => 'boolean',
        ]);

        // If URL is being changed, check for duplicates
        if (isset($validated['url']) && $validated['url'] !== $page->url) {
            $existingPage = $project->pages()
                ->where('url', $validated['url'])
                ->where('id', '!=', $page->id)
                ->first();
            if ($existingPage) {
                return response()->json([
                    'success' => false,
                    'message' => 'This URL already exists in your internal pages.',
                ], 422);
            }
        }

        $page->update($validated);

        return response()->json([
            'success' => true,
            'page' => $page->fresh(),
        ]);
    }

    public function destroy(Request $request, Project $project, ProjectPage $page): JsonResponse
    {
        $this->authorize('update', $project);

        // Ensure page belongs to project
        if ($page->project_id !== $project->id) {
            abort(404);
        }

        $page->delete();

        return response()->json([
            'success' => true,
            'message' => 'Page removed from internal links.',
        ]);
    }

    public function stats(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $stats = [
            'total' => $project->pages()->count(),
            'active' => $project->pages()->active()->count(),
            'by_type' => [
                'blog' => $project->pages()->active()->byType('blog')->count(),
                'product' => $project->pages()->active()->byType('product')->count(),
                'service' => $project->pages()->active()->byType('service')->count(),
                'landing' => $project->pages()->active()->byType('landing')->count(),
                'other' => $project->pages()->active()->byType('other')->count(),
            ],
            'total_links_distributed' => $project->pages()->sum('link_count'),
            'last_fetched' => $project->sitemap_last_fetched_at?->toIso8601String(),
        ];

        return response()->json($stats);
    }
}
