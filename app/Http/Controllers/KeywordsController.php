<?php

namespace App\Http\Controllers;

use App\Models\Keyword;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KeywordsController extends Controller
{
    public function index(Request $request): Response
    {
        $keywords = Keyword::query()
            ->whereIn('project_id', $request->user()->projects()->pluck('id'))
            ->with('project:id,name')
            ->withCount('articles')
            ->orderBy('priority', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return Inertia::render('Keywords/IndexAll', [
            'keywords' => $keywords,
        ]);
    }
}
