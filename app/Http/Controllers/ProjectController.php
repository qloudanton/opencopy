<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectRequest;
use App\Models\Project;
use App\Services\BusinessAnalyzerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(Request $request): Response
    {
        $projects = $request->user()
            ->projects()
            ->withCount(['keywords', 'articles', 'integrations'])
            ->orderBy('name')
            ->get()
            ->map(fn ($project) => [
                'id' => $project->id,
                'name' => $project->name,
                'website_url' => $project->website_url,
                'description' => $project->description,
                'primary_language' => $project->primary_language,
                'target_region' => $project->target_region,
                'default_tone' => $project->default_tone,
                'keywords_count' => $project->keywords_count,
                'articles_count' => $project->articles_count,
                'integrations_count' => $project->integrations_count,
                'is_active' => $project->is_active,
                'created_at' => $project->created_at,
                'updated_at' => $project->updated_at,
            ]);

        return Inertia::render('Projects/Index', [
            'projects' => $projects,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Projects/Create');
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $keywords = $validated['keywords'] ?? [];
        unset($validated['keywords']);

        $project = $request->user()->projects()->create($validated);

        // Create keywords if provided
        if (! empty($keywords)) {
            foreach ($keywords as $keywordData) {
                $project->keywords()->create([
                    'keyword' => $keywordData['keyword'],
                    'search_intent' => $keywordData['search_intent'] ?? 'informational',
                    'difficulty' => $keywordData['difficulty'] ?? null,
                    'volume' => $keywordData['volume'] ?? null,
                    'status' => 'pending',
                ]);
            }
        }

        return redirect()
            ->route('projects.show', $project)
            ->with('success', 'Project created successfully.');
    }

    public function show(Request $request, Project $project): Response
    {
        $this->authorize('view', $project);

        $project->loadCount(['keywords', 'articles', 'integrations']);

        // Content runway - how far ahead is content scheduled?
        $scheduledContent = $project->scheduledContents()
            ->whereIn('status', ['scheduled', 'generating'])
            ->whereNotNull('scheduled_date')
            ->where('scheduled_date', '>=', now()->toDateString());

        $nextScheduled = (clone $scheduledContent)
            ->with('keyword:id,keyword')
            ->orderBy('scheduled_date')
            ->orderBy('scheduled_time')
            ->first();

        $lastScheduled = (clone $scheduledContent)
            ->orderBy('scheduled_date', 'desc')
            ->first();

        $scheduledCount = $scheduledContent->count();

        $contentRunway = [
            'days_ahead' => $lastScheduled?->scheduled_date
                ? now()->startOfDay()->diffInDays($lastScheduled->scheduled_date, false)
                : 0,
            'scheduled_count' => $scheduledCount,
            'next_scheduled' => $nextScheduled ? [
                'id' => $nextScheduled->id,
                'title' => $nextScheduled->title ?? $nextScheduled->keyword?->keyword ?? 'Untitled',
                'scheduled_date' => $nextScheduled->scheduled_date?->toDateString(),
                'scheduled_time' => $nextScheduled->scheduled_time?->format('H:i'),
            ] : null,
            'last_scheduled_date' => $lastScheduled?->scheduled_date?->toDateString(),
            'backlog_count' => $project->scheduledContents()->where('status', 'backlog')->count(),
            'published_count' => $project->scheduledContents()->where('status', 'published')->count(),
            'failed_count' => $project->scheduledContents()->where('status', 'failed')->count(),
        ];

        // Needs attention - failed or overdue content
        $needsAttention = $project->scheduledContents()
            ->with(['keyword:id,keyword', 'article:id,title'])
            ->where(function ($q) {
                $q->where('status', 'failed')
                    ->orWhere(function ($q2) {
                        $q2->whereNotIn('status', ['published', 'backlog'])
                            ->whereNotNull('scheduled_date')
                            ->where('scheduled_date', '<', now()->toDateString());
                    });
            })
            ->orderBy('scheduled_date')
            ->limit(5)
            ->get();

        // Currently generating
        $generating = $project->scheduledContents()
            ->with(['keyword:id,keyword'])
            ->where('status', 'generating')
            ->orderBy('generation_started_at')
            ->limit(5)
            ->get();

        // Upcoming content - next 7 days
        $upcomingContent = $project->scheduledContents()
            ->with(['keyword:id,keyword', 'article:id,title,status'])
            ->whereIn('status', ['scheduled', 'generating', 'in_review', 'approved'])
            ->whereNotNull('scheduled_date')
            ->whereBetween('scheduled_date', [now()->toDateString(), now()->addDays(7)->toDateString()])
            ->orderBy('scheduled_date')
            ->orderBy('scheduled_time')
            ->limit(10)
            ->get();

        // Recent articles
        $recentArticles = $project->articles()
            ->with(['keyword:id,keyword'])
            ->latest()
            ->limit(5)
            ->get();

        // Keywords without articles
        $untargetedKeywords = $project->keywords()
            ->whereDoesntHave('articles')
            ->limit(5)
            ->get(['id', 'keyword']);

        // Active integrations
        $integrations = $project->integrations()
            ->where('is_active', true)
            ->get(['id', 'type', 'name', 'is_active', 'last_connected_at']);

        return Inertia::render('Projects/Show', [
            'project' => $project->only([
                'id', 'name', 'website_url', 'description',
                'keywords_count', 'articles_count', 'integrations_count',
            ]),
            'contentRunway' => $contentRunway,
            'needsAttention' => $needsAttention,
            'generating' => $generating,
            'upcomingContent' => $upcomingContent,
            'recentArticles' => $recentArticles,
            'untargetedKeywords' => $untargetedKeywords,
            'integrations' => $integrations,
        ]);
    }

    public function destroy(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('delete', $project);

        $project->delete();

        return redirect()
            ->route('projects.index')
            ->with('success', 'Project deleted successfully.');
    }

    // Settings: General (Project Details)
    public function settingsGeneral(Request $request, Project $project): Response
    {
        $this->authorize('update', $project);

        return Inertia::render('Projects/Settings/ProjectDetails', [
            'project' => $project->only([
                'id',
                'name',
                'website_url',
                'description',
            ]),
        ]);
    }

    public function updateSettingsProjectDetails(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'website_url' => ['required', 'url', 'max:500'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $project->update($validated);

        return back()->with('success', 'Project details updated successfully.');
    }

    // Settings: Content
    public function settingsContent(Request $request, Project $project): Response
    {
        $this->authorize('update', $project);

        $user = $request->user();

        // Get text-capable providers
        $textProviders = $user->aiProviders()
            ->where('is_active', true)
            ->where('supports_text', true)
            ->select('id', 'name', 'provider')
            ->orderBy('name')
            ->get();

        // Get image-capable providers
        $imageProviders = $user->aiProviders()
            ->where('is_active', true)
            ->where('supports_image', true)
            ->select('id', 'name', 'provider')
            ->orderBy('name')
            ->get();

        // Get the user's account defaults for display
        $accountDefaultTextProvider = $user->getDefaultTextProvider();
        $accountDefaultImageProvider = $user->getDefaultImageProvider();

        return Inertia::render('Projects/Settings/Content', [
            'project' => $project->only([
                'id',
                'name',
                'default_ai_provider_id',
                'default_image_provider_id',
                'default_word_count',
                'default_tone',
                'target_audiences',
                'competitors',
                'brand_guidelines',
                'include_emojis',
            ]),
            'textProviders' => $textProviders,
            'imageProviders' => $imageProviders,
            'accountDefaultTextProvider' => $accountDefaultTextProvider ? [
                'id' => $accountDefaultTextProvider->id,
                'name' => $accountDefaultTextProvider->name,
                'provider' => $accountDefaultTextProvider->provider,
            ] : null,
            'accountDefaultImageProvider' => $accountDefaultImageProvider ? [
                'id' => $accountDefaultImageProvider->id,
                'name' => $accountDefaultImageProvider->name,
                'provider' => $accountDefaultImageProvider->provider,
            ] : null,
        ]);
    }

    public function updateSettingsContent(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'default_ai_provider_id' => [
                'nullable',
                'integer',
                'exists:ai_providers,id',
                function ($attribute, $value, $fail) use ($request) {
                    if ($value !== null) {
                        $provider = \App\Models\AiProvider::find($value);
                        if (! $provider || $provider->user_id !== $request->user()->id) {
                            $fail('The selected text provider is invalid.');
                        }
                        if (! $provider->supports_text) {
                            $fail('The selected provider does not support text generation.');
                        }
                    }
                },
            ],
            'default_image_provider_id' => [
                'nullable',
                'integer',
                'exists:ai_providers,id',
                function ($attribute, $value, $fail) use ($request) {
                    if ($value !== null) {
                        $provider = \App\Models\AiProvider::find($value);
                        if (! $provider || $provider->user_id !== $request->user()->id) {
                            $fail('The selected image provider is invalid.');
                        }
                        if (! $provider->supports_image) {
                            $fail('The selected provider does not support image generation.');
                        }
                    }
                },
            ],
            'default_word_count' => 'integer|min:500|max:5000',
            'default_tone' => 'string|in:professional,casual,friendly,technical,authoritative,conversational,formal,informative,persuasive,enthusiastic',
            'target_audiences' => 'nullable|array',
            'target_audiences.*' => 'string|max:200',
            'competitors' => 'nullable|array|max:10',
            'competitors.*' => 'string|max:255',
            'brand_guidelines' => 'nullable|string|max:2000',
            'include_emojis' => 'boolean',
        ]);

        $project->update($validated);

        return back()->with('success', 'Settings updated successfully.');
    }

    // Settings: Localization (formerly SEO)
    public function settingsLocalization(Request $request, Project $project): Response
    {
        $this->authorize('update', $project);

        return Inertia::render('Projects/Settings/Localization', [
            'project' => $project->only([
                'id',
                'name',
                'primary_language',
                'target_region',
            ]),
        ]);
    }

    public function updateSettingsLocalization(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'primary_language' => 'string|max:10',
            'target_region' => 'nullable|string|max:50',
        ]);

        $project->update($validated);

        return back()->with('success', 'Settings updated successfully.');
    }

    // Settings: Internal Linking
    public function settingsInternalLinking(Request $request, Project $project): Response
    {
        $this->authorize('update', $project);

        return Inertia::render('Projects/Settings/InternalLinking', [
            'project' => $project->only([
                'id',
                'name',
                'internal_links_per_article',
                'sitemap_url',
                'auto_internal_linking',
                'prioritize_blog_links',
                'cross_link_articles',
                'sitemap_last_fetched_at',
            ]),
            'pageStats' => [
                'total' => $project->pages()->count(),
                'active' => $project->pages()->where('is_active', true)->count(),
                'by_type' => [
                    'blog' => $project->pages()->where('is_active', true)->where('page_type', 'blog')->count(),
                    'product' => $project->pages()->where('is_active', true)->where('page_type', 'product')->count(),
                    'service' => $project->pages()->where('is_active', true)->where('page_type', 'service')->count(),
                    'landing' => $project->pages()->where('is_active', true)->where('page_type', 'landing')->count(),
                    'other' => $project->pages()->where('is_active', true)->where('page_type', 'other')->count(),
                ],
            ],
        ]);
    }

    public function updateSettingsInternalLinking(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'internal_links_per_article' => 'integer|min:0|max:10',
            'sitemap_url' => 'nullable|url|max:2048',
            'auto_internal_linking' => 'boolean',
            'prioritize_blog_links' => 'boolean',
            'cross_link_articles' => 'boolean',
        ]);

        $project->update($validated);

        return back()->with('success', 'Settings updated successfully.');
    }

    // Settings: Media
    public function settingsMedia(Request $request, Project $project): Response
    {
        $this->authorize('update', $project);

        $user = $request->user();

        return Inertia::render('Projects/Settings/Media', [
            'project' => $project->only([
                'id',
                'name',
                'generate_inline_images',
                'generate_featured_image',
                'brand_color',
                'image_style',
                'include_youtube_videos',
                'include_infographic_placeholders',
            ]),
            'hasYouTubeApiKey' => $user->settings?->hasYouTubeApiKey() ?? false,
        ]);
    }

    public function updateSettingsMedia(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'generate_inline_images' => 'boolean',
            'generate_featured_image' => 'boolean',
            'brand_color' => 'nullable|string|max:7',
            'image_style' => 'string|in:illustration,sketch,watercolor,cinematic,brand-text',
            'include_youtube_videos' => 'boolean',
            'include_infographic_placeholders' => 'boolean',
        ]);

        $project->update($validated);

        return back()->with('success', 'Settings updated successfully.');
    }

    // Settings: Call-to-Action
    public function settingsCallToAction(Request $request, Project $project): Response
    {
        $this->authorize('update', $project);

        return Inertia::render('Projects/Settings/CallToAction', [
            'project' => $project->only([
                'id',
                'name',
                'include_cta',
                'cta_product_name',
                'cta_website_url',
                'cta_features',
                'cta_action_text',
            ]),
        ]);
    }

    public function updateSettingsCallToAction(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'include_cta' => 'boolean',
            'cta_product_name' => 'nullable|string|max:100',
            'cta_website_url' => 'nullable|url|max:255',
            'cta_features' => 'nullable|string|max:500',
            'cta_action_text' => 'nullable|string|max:100',
        ]);

        $project->update($validated);

        return back()->with('success', 'Settings updated successfully.');
    }

    // Settings: Publishing
    public function settingsPublishing(Request $request, Project $project): Response
    {
        $this->authorize('update', $project);

        return Inertia::render('Projects/Settings/Publishing', [
            'project' => $project->only([
                'id',
                'name',
                'auto_publish',
                'skip_review',
            ]),
        ]);
    }

    public function updateSettingsPublishing(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'auto_publish' => 'required|in:manual,immediate,scheduled',
            'skip_review' => 'boolean',
        ]);

        $project->update($validated);

        return back()->with('success', 'Publishing settings updated successfully.');
    }

    // Settings: Danger Zone
    public function settingsDangerZone(Request $request, Project $project): Response
    {
        $this->authorize('update', $project);

        return Inertia::render('Projects/Settings/DangerZone', [
            'project' => $project->only(['id', 'name']),
        ]);
    }

    public function analyzeWebsite(Request $request, BusinessAnalyzerService $analyzerService): JsonResponse
    {
        $request->validate([
            'url' => ['required', 'url'],
        ]);

        try {
            $result = $analyzerService->analyzeWebsite(
                $request->input('url'),
                $request->user()
            );

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function generateAudiences(Request $request, BusinessAnalyzerService $analyzerService): JsonResponse
    {
        $request->validate([
            'description' => ['required', 'string', 'min:10'],
        ]);

        try {
            $audiences = $analyzerService->generateTargetAudiences(
                $request->input('description'),
                $request->user()
            );

            return response()->json([
                'success' => true,
                'data' => $audiences,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function generateCompetitors(Request $request, BusinessAnalyzerService $analyzerService): JsonResponse
    {
        $request->validate([
            'description' => ['required', 'string', 'min:10'],
        ]);

        try {
            $competitors = $analyzerService->generateCompetitors(
                $request->input('description'),
                $request->user()
            );

            return response()->json([
                'success' => true,
                'data' => $competitors,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function generateKeywords(Request $request, BusinessAnalyzerService $analyzerService): JsonResponse
    {
        $request->validate([
            'description' => ['required', 'string', 'min:10'],
            'target_audiences' => ['nullable', 'array'],
            'target_audiences.*' => ['string'],
            'competitors' => ['nullable', 'array'],
            'competitors.*' => ['string'],
        ]);

        try {
            $keywords = $analyzerService->generateKeywordSuggestions(
                $request->input('description'),
                $request->input('target_audiences', []),
                $request->input('competitors', []),
                $request->user()
            );

            return response()->json([
                'success' => true,
                'data' => $keywords,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
