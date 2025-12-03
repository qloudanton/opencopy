<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectPage extends Model
{
    /** @use HasFactory<\Database\Factories\ProjectPageFactory> */
    use HasFactory;

    protected $fillable = [
        'project_id',
        'url',
        'title',
        'page_type',
        'keywords',
        'priority',
        'link_count',
        'is_active',
        'last_modified_at',
        'last_fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'keywords' => 'array',
            'priority' => 'decimal:2',
            'link_count' => 'integer',
            'is_active' => 'boolean',
            'last_modified_at' => 'datetime',
            'last_fetched_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeBlogPosts($query)
    {
        return $query->where('page_type', 'blog');
    }

    public function scopeLeastLinked($query)
    {
        return $query->orderBy('link_count', 'asc');
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('page_type', $type);
    }
}
