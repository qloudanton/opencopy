<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Project;
use App\Services\ArticleImprovementService;
use App\Services\SeoScoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ArticleController extends Controller
{
    public function index(Request $request, Project $project): Response
    {
        $this->authorize('view', $project);

        $articles = $project->articles()
            ->with('keyword:id,keyword')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return Inertia::render('Articles/Index', [
            'project' => $project,
            'articles' => $articles,
        ]);
    }

    public function show(Request $request, Project $project, Article $article): Response
    {
        $this->authorize('view', $project);
        $this->ensureArticleBelongsToProject($article, $project);

        $article->load(['keyword', 'aiProvider']);

        return Inertia::render('Articles/Show', [
            'project' => $project,
            'article' => $article,
        ]);
    }

    public function edit(Request $request, Project $project, Article $article): Response
    {
        $this->authorize('update', $project);
        $this->ensureArticleBelongsToProject($article, $project);

        $article->load(['keyword']);

        return Inertia::render('Articles/Edit', [
            'project' => $project,
            'article' => $article,
        ]);
    }

    public function update(Request $request, Project $project, Article $article, SeoScoreService $seoScoreService): RedirectResponse
    {
        $this->authorize('update', $project);
        $this->ensureArticleBelongsToProject($article, $project);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'meta_description' => 'nullable|string|max:255',
            'content' => 'required|string',
            'status' => 'required|in:draft,review,published',
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

    protected function ensureArticleBelongsToProject(Article $article, Project $project): void
    {
        if ($article->project_id !== $project->id) {
            abort(404);
        }
    }
}
