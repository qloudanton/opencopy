<?php

namespace App\Http\Controllers;

use App\Models\Article;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ArticleController extends Controller
{
    public function show(Request $request, Article $article): Response
    {
        $this->authorize('view', $article);

        $article->load(['project', 'keyword', 'aiProvider']);

        return Inertia::render('Articles/Show', [
            'article' => $article,
        ]);
    }

    public function edit(Request $request, Article $article): Response
    {
        $this->authorize('update', $article);

        $article->load(['project', 'keyword']);

        return Inertia::render('Articles/Edit', [
            'article' => $article,
        ]);
    }

    public function update(Request $request, Article $article): RedirectResponse
    {
        $this->authorize('update', $article);

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
            ->route('articles.show', $article)
            ->with('success', 'Article updated successfully.');
    }

    public function destroy(Request $request, Article $article): RedirectResponse
    {
        $this->authorize('delete', $article);

        $keywordId = $article->keyword_id;
        $projectId = $article->project_id;

        $article->delete();

        if ($keywordId) {
            return redirect()
                ->route('projects.keywords.show', [$projectId, $keywordId])
                ->with('success', 'Article deleted successfully.');
        }

        return redirect()
            ->route('projects.show', $projectId)
            ->with('success', 'Article deleted successfully.');
    }
}
