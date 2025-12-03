<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiProvider extends Model
{
    /** @use HasFactory<\Database\Factories\AiProviderFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'provider',
        'name',
        'api_key',
        'api_endpoint',
        'model',
        'settings',
        'is_default',
        'is_active',
        'supports_text',
        'supports_image',
    ];

    protected $hidden = [
        'api_key',
    ];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'settings' => 'array',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'supports_text' => 'boolean',
            'supports_image' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }
}
