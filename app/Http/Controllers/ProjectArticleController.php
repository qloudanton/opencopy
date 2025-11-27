<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectArticleController extends Controller
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
}
