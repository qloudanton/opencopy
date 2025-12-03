<?php

namespace App\Http\Controllers;

use App\Enums\ContentStatus;
use App\Enums\ContentType;
use App\Models\Project;
use App\Models\ScheduledContent;
use App\Services\ContentPlannerService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContentPlannerController extends Controller
{
    /**
     * Display the content planner calendar view.
     */
    public function index(Request $request, Project $project): Response
    {
        $this->authorize('view', $project);

        $view = $request->input('view', $project->calendar_view ?? 'month');
        $date = $request->input('date', now()->toDateString());
        $currentDate = Carbon::parse($date);

        // Get date range based on view
        [$startDate, $endDate] = $this->getDateRange($view, $currentDate, $project->calendar_start_day ?? 'monday');

        // Get scheduled content for the date range
        $scheduledContents = $project->scheduledContents()
            ->with(['keyword', 'article'])
            ->scheduledBetween($startDate, $endDate)
            ->orderBySchedule()
            ->get();

        // Get backlog items (unscheduled)
        $backlog = $project->scheduledContents()
            ->with(['keyword', 'article'])
            ->inBacklog()
            ->orderBy('position')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        // Get ALL keywords with article and scheduled counts
        $allKeywords = $project->keywords()
            ->withCount(['articles', 'scheduledContents'])
            ->orderBy('priority', 'desc')
            ->limit(100)
            ->get();

        // Get unscheduled articles (articles not linked to any scheduled content)
        $unscheduledArticles = $project->articles()
            ->whereDoesntHave('scheduledContent')
            ->with('keyword')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        // Get pipeline stats
        $stats = $this->getPipelineStats($project);

        return Inertia::render('ContentPlanner/Index', [
            'project' => $project,
            'scheduledContents' => $scheduledContents,
            'backlog' => $backlog,
            'allKeywords' => $allKeywords,
            'unscheduledArticles' => $unscheduledArticles,
            'stats' => $stats,
            'view' => $view,
            'currentDate' => $currentDate->toDateString(),
            'startDate' => $startDate->toDateString(),
            'endDate' => $endDate->toDateString(),
            'contentTypes' => $this->getContentTypes(),
            'contentStatuses' => $this->getContentStatuses(),
        ]);
    }

    /**
     * Store a new scheduled content item.
     */
    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('view', $project);

        $validated = $request->validate([
            'keyword_id' => ['nullable', 'exists:keywords,id'],
            'article_id' => ['nullable', 'exists:articles,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'content_type' => ['required_without:article_id', 'nullable', 'string'],
            'scheduled_date' => ['nullable', 'date'],
            'scheduled_time' => ['nullable', 'date_format:H:i'],
            'target_word_count' => ['nullable', 'integer', 'min:100', 'max:10000'],
            'tone' => ['nullable', 'string', 'max:50'],
            'custom_instructions' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        // If scheduling an existing article, populate from article data
        if (! empty($validated['article_id'])) {
            $article = $project->articles()->findOrFail($validated['article_id']);
            $validated['keyword_id'] = $article->keyword_id;
            $validated['title'] = $article->title;
            // Article already exists, so start in review/approved status
            $status = isset($validated['scheduled_date'])
                ? ContentStatus::Approved
                : ContentStatus::InReview;
        } else {
            $status = isset($validated['scheduled_date'])
                ? ContentStatus::Scheduled
                : ContentStatus::Backlog;
        }

        $project->scheduledContents()->create([
            ...$validated,
            'status' => $status,
        ]);

        return redirect()->back()->with('success', 'Content scheduled successfully.');
    }

    /**
     * Update a scheduled content item.
     */
    public function update(Request $request, Project $project, ScheduledContent $content): RedirectResponse
    {
        $this->authorize('view', $project);

        if ($content->project_id !== $project->id) {
            abort(404);
        }

        $validated = $request->validate([
            'keyword_id' => ['nullable', 'exists:keywords,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'content_type' => ['nullable', 'string'],
            'scheduled_date' => ['nullable', 'date'],
            'scheduled_time' => ['nullable', 'date_format:H:i'],
            'target_word_count' => ['nullable', 'integer', 'min:100', 'max:10000'],
            'tone' => ['nullable', 'string', 'max:50'],
            'custom_instructions' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:500'],
            'status' => ['nullable', 'string'],
        ]);

        // Handle status transitions
        if (isset($validated['status'])) {
            $newStatus = ContentStatus::from($validated['status']);
            if (! $content->canTransitionTo($newStatus)) {
                return redirect()->back()->with('error', 'Invalid status transition.');
            }
        }

        // Auto-update status based on scheduling
        if (isset($validated['scheduled_date']) && $content->isInBacklog()) {
            $validated['status'] = ContentStatus::Scheduled;
        } elseif (! isset($validated['scheduled_date']) && $content->isScheduled()) {
            $validated['status'] = ContentStatus::Backlog;
        }

        $content->update($validated);

        return redirect()->back()->with('success', 'Content updated successfully.');
    }

    /**
     * Delete a scheduled content item.
     */
    public function destroy(Request $request, Project $project, ScheduledContent $content): RedirectResponse
    {
        $this->authorize('view', $project);

        if ($content->project_id !== $project->id) {
            abort(404);
        }

        $content->delete();

        return redirect()->back()->with('success', 'Content removed from planner.');
    }

    /**
     * Schedule content for a specific date (drag & drop support).
     */
    public function schedule(Request $request, Project $project, ScheduledContent $content): JsonResponse
    {
        $this->authorize('view', $project);

        if ($content->project_id !== $project->id) {
            abort(404);
        }

        $validated = $request->validate([
            'scheduled_date' => ['required', 'date'],
            'scheduled_time' => ['nullable', 'date_format:H:i'],
            'position' => ['nullable', 'integer', 'min:0'],
        ]);

        $content->schedule(
            Carbon::parse($validated['scheduled_date']),
            $validated['scheduled_time'] ?? null
        );

        if (isset($validated['position'])) {
            $content->update(['position' => $validated['position']]);
        }

        return response()->json([
            'success' => true,
            'content' => $content->fresh(['keyword', 'article']),
        ]);
    }

    /**
     * Move content to backlog.
     */
    public function unschedule(Request $request, Project $project, ScheduledContent $content): JsonResponse
    {
        $this->authorize('view', $project);

        if ($content->project_id !== $project->id) {
            abort(404);
        }

        $content->moveToBacklog();

        return response()->json([
            'success' => true,
            'content' => $content->fresh(['keyword', 'article']),
        ]);
    }

    /**
     * Update content status (for pipeline drag & drop).
     */
    public function updateStatus(Request $request, Project $project, ScheduledContent $content): JsonResponse
    {
        $this->authorize('view', $project);

        if ($content->project_id !== $project->id) {
            abort(404);
        }

        $validated = $request->validate([
            'status' => ['required', 'string'],
            'position' => ['nullable', 'integer', 'min:0'],
        ]);

        $newStatus = ContentStatus::from($validated['status']);

        if (! $content->canTransitionTo($newStatus)) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid status transition.',
            ], 422);
        }

        $content->update([
            'status' => $newStatus,
            'position' => $validated['position'] ?? $content->position,
        ]);

        return response()->json([
            'success' => true,
            'content' => $content->fresh(['keyword', 'article']),
        ]);
    }

    /**
     * Auto-create backlog content for all available keywords using AI.
     */
    public function autoCreate(Request $request, Project $project, ContentPlannerService $service): JsonResponse
    {
        $this->authorize('view', $project);

        try {
            $result = $service->autoCreateBacklog($project);

            return response()->json([
                'success' => true,
                'created' => $result['created'],
                'message' => $result['created'] > 0
                    ? "{$result['created']} content items added to backlog."
                    : 'No available keywords to add.',
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Bulk add keywords to content planner.
     */
    public function bulkAdd(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('view', $project);

        $validated = $request->validate([
            'keyword_ids' => ['required', 'array', 'min:1', 'max:100'],
            'keyword_ids.*' => ['required', 'integer', 'exists:keywords,id'],
            'content_type' => ['required', 'string'],
            'add_to_backlog' => ['boolean'],
        ]);

        $keywords = $project->keywords()
            ->whereIn('id', $validated['keyword_ids'])
            ->whereDoesntHave('scheduledContents')
            ->get();

        foreach ($keywords as $keyword) {
            $project->scheduledContents()->create([
                'keyword_id' => $keyword->id,
                'title' => null, // Will use keyword as title
                'content_type' => $validated['content_type'],
                'status' => ContentStatus::Backlog,
            ]);
        }

        return redirect()->back()->with('success', count($keywords).' keywords added to content planner.');
    }

    /**
     * Get the date range for the current view.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function getDateRange(string $view, Carbon $date, string $startDay): array
    {
        $dayMap = [
            'sunday' => Carbon::SUNDAY,
            'monday' => Carbon::MONDAY,
            'saturday' => Carbon::SATURDAY,
        ];

        $weekStartsOn = $dayMap[$startDay] ?? Carbon::MONDAY;

        return match ($view) {
            'week' => [
                $date->copy()->startOfWeek($weekStartsOn),
                $date->copy()->endOfWeek($weekStartsOn),
            ],
            'day' => [
                $date->copy()->startOfDay(),
                $date->copy()->endOfDay(),
            ],
            default => [ // month
                $date->copy()->startOfMonth()->startOfWeek($weekStartsOn),
                $date->copy()->endOfMonth()->endOfWeek($weekStartsOn),
            ],
        };
    }

    /**
     * Get pipeline statistics for the project.
     *
     * @return array<string, mixed>
     */
    private function getPipelineStats(Project $project): array
    {
        $contents = $project->scheduledContents();

        return [
            'total' => $contents->count(),
            'backlog' => $contents->clone()->inBacklog()->count(),
            'scheduled' => $contents->clone()->scheduled()->count(),
            'generating' => $contents->clone()->where('status', ContentStatus::Generating)->count(),
            'in_review' => $contents->clone()->inReview()->count(),
            'approved' => $contents->clone()->approved()->count(),
            'published' => $contents->clone()->where('status', ContentStatus::Published)->count(),
            'failed' => $contents->clone()->where('status', ContentStatus::Failed)->count(),
            'overdue' => $contents->clone()
                ->where('status', '!=', ContentStatus::Published)
                ->whereNotNull('scheduled_date')
                ->whereDate('scheduled_date', '<', now())
                ->count(),
            'due_today' => $contents->clone()
                ->whereDate('scheduled_date', now())
                ->count(),
        ];
    }

    /**
     * Get content types for the frontend.
     *
     * @return array<array<string, mixed>>
     */
    private function getContentTypes(): array
    {
        return collect(ContentType::cases())->map(fn ($type) => [
            'value' => $type->value,
            'label' => $type->label(),
            'description' => $type->description(),
            'icon' => $type->icon(),
            'wordCount' => $type->suggestedWordCount(),
        ])->values()->all();
    }

    /**
     * Get content statuses for the frontend.
     *
     * @return array<array<string, mixed>>
     */
    private function getContentStatuses(): array
    {
        return collect(ContentStatus::cases())->map(fn ($status) => [
            'value' => $status->value,
            'label' => $status->label(),
            'color' => $status->color(),
            'icon' => $status->icon(),
        ])->values()->all();
    }

    /**
     * Quick create keyword from content planner.
     */
    public function createKeyword(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $validated = $request->validate([
            'keyword' => ['required', 'string', 'max:255'],
        ]);

        $keyword = $project->keywords()->create($validated);

        return response()->json([
            'success' => true,
            'keyword' => [
                'id' => $keyword->id,
                'keyword' => $keyword->keyword,
                'volume' => $keyword->volume,
                'difficulty' => $keyword->difficulty,
            ],
        ]);
    }
}
