<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsageLog extends Model
{
    /** @use HasFactory<\Database\Factories\UsageLogFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'article_id',
        'ai_provider_id',
        'operation',
        'model',
        'input_tokens',
        'output_tokens',
        'image_count',
        'image_size',
        'image_quality',
        'estimated_cost',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'image_count' => 'integer',
            'estimated_cost' => 'decimal:6',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function aiProvider(): BelongsTo
    {
        return $this->belongsTo(AiProvider::class);
    }
}
