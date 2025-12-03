<?php

namespace App\Http\Controllers;

use App\Enums\ContentStatus;
use App\Http\Requests\StoreKeywordRequest;
use App\Http\Requests\UpdateKeywordRequest;
use App\Jobs\GenerateArticleJob;
use App\Models\Keyword;
use App\Models\Project;
use App\Models\ScheduledContent;
use App\Services\BusinessAnalyzerService;
use Illuminate\Http\JsonResponse;
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
            ->with(['scheduledContent'])
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
            'articles' => fn ($q) => $q->withSum('usageLogs', 'estimated_cost')->latest()->limit(10),
            'scheduledContent',
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

        // Find existing scheduled content without an article, or create a new one
        $scheduledContent = ScheduledContent::where('keyword_id', $keyword->id)
            ->whereNull('article_id')
            ->whereNotIn('status', [ContentStatus::Generating, ContentStatus::Queued])
            ->first();

        if (! $scheduledContent) {
            $scheduledContent = ScheduledContent::create([
                'project_id' => $project->id,
                'keyword_id' => $keyword->id,
                'title' => $keyword->keyword,
                'status' => ContentStatus::Queued,
            ]);
        } else {
            $scheduledContent->update(['status' => ContentStatus::Queued]);
        }

        GenerateArticleJob::dispatch($scheduledContent);

        return redirect()
            ->back()
            ->with('success', 'Article generation has been queued.');
    }

    public function analyze(Request $request, Project $project, BusinessAnalyzerService $analyzer): JsonResponse
    {
        \Log::info('Analyze endpoint called', [
            'project_id' => $project->id,
            'request_data' => $request->all(),
            'expects_json' => $request->expectsJson(),
            'is_ajax' => $request->ajax(),
        ]);

        $this->authorize('view', $project);

        $request->validate([
            'keyword_ids' => ['required', 'array', 'min:1', 'max:50'],
            'keyword_ids.*' => ['required', 'integer', 'exists:keywords,id'],
        ]);

        $keywords = $project->keywords()
            ->whereIn('id', $request->input('keyword_ids'))
            ->get();

        if ($keywords->isEmpty()) {
            return response()->json(['error' => 'No keywords found'], 404);
        }

        $businessDescription = $project->description ?? $project->name;

        try {
            $results = $analyzer->analyzeKeywordMetrics(
                $keywords->pluck('keyword')->toArray(),
                $businessDescription,
                $request->user()
            );

            // Map results back to keywords and update
            $resultMap = collect($results)->keyBy(fn ($r) => strtolower(trim($r['keyword'])));

            \Log::info('Mapping results to keywords', [
                'result_keys' => $resultMap->keys()->toArray(),
                'keyword_keys' => $keywords->pluck('keyword')->map(fn ($k) => strtolower(trim($k)))->toArray(),
            ]);

            $matchedCount = 0;
            $unmatchedKeywords = [];

            foreach ($keywords as $keyword) {
                $keywordLower = strtolower(trim($keyword->keyword));
                $result = $resultMap->get($keywordLower);
                if ($result) {
                    $keyword->update([
                        'difficulty' => $result['difficulty'] ?? null,
                        'volume' => $result['volume'] ?? null,
                    ]);
                    $matchedCount++;
                } else {
                    $unmatchedKeywords[] = $keyword->keyword;
                }
            }

            \Log::info('Keyword matching complete', [
                'matched' => $matchedCount,
                'unmatched_count' => count($unmatchedKeywords),
                'unmatched_keywords' => $unmatchedKeywords,
            ]);

            // Reload keywords with updated data
            $updatedKeywords = $project->keywords()
                ->whereIn('id', $request->input('keyword_ids'))
                ->withCount('articles')
                ->get();

            return response()->json([
                'success' => true,
                'keywords' => $updatedKeywords,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
