<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function aiProviders(): HasMany
    {
        return $this->hasMany(AiProvider::class);
    }

    public function settings(): HasOne
    {
        return $this->hasOne(UserSetting::class);
    }

    public function defaultAiProvider(): ?AiProvider
    {
        return $this->aiProviders()->where('is_default', true)->where('is_active', true)->first();
    }

    /**
     * Get the user's settings, creating them if they don't exist.
     */
    public function getOrCreateSettings(): UserSetting
    {
        return $this->settings ?? $this->settings()->create(['user_id' => $this->id]);
    }

    /**
     * Get the default text provider from user settings, or fall back to the default provider.
     */
    public function getDefaultTextProvider(): ?AiProvider
    {
        $settings = $this->settings;

        if ($settings?->default_text_provider_id) {
            return $settings->defaultTextProvider;
        }

        return $this->defaultAiProvider();
    }

    /**
     * Get the default image provider from user settings, or fall back to the default provider.
     */
    public function getDefaultImageProvider(): ?AiProvider
    {
        $settings = $this->settings;

        if ($settings?->default_image_provider_id) {
            return $settings->defaultImageProvider;
        }

        // Fall back to any provider that supports images
        return $this->aiProviders()
            ->where('is_active', true)
            ->where('supports_image', true)
            ->first() ?? $this->defaultAiProvider();
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}
