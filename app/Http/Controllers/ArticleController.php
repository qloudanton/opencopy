<?php

namespace App\Http\Controllers;

use App\Jobs\EnrichArticleJob;
use App\Jobs\GenerateFeaturedImageJob;
use App\Jobs\PublishToIntegrationJob;
use App\Models\Article;
use App\Models\Integration;
use App\Models\Project;
use App\Services\ArticleImageService;
use App\Services\ArticleImprovementService;
use App\Services\Publishing\PublishingService;
use App\Services\SeoScoreService;
use App\Services\UsageTrackingService;
use App\Services\YouTubeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class ArticleController extends Controller
{
    public function index(Request $request, Project $project): Response
    {
        $this->authorize('view', $project);

        $articles = $project->articles()
            ->with(['keyword:id,keyword', 'scheduledContent:id,article_id,status'])
            ->withSum('usageLogs', 'estimated_cost')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return Inertia::render('Articles/Index', [
            'project' => $project,
            'articles' => $articles,
        ]);
    }

    public function show(Request $request, Project $project, Article $article, UsageTrackingService $usageTrackingService): Response
    {
        $this->authorize('view', $project);
        $this->ensureArticleBelongsToProject($article, $project);

        $article->load(['keyword', 'aiProvider']);

        // Get featured image
        $featuredImage = $article->images()->where('type', 'featured')->first();

        // Get cost breakdown
        $costBreakdown = $usageTrackingService->getArticleCostBreakdown($article);

        // Get project's active integrations
        $integrations = $project->integrations()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (Integration $integration) => [
                'id' => $integration->id,
                'type' => $integration->type,
                'name' => $integration->name,
                'has_credentials' => $integration->hasRequiredCredentials(),
            ]);

        // Get publication status for each integration
        $publications = $article->publications()
            ->with('integration:id,name,type')
            ->get()
            ->map(fn ($pub) => [
                'id' => $pub->id,
                'integration_id' => $pub->integration_id,
                'integration_name' => $pub->integration?->name,
                'integration_type' => $pub->integration?->type,
                'status' => $pub->status,
                'external_url' => $pub->external_url,
                'error_message' => $pub->error_message,
                'published_at' => $pub->published_at?->toIso8601String(),
                'created_at' => $pub->created_at->toIso8601String(),
            ]);

        // Get scheduled content if exists
        $scheduledContent = $article->scheduledContent;

        return Inertia::render('Articles/Show', [
            'project' => $project,
            'article' => $article,
            'featuredImage' => $featuredImage ? [
                'id' => $featuredImage->id,
                'url' => $featuredImage->url,
                'width' => $featuredImage->width,
                'height' => $featuredImage->height,
            ] : null,
            'costBreakdown' => $costBreakdown,
            'integrations' => $integrations,
            'publications' => $publications,
            'scheduledContent' => $scheduledContent ? [
                'id' => $scheduledContent->id,
                'status' => $scheduledContent->status->value,
                'scheduled_date' => $scheduledContent->scheduled_date?->toDateString(),
                'scheduled_time' => $scheduledContent->scheduled_time,
            ] : null,
        ]);
    }

    public function edit(Request $request, Project $project, Article $article, UsageTrackingService $usageTrackingService): Response
    {
        $this->authorize('update', $project);
        $this->ensureArticleBelongsToProject($article, $project);

        $article->load(['keyword']);

        // Get featured image
        $featuredImage = $article->images()->where('type', 'featured')->first();

        // Get cost breakdown
        $costBreakdown = $usageTrackingService->getArticleCostBreakdown($article);

        return Inertia::render('Articles/Edit', [
            'project' => $project->only(['id', 'name', 'image_style', 'brand_color']),
            'article' => $article,
            'featuredImage' => $featuredImage ? [
                'id' => $featuredImage->id,
                'url' => $featuredImage->url,
                'width' => $featuredImage->width,
                'height' => $featuredImage->height,
            ] : null,
            'costBreakdown' => $costBreakdown,
        ]);
    }

    public function update(Request $request, Project $project, Article $article, SeoScoreService $seoScoreService): RedirectResponse
    {
        $this->authorize('update', $project);
        $this->ensureArticleBelongsToProject($article, $project);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:articles,slug,'.$article->id],
            'meta_description' => 'nullable|string|max:255',
            'content' => 'required|string',
        ]);

        $validated['content_markdown'] = $validated['content'];
        $validated['word_count'] = str_word_count(strip_tags($validated['content']));
        $validated['reading_time_minutes'] = (int) ceil($validated['word_count'] / 200);

        $article->update($validated);

        // Recalculate SEO score after update
        $seoScoreService->calculateAndSave($article);

        return redirect()
            ->route('projects.articles.show', [$project, $article])
            ->with('success', 'Article updated successfully.');
    }

    public function destroy(Request $request, Project $project, Article $article): RedirectResponse
    {
        $this->authorize('delete', $project);
        $this->ensureArticleBelongsToProject($article, $project);

        $keywordId = $article->keyword_id;

        $article->delete();

        if ($keywordId) {
            return redirect()
                ->route('projects.keywords.show', [$project, $keywordId])
                ->with('success', 'Article deleted successfully.');
        }

        return redirect()
            ->route('projects.articles.index', $project)
            ->with('success', 'Article deleted successfully.');
    }

    public function recalculateSeo(Request $request, Project $project, Article $article, SeoScoreService $seoScoreService): JsonResponse
    {
        $this->authorize('view', $project);
        $this->ensureArticleBelongsToProject($article, $project);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'meta_description' => 'nullable|string|max:255',
            'content' => 'required|string',
        ]);

        // Temporarily set the values on the article for calculation
        $article->title = $validated['title'];
        $article->meta_description = $validated['meta_description'] ?? '';
        $article->content_markdown = $validated['content'];
        $article->content = $validated['content'];
        $article->word_count = str_word_count(strip_tags($validated['content']));

        // Calculate the score without saving
        $result = $seoScoreService->calculate($article);

        return response()->json([
            'score' => $result['score'],
            'breakdown' => $result['breakdown'],
        ]);
    }

    public function improve(Request $request, Project $project, Article $article, ArticleImprovementService $improvementService): JsonResponse
    {
        $this->authorize('update', $project);
        $this->ensureArticleBelongsToProject($article, $project);

        $validated = $request->validate([
            'improvement_type' => 'required|string|in:add_keyword_to_title,add_keyword_to_meta,add_faq_section,add_table,add_h2_headings,add_lists,optimize_title_length,optimize_meta_length,add_keyword_to_h2,add_keyword_to_intro',
        ]);

        // Get the user's default AI provider
        $aiProvider = $project->user
            ->aiProviders()
            ->where('is_active', true)
            ->where('is_default', true)
            ->first();

        if (! $aiProvider) {
            $aiProvider = $project->user
                ->aiProviders()
                ->where('is_active', true)
                ->first();
        }

        if (! $aiProvider) {
            return response()->json([
                'error' => 'No active AI provider configured. Please add an AI provider in settings.',
            ], 422);
        }

        try {
            $result = $improvementService->improve($article, $validated['improvement_type'], $aiProvider);

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to generate improvement: '.$e->getMessage(),
            ], 500);
        }
    }

    public function generateFeaturedImage(Request $request, Project $project, Article $article): JsonResponse
    {
        $this->authorize('update', $project);
        $this->ensureArticleBelongsToProject($article, $project);

        $validated = $request->validate([
            'style' => 'nullable|string|in:sketch,watercolor,illustration,cinematic,brand_text',
        ]);

        // Get the project's effective image provider (Project → Account Default → Any Active)
        $aiProvider = $project->getEffectiveImageProvider();

        if (! $aiProvider) {
            return response()->json([
                'error' => 'No active AI provider with image support configured. Please add an AI provider in settings.',
            ], 422);
        }

        // Set initial status and dispatch job
        $cacheKey = GenerateFeaturedImageJob::statusCacheKey($article->id);
        Cache::put($cacheKey, [
            'status' => 'queued',
            'queued_at' => now()->toIso8601String(),
        ], now()->addMinutes(10));

        GenerateFeaturedImageJob::dispatch($article, $aiProvider, $validated['style'] ?? null);

        return response()->json([
            'success' => true,
            'status' => 'queued',
            'message' => 'Featured image generation started',
        ]);
    }

    public function featuredImageStatus(Project $project, Article $article): JsonResponse
    {
        $this->authorize('view', $project);
        $this->ensureArticleBelongsToProject($article, $project);

        $cacheKey = GenerateFeaturedImageJob::statusCacheKey($article->id);
        $status = Cache::get($cacheKey);

        if (! $status) {
            // No generation in progress, check if image exists
            $featuredImage = $article->images()->where('type', 'featured')->first();

            if ($featuredImage) {
                return response()->json([
                    'status' => 'completed',
                    'image' => [
                        'id' => $featuredImage->id,
                        'url' => $featuredImage->url,
                        'width' => $featuredImage->width,
                        'height' => $featuredImage->height,
                    ],
                ]);
            }

            return response()->json([
                'status' => 'idle',
            ]);
        }

        // If completed, include image data
        if ($status['status'] === 'completed') {
            $featuredImage = $article->images()->where('type', 'featured')->first();
            if ($featuredImage) {
                $status['image'] = [
                    'id' => $featuredImage->id,
                    'url' => $featuredImage->url,
                    'width' => $featuredImage->width,
                    'height' => $featuredImage->height,
                ];
            }
        }

        return response()->json($status);
    }

    public function deleteFeaturedImage(Request $request, Project $project, Article $article): JsonResponse
    {
        $this->authorize('update', $project);
        $this->ensureArticleBelongsToProject($article, $project);

        $image = $article->images()->where('type', 'featured')->first();

        if (! $image) {
            return response()->json([
                'error' => 'No featured image found',
            ], 404);
        }

        // Delete file from storage
        if ($image->path) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($image->path);
        }

        $image->delete();

        return response()->json([
            'success' => true,
            'message' => 'Featured image deleted',
        ]);
    }

    public function publish(Request $request, Project $project, Article $article): JsonResponse
    {
        $this->authorize('update', $project);
        $this->ensureArticleBelongsToProject($article, $project);

        $validated = $request->validate([
            'integration_ids' => 'required|array|min:1',
            'integration_ids.*' => 'required|integer|exists:integrations,id',
        ]);

        $integrations = $project->integrations()
            ->whereIn('id', $validated['integration_ids'])
            ->where('is_active', true)
            ->get();

        if ($integrations->isEmpty()) {
            return response()->json([
                'error' => 'No valid active integrations found.',
            ], 422);
        }

        $queued = [];

        foreach ($integrations as $integration) {
            // Create or update publication record as pending
            $publication = $article->publications()->updateOrCreate(
                ['integration_id' => $integration->id],
                [
                    'status' => 'pending',
                    'error_message' => null,
                ]
            );

            // Dispatch job
            PublishToIntegrationJob::dispatch($article, $integration, $publication);

            $queued[] = [
                'integration_id' => $integration->id,
                'integration_name' => $integration->name,
                'publication_id' => $publication->id,
            ];
        }

        return response()->json([
            'success' => true,
            'message' => count($queued) === 1
                ? 'Publishing to 1 integration...'
                : 'Publishing to '.count($queued).' integrations...',
            'queued' => $queued,
        ]);
    }

    public function retryPublication(Request $request, Project $project, Article $article, PublishingService $publishingService): JsonResponse
    {
        $this->authorize('update', $project);
        $this->ensureArticleBelongsToProject($article, $project);

        $validated = $request->validate([
            'publication_id' => 'required|integer|exists:publications,id',
        ]);

        $publication = $article->publications()
            ->where('id', $validated['publication_id'])
            ->firstOrFail();

        // Verify the integration still exists and is active
        $integration = $publication->integration;

        if (! $integration || ! $integration->is_active) {
            return response()->json([
                'error' => 'Integration is no longer active.',
            ], 422);
        }

        // Update status to pending
        $publication->update([
            'status' => 'pending',
            'error_message' => null,
        ]);

        // Refresh the publication to get the updated values
        $publication->refresh();

        // Dispatch job
        PublishToIntegrationJob::dispatch($article, $integration, $publication);

        return response()->json([
            'success' => true,
            'message' => 'Retrying publication...',
        ]);
    }

    public function publicationStatus(Request $request, Project $project, Article $article): JsonResponse
    {
        $this->authorize('view', $project);
        $this->ensureArticleBelongsToProject($article, $project);

        $publications = $article->publications()
            ->with('integration:id,name,type')
            ->get()
            ->map(fn ($pub) => [
                'id' => $pub->id,
                'integration_id' => $pub->integration_id,
                'integration_name' => $pub->integration?->name,
                'integration_type' => $pub->integration?->type,
                'status' => $pub->status,
                'external_url' => $pub->external_url,
                'error_message' => $pub->error_message,
                'published_at' => $pub->published_at?->toIso8601String(),
                'created_at' => $pub->created_at->toIso8601String(),
            ]);

        return response()->json([
            'publications' => $publications,
        ]);
    }

    public function regenerateInlineImage(
        Request $request,
        Project $project,
        Article $article,
        ArticleImageService $imageService
    ): JsonResponse {
        $this->authorize('update', $project);
        $this->ensureArticleBelongsToProject($article, $project);

        $validated = $request->validate([
            'style' => 'required|string|in:illustration,realistic,sketch,watercolor,cinematic,brand_text,photo',
            'prompt' => 'required|string|min:3|max:500',
        ]);

        // Get the project's effective image provider (Project → Account Default → Any Active)
        $aiProvider = $project->getEffectiveImageProvider();

        if (! $aiProvider) {
            return response()->json([
                'error' => 'No AI provider with image support configured. Please add one in settings.',
            ], 422);
        }

        // Check if the provider supports image generation
        $imageCapableProviders = ['openai', 'gemini'];
        if (! in_array($aiProvider->provider, $imageCapableProviders)) {
            return response()->json([
                'error' => "Your image provider ({$aiProvider->name}) does not support AI image generation. Please configure OpenAI or Gemini as your image provider.",
            ], 422);
        }

        try {
            $image = $imageService->generateInlineImage(
                $validated['prompt'],
                $validated['style'],
                $article,
                $aiProvider
            );

            return response()->json([
                'success' => true,
                'url' => $image->url,
                'alt' => $validated['prompt'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to generate image: '.$e->getMessage(),
            ], 500);
        }
    }

    public function searchYouTube(
        Request $request,
        Project $project,
        YouTubeService $youTubeService
    ): JsonResponse {
        $this->authorize('view', $project);

        $validated = $request->validate([
            'query' => 'required|string|min:2|max:200',
        ]);

        $user = $project->user;

        // Check if YouTube API is configured
        if (! $user->settings?->hasYouTubeApiKey()) {
            return response()->json([
                'error' => 'YouTube API key not configured. Add it in Settings → Generation Settings.',
                'videos' => [],
            ], 422);
        }

        $videos = $youTubeService
            ->forUser($user)
            ->searchMultiple($validated['query'], 5);

        // Format the response
        $formattedVideos = collect($videos)->map(fn ($video) => [
            'id' => $video['id']['videoId'] ?? null,
            'title' => $video['snippet']['title'] ?? '',
            'description' => $video['snippet']['description'] ?? '',
            'thumbnail' => $video['snippet']['thumbnails']['medium']['url'] ?? $video['snippet']['thumbnails']['default']['url'] ?? '',
            'channelTitle' => $video['snippet']['channelTitle'] ?? '',
            'url' => 'https://www.youtube.com/watch?v='.($video['id']['videoId'] ?? ''),
        ])->filter(fn ($video) => $video['id'] !== null)->values();

        return response()->json([
            'videos' => $formattedVideos,
        ]);
    }

    public function enrich(
        Request $request,
        Project $project,
        Article $article
    ): JsonResponse {
        $this->authorize('update', $project);
        $this->ensureArticleBelongsToProject($article, $project);

        // Set initial status and dispatch job
        $cacheKey = EnrichArticleJob::statusCacheKey($article->id);
        Cache::put($cacheKey, [
            'status' => 'queued',
            'queued_at' => now()->toIso8601String(),
        ], now()->addMinutes(30));

        EnrichArticleJob::dispatch($article);

        return response()->json([
            'success' => true,
            'status' => 'queued',
            'message' => 'Content enrichment started',
        ]);
    }

    public function enrichmentStatus(Project $project, Article $article): JsonResponse
    {
        $this->authorize('view', $project);
        $this->ensureArticleBelongsToProject($article, $project);

        $cacheKey = EnrichArticleJob::statusCacheKey($article->id);
        $status = Cache::get($cacheKey);

        if (! $status) {
            return response()->json([
                'status' => 'idle',
            ]);
        }

        // If completed, include updated content
        if ($status['status'] === 'completed') {
            $article->refresh();
            $status['content'] = $article->content_markdown ?? $article->content;
        }

        return response()->json($status);
    }

    protected function ensureArticleBelongsToProject(Article $article, Project $project): void
    {
        if ($article->project_id !== $project->id) {
            abort(404);
        }
    }
}
