<?php

namespace App\Http\Middleware;

use App\Models\Project;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $request->user(),
            ],
            'csrf_token' => csrf_token(),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'projects' => fn () => $this->getProjects($request),
            'currentProject' => fn () => $this->getCurrentProject($request),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'message' => fn () => $request->session()->get('message'),
            ],
        ];
    }

    /**
     * Get all projects for the authenticated user.
     *
     * @return array<int, array{id: int, name: string, domain: string|null, website_url: string|null}>
     */
    protected function getProjects(Request $request): array
    {
        if (! $request->user()) {
            return [];
        }

        return $request->user()
            ->projects()
            ->select('id', 'name', 'domain', 'website_url')
            ->orderBy('name')
            ->get()
            ->toArray();
    }

    /**
     * Get the current project from the route.
     *
     * @return array{id: int, name: string, domain: string|null, website_url: string|null}|null
     */
    protected function getCurrentProject(Request $request): ?array
    {
        $project = $request->route('project');

        if ($project instanceof Project) {
            return [
                'id' => $project->id,
                'name' => $project->name,
                'domain' => $project->domain,
                'website_url' => $project->website_url,
            ];
        }

        return null;
    }
}
