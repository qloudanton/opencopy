<?php

namespace App\Models;

use App\Contracts\Publishing\PublishableContract;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Article extends Model implements PublishableContract
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

    public function scheduledContent(): HasOne
    {
        return $this->hasOne(ScheduledContent::class);
    }

    public function usageLogs(): HasMany
    {
        return $this->hasMany(UsageLog::class);
    }

    public function totalCost(): float
    {
        return (float) $this->usageLogs()->sum('estimated_cost');
    }

    // =========================================================================
    // PublishableContract Implementation
    // =========================================================================

    public function getPublishableId(): int
    {
        return $this->id;
    }

    public function getPublishableTitle(): string
    {
        return $this->title;
    }

    public function getPublishableSlug(): string
    {
        return $this->slug;
    }

    public function getPublishableHtml(): string
    {
        return $this->content ?? '';
    }

    public function getPublishableMarkdown(): string
    {
        return $this->content_markdown ?? '';
    }

    public function getPublishableMetaDescription(): ?string
    {
        return $this->meta_description;
    }

    public function getPublishableExcerpt(): ?string
    {
        return $this->excerpt;
    }

    public function getPublishableFeaturedImageUrl(): ?string
    {
        $featuredImage = $this->featuredImage();

        return $featuredImage?->url ?? $featuredImage?->path;
    }

    /**
     * @return array<string>
     */
    public function getPublishableTags(): array
    {
        // Extract tags from keyword's secondary_keywords if available
        $tags = [];

        if ($this->keyword) {
            $tags[] = $this->keyword->keyword;

            if (is_array($this->keyword->secondary_keywords)) {
                $tags = array_merge($tags, $this->keyword->secondary_keywords);
            }
        }

        return array_unique(array_filter($tags));
    }

    public function getPublishableCreatedAt(): DateTimeInterface
    {
        return $this->created_at;
    }

    /**
     * @return array<string, mixed>
     */
    public function toPublishableArray(): array
    {
        return [
            'id' => $this->getPublishableId(),
            'title' => $this->getPublishableTitle(),
            'slug' => $this->getPublishableSlug(),
            'content_html' => $this->getPublishableHtml(),
            'content_markdown' => $this->getPublishableMarkdown(),
            'meta_description' => $this->getPublishableMetaDescription(),
            'excerpt' => $this->getPublishableExcerpt(),
            'image_url' => $this->getPublishableFeaturedImageUrl(),
            'tags' => $this->getPublishableTags(),
            'created_at' => $this->getPublishableCreatedAt()->format('c'),
            'word_count' => $this->word_count,
            'reading_time_minutes' => $this->reading_time_minutes,
        ];
    }
}
