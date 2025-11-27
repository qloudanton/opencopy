<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Article extends Model
{
    /** @use HasFactory<\Database\Factories\ArticleFactory> */
    use HasFactory;

    protected $fillable = [
        'project_id',
        'keyword_id',
        'ai_provider_id',
        'title',
        'slug',
        'meta_description',
        'excerpt',
        'content',
        'content_markdown',
        'outline',
        'word_count',
        'reading_time_minutes',
        'seo_score',
        'seo_analysis',
        'status',
        'generation_metadata',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'outline' => 'array',
            'seo_analysis' => 'array',
            'generation_metadata' => 'array',
            'word_count' => 'integer',
            'reading_time_minutes' => 'integer',
            'seo_score' => 'integer',
            'generated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Article $article) {
            if (empty($article->slug)) {
                $article->slug = Str::slug($article->title);
            }

            // Ensure slug is unique within the project
            $article->slug = static::generateUniqueSlug($article->slug, $article->project_id);
        });
    }

    /**
     * Generate a unique slug within the project by appending a number if needed.
     */
    public static function generateUniqueSlug(string $slug, int $projectId, ?int $excludeId = null): string
    {
        $originalSlug = $slug;
        $counter = 1;

        while (static::slugExistsInProject($slug, $projectId, $excludeId)) {
            $slug = $originalSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Check if a slug already exists within the project.
     */
    protected static function slugExistsInProject(string $slug, int $projectId, ?int $excludeId = null): bool
    {
        $query = static::where('project_id', $projectId)->where('slug', $slug);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function keyword(): BelongsTo
    {
        return $this->belongsTo(Keyword::class);
    }

    public function aiProvider(): BelongsTo
    {
        return $this->belongsTo(AiProvider::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(Image::class);
    }

    public function featuredImage(): ?Image
    {
        return $this->images()->where('type', 'featured')->first();
    }

    public function internalLinks(): BelongsToMany
    {
        return $this->belongsToMany(InternalLink::class)
            ->withPivot(['position', 'anchor_text_used'])
            ->withTimestamps();
    }

    public function publications(): HasMany
    {
        return $this->hasMany(Publication::class);
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }
}
