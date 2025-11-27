<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Http\Requests\UpdateProjectSettingsRequest;
use App\Models\Project;
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
            ->withCount(['keywords', 'articles'])
            ->orderBy('name')
            ->get();

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
        $project = $request->user()->projects()->create($request->validated());

        return redirect()
            ->route('projects.show', $project)
            ->with('success', 'Project created successfully.');
    }

    public function show(Request $request, Project $project): Response
    {
        $this->authorize('view', $project);

        $project->loadCount(['keywords', 'articles', 'integrations']);
        $project->load([
            'keywords' => fn ($q) => $q->latest()->limit(5),
            'articles' => fn ($q) => $q->latest()->limit(5),
        ]);

        return Inertia::render('Projects/Show', [
            'project' => $project,
        ]);
    }

    public function edit(Request $request, Project $project): Response
    {
        $this->authorize('update', $project);

        return Inertia::render('Projects/Edit', [
            'project' => $project,
        ]);
    }

    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $project->update($request->validated());

        return redirect()
            ->route('projects.show', $project)
            ->with('success', 'Project updated successfully.');
    }

    public function destroy(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('delete', $project);

        $project->delete();

        return redirect()
            ->route('projects.index')
            ->with('success', 'Project deleted successfully.');
    }

    public function settings(Request $request, Project $project): Response
    {
        $this->authorize('update', $project);

        $aiProviders = $request->user()
            ->aiProviders()
            ->where('is_active', true)
            ->select('id', 'name', 'provider')
            ->orderBy('name')
            ->get();

        return Inertia::render('Projects/Settings', [
            'project' => $project->only([
                'id',
                'name',
                // Content Generation
                'default_ai_provider_id',
                'default_word_count',
                'default_tone',
                'target_audience',
                'brand_guidelines',
                // SEO Preferences
                'primary_language',
                'target_region',
                'internal_links_per_article',
                // Engagement
                'brand_color',
                'image_style',
                'include_youtube_videos',
                'include_emojis',
                'include_infographic_placeholders',
                'include_cta',
                'cta_product_name',
                'cta_website_url',
                'cta_features',
                'cta_action_text',
            ]),
            'aiProviders' => $aiProviders,
        ]);
    }

    public function updateSettings(UpdateProjectSettingsRequest $request, Project $project): RedirectResponse
    {
        $project->update($request->validated());

        return redirect()
            ->route('projects.settings', $project)
            ->with('success', 'Settings updated successfully.');
    }
}
