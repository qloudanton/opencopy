<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreKeywordRequest;
use App\Http\Requests\UpdateKeywordRequest;
use App\Jobs\GenerateArticleJob;
use App\Models\Keyword;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KeywordController extends Controller
{
    public function index(Request $request, Project $project): Response
    {
        $this->authorize('view', $project);

        $keywords = $project->keywords()
            ->withCount('articles')
            ->orderBy('priority', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return Inertia::render('Keywords/Index', [
            'project' => $project,
            'keywords' => $keywords,
        ]);
    }

    public function create(Request $request, Project $project): Response
    {
        $this->authorize('view', $project);

        return Inertia::render('Keywords/Create', [
            'project' => $project,
        ]);
    }

    public function store(StoreKeywordRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('view', $project);

        $keyword = $project->keywords()->create($request->validated());

        return redirect()
            ->route('projects.keywords.show', [$project, $keyword])
            ->with('success', 'Keyword created successfully.');
    }

    public function show(Request $request, Project $project, Keyword $keyword): Response
    {
        $this->authorize('view', $project);

        $keyword->loadCount('articles');
        $keyword->load([
            'articles' => fn ($q) => $q->latest()->limit(10),
        ]);

        return Inertia::render('Keywords/Show', [
            'project' => $project,
            'keyword' => $keyword,
        ]);
    }

    public function edit(Request $request, Project $project, Keyword $keyword): Response
    {
        $this->authorize('view', $project);

        return Inertia::render('Keywords/Edit', [
            'project' => $project,
            'keyword' => $keyword,
        ]);
    }

    public function update(UpdateKeywordRequest $request, Project $project, Keyword $keyword): RedirectResponse
    {
        $this->authorize('view', $project);

        $keyword->update($request->validated());

        return redirect()
            ->route('projects.keywords.show', [$project, $keyword])
            ->with('success', 'Keyword updated successfully.');
    }

    public function destroy(Request $request, Project $project, Keyword $keyword): RedirectResponse
    {
        $this->authorize('view', $project);

        $keyword->delete();

        return redirect()
            ->route('projects.keywords.index', $project)
            ->with('success', 'Keyword deleted successfully.');
    }

    public function generate(Request $request, Project $project, Keyword $keyword): RedirectResponse
    {
        $this->authorize('view', $project);

        if ($keyword->isGenerating()) {
            return redirect()
                ->back()
                ->with('error', 'Article generation is already in progress for this keyword.');
        }

        $hasProvider = $request->user()
            ->aiProviders()
            ->where('is_active', true)
            ->exists();

        if (! $hasProvider) {
            return redirect()
                ->route('ai-providers.index')
                ->with('error', 'Please configure an AI provider before generating articles.');
        }

        $keyword->update(['status' => 'queued']);

        GenerateArticleJob::dispatch($keyword);

        return redirect()
            ->back()
            ->with('success', 'Article generation has been queued.');
    }
}
