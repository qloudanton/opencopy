<?php

namespace App\Policies;

use App\Models\Article;
use App\Models\User;

class ArticlePolicy
{
    public function view(User $user, Article $article): bool
    {
        return $article->project->user_id === $user->id;
    }

    public function update(User $user, Article $article): bool
    {
        return $article->project->user_id === $user->id;
    }

    public function delete(User $user, Article $article): bool
    {
        return $article->project->user_id === $user->id;
    }
}
