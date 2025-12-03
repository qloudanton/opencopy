<?php

namespace App\Http\Controllers;

use App\Enums\IntegrationType;
use App\Http\Requests\StoreIntegrationRequest;
use App\Http\Requests\UpdateIntegrationRequest;
use App\Models\Integration;
use App\Models\Project;
use App\Services\Publishing\PublishingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IntegrationController extends Controller
{
    public function __construct(
        private readonly PublishingService $publishingService,
    ) {}

    public function index(Request $request, Project $project): Response
    {
        $this->authorize('view', $project);

        $integrations = $project->integrations()
            ->withCount('publications')
            ->orderBy('name')
            ->get()
            ->map(fn (Integration $integration) => [
                'id' => $integration->id,
                'type' => $integration->type,
                'name' => $integration->name,
                'is_active' => $integration->is_active,
                'has_credentials' => $integration->hasRequiredCredentials(),
                'last_connected_at' => $integration->last_connected_at?->toIso8601String(),
                'publications_count' => $integration->publications_count,
                'created_at' => $integration->created_at->toIso8601String(),
            ]);

        return Inertia::render('Projects/Integrations/Index', [
            'project' => $project->only(['id', 'name']),
            'integrations' => $integrations,
            'availableTypes' => $this->getAvailableIntegrationTypes(),
        ]);
    }

    public function store(StoreIntegrationRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $data = $request->validated();

        // Build credentials array from the type-specific fields
        $credentials = $this->buildCredentials($data);

        // Build settings array
        $settings = $this->buildSettings($data);

        $project->integrations()->create([
            'type' => $data['type'],
            'name' => $data['name'],
            'credentials' => $credentials,
            'settings' => $settings,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return redirect()
            ->route('projects.integrations.index', $project)
            ->with('success', 'Integration added successfully.');
    }

    public function update(UpdateIntegrationRequest $request, Project $project, Integration $integration): RedirectResponse
    {
        $this->authorize('update', $project);
        $this->ensureIntegrationBelongsToProject($integration, $project);

        $data = $request->validated();

        // Build credentials - only update non-empty credential fields
        $credentials = $this->buildCredentials($data, $integration->credentials);

        // Build settings
        $settings = $this->buildSettings($data, $integration->settings);

        $integration->update([
            'name' => $data['name'],
            'credentials' => $credentials,
            'settings' => $settings,
            'is_active' => $data['is_active'] ?? $integration->is_active,
        ]);

        return redirect()
            ->route('projects.integrations.index', $project)
            ->with('success', 'Integration updated successfully.');
    }

    public function destroy(Request $request, Project $project, Integration $integration): RedirectResponse
    {
        $this->authorize('update', $project);
        $this->ensureIntegrationBelongsToProject($integration, $project);

        $integration->delete();

        return redirect()
            ->route('projects.integrations.index', $project)
            ->with('success', 'Integration removed successfully.');
    }

    public function test(Request $request, Project $project, Integration $integration): JsonResponse
    {
        $this->authorize('update', $project);
        $this->ensureIntegrationBelongsToProject($integration, $project);

        $result = $this->publishingService->test($integration);

        return response()->json($result->toDebugArray());
    }

    public function toggleActive(Request $request, Project $project, Integration $integration): RedirectResponse
    {
        $this->authorize('update', $project);
        $this->ensureIntegrationBelongsToProject($integration, $project);

        $integration->update(['is_active' => ! $integration->is_active]);

        $status = $integration->is_active ? 'enabled' : 'disabled';

        return redirect()
            ->route('projects.integrations.index', $project)
            ->with('success', "Integration {$status}.");
    }

    /**
     * Build credentials array from request data.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $existing
     * @return array<string, mixed>
     */
    private function buildCredentials(array $data, array $existing = []): array
    {
        $credentials = $existing;

        // Webhook credentials
        if (! empty($data['endpoint_url'])) {
            $credentials['endpoint_url'] = $data['endpoint_url'];
        }
        if (! empty($data['access_token'])) {
            $credentials['access_token'] = $data['access_token'];
        }

        // WordPress credentials
        if (! empty($data['site_url'])) {
            $credentials['site_url'] = $data['site_url'];
        }
        if (! empty($data['username'])) {
            $credentials['username'] = $data['username'];
        }
        if (! empty($data['application_password'])) {
            $credentials['application_password'] = $data['application_password'];
        }

        // Webflow credentials
        if (! empty($data['api_token'])) {
            $credentials['api_token'] = $data['api_token'];
        }
        if (! empty($data['site_id'])) {
            $credentials['site_id'] = $data['site_id'];
        }
        if (! empty($data['collection_id'])) {
            $credentials['collection_id'] = $data['collection_id'];
        }

        // Shopify credentials
        if (! empty($data['store_url'])) {
            $credentials['store_url'] = $data['store_url'];
        }
        if (! empty($data['blog_id'])) {
            $credentials['blog_id'] = $data['blog_id'];
        }

        // Wix credentials
        if (! empty($data['api_key'])) {
            $credentials['api_key'] = $data['api_key'];
        }

        return $credentials;
    }

    /**
     * Build settings array from request data.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $existing
     * @return array<string, mixed>
     */
    private function buildSettings(array $data, array $existing = []): array
    {
        $settings = $existing;

        // Webhook settings
        if (isset($data['timeout'])) {
            $settings['timeout'] = (int) $data['timeout'];
        }
        if (isset($data['retry_times'])) {
            $settings['retry_times'] = (int) $data['retry_times'];
        }

        // WordPress settings
        if (isset($data['default_status'])) {
            $settings['default_status'] = $data['default_status'];
        }
        if (isset($data['default_category'])) {
            $settings['default_category'] = $data['default_category'];
        }

        return $settings;
    }

    /**
     * Ensure the integration belongs to the project.
     */
    private function ensureIntegrationBelongsToProject(Integration $integration, Project $project): void
    {
        if ($integration->project_id !== $project->id) {
            abort(404);
        }
    }

    /**
     * Get available integration types with their configurations.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getAvailableIntegrationTypes(): array
    {
        return collect(IntegrationType::cases())
            ->map(fn (IntegrationType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
                'description' => $type->description(),
                'icon' => $type->icon(),
                'is_available' => $type->isAvailable(),
                'credentials' => $type->requiredCredentials(),
            ])
            ->values()
            ->all();
    }
}
