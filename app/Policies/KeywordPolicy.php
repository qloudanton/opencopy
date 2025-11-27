<?php

namespace App\Policies;

use App\Models\Keyword;
use App\Models\Project;
use App\Models\User;

class KeywordPolicy
{
    public function viewAny(User $user, Project $project): bool
    {
        return $user->id === $project->user_id;
    }

    public function view(User $user, Keyword $keyword): bool
    {
        return $user->id === $keyword->project->user_id;
    }

    public function create(User $user, Project $project): bool
    {
        return $user->id === $project->user_id;
    }

    public function update(User $user, Keyword $keyword): bool
    {
        return $user->id === $keyword->project->user_id;
    }

    public function delete(User $user, Keyword $keyword): bool
    {
        return $user->id === $keyword->project->user_id;
    }

    public function restore(User $user, Keyword $keyword): bool
    {
        return $user->id === $keyword->project->user_id;
    }

    public function forceDelete(User $user, Keyword $keyword): bool
    {
        return $user->id === $keyword->project->user_id;
    }
}
