<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Project;
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

    public function update(Request $request, Project $project, Article $article): RedirectResponse
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

    protected function ensureArticleBelongsToProject(Article $article, Project $project): void
    {
        if ($article->project_id !== $project->id) {
            abort(404);
        }
    }
}
