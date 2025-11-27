<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class InternalLink extends Model
{
    /** @use HasFactory<\Database\Factories\InternalLinkFactory> */
    use HasFactory;

    protected $fillable = [
        'project_id',
        'url',
        'anchor_text',
        'title',
        'description',
        'category',
        'priority',
        'max_uses_per_article',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'max_uses_per_article' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class)
            ->withPivot(['position', 'anchor_text_used'])
            ->withTimestamps();
    }
}
