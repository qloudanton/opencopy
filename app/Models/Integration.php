<?php

namespace App\Models;

use App\Enums\IntegrationType;
use App\Services\Publishing\PublisherFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Integration extends Model
{
    /** @use HasFactory<\Database\Factories\IntegrationFactory> */
    use HasFactory;

    protected $fillable = [
        'project_id',
        'type',
        'name',
        'credentials',
        'settings',
        'is_active',
        'last_connected_at',
    ];

    protected $hidden = [
        'credentials',
    ];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'settings' => 'array',
            'is_active' => 'boolean',
            'last_connected_at' => 'datetime',
        ];
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function publications(): HasMany
    {
        return $this->hasMany(Publication::class);
    }

    // =========================================================================
    // Type Helpers
    // =========================================================================

    /**
     * Get the integration type as an enum (if valid).
     */
    public function integrationType(): ?IntegrationType
    {
        return IntegrationType::tryFrom($this->type);
    }

    public function isWebhook(): bool
    {
        return $this->type === IntegrationType::Webhook->value;
    }

    public function isWordPress(): bool
    {
        return $this->type === IntegrationType::WordPress->value;
    }

    public function isWebflow(): bool
    {
        return $this->type === IntegrationType::Webflow->value;
    }

    // =========================================================================
    // Status Helpers
    // =========================================================================

    /**
     * Check if this integration has ever successfully connected.
     */
    public function hasConnected(): bool
    {
        return $this->last_connected_at !== null;
    }

    /**
     * Check if a publisher is available for this integration type.
     */
    public function hasPublisher(): bool
    {
        return app(PublisherFactory::class)->supports($this->type);
    }

    // =========================================================================
    // Credential Helpers
    // =========================================================================

    /**
     * Get a specific credential value.
     */
    public function getCredential(string $key, mixed $default = null): mixed
    {
        return $this->credentials[$key] ?? $default;
    }

    /**
     * Set a specific credential value.
     */
    public function setCredential(string $key, mixed $value): void
    {
        $credentials = $this->credentials ?? [];
        $credentials[$key] = $value;
        $this->credentials = $credentials;
    }

    /**
     * Check if all required credentials are set.
     */
    public function hasRequiredCredentials(): bool
    {
        $type = $this->integrationType();

        if (! $type) {
            return false;
        }

        foreach ($type->requiredCredentials() as $key => $config) {
            if (($config['required'] ?? false) && empty($this->getCredential($key))) {
                return false;
            }
        }

        return true;
    }

    // =========================================================================
    // Setting Helpers
    // =========================================================================

    /**
     * Get a specific setting value.
     */
    public function getSetting(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }

    /**
     * Set a specific setting value.
     */
    public function setSetting(string $key, mixed $value): void
    {
        $settings = $this->settings ?? [];
        $settings[$key] = $value;
        $this->settings = $settings;
    }

    // =========================================================================
    // Scopes
    // =========================================================================

    /**
     * Scope to active integrations only.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Integration>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Integration>
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to a specific type.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Integration>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Integration>
     */
    public function scopeOfType($query, string|IntegrationType $type)
    {
        $typeValue = $type instanceof IntegrationType ? $type->value : $type;

        return $query->where('type', $typeValue);
    }

    /**
     * Scope to webhooks only.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Integration>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Integration>
     */
    public function scopeWebhooks($query)
    {
        return $query->ofType(IntegrationType::Webhook);
    }
}
