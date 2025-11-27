<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Image extends Model
{
    /** @use HasFactory<\Database\Factories\ImageFactory> */
    use HasFactory;

    protected $fillable = [
        'project_id',
        'article_id',
        'type',
        'source',
        'prompt',
        'path',
        'url',
        'alt_text',
        'caption',
        'width',
        'height',
        'file_size',
        'mime_type',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'width' => 'integer',
            'height' => 'integer',
            'file_size' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function isFeatured(): bool
    {
        return $this->type === 'featured';
    }

    public function isAiGenerated(): bool
    {
        return $this->source === 'ai_generated';
    }
}
