<?php

use App\Models\AiProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

it('requires authentication to access ai providers', function () {
    $this->get(route('ai-providers.index'))
        ->assertRedirect(route('login'));
});

it('can list ai providers for authenticated user', function () {
    $user = User::factory()->create();
    $providers = AiProvider::factory()->for($user)->count(3)->create();

    // Create providers for another user (should not be visible)
    AiProvider::factory()->count(2)->create();

    $this->actingAs($user)
        ->get(route('ai-providers.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/ai-providers')
            ->has('providers', 3)
            ->has('availableProviders')
        );
});

it('can create a new ai provider', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('ai-providers.store'), [
            'provider' => 'openai',
            'name' => 'My OpenAI',
            'api_key' => 'sk-test-key',
            'model' => 'gpt-4o',
            'is_default' => true,
            'is_active' => true,
        ])
        ->assertRedirect(route('ai-providers.index'));

    $this->assertDatabaseHas('ai_providers', [
        'user_id' => $user->id,
        'provider' => 'openai',
        'name' => 'My OpenAI',
        'model' => 'gpt-4o',
        'is_default' => true,
    ]);
});

it('validates required fields when creating ai provider', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('ai-providers.store'), [])
        ->assertSessionHasErrors(['provider', 'name', 'model']);
});

it('validates provider enum', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('ai-providers.store'), [
            'provider' => 'invalid',
            'name' => 'Test',
            'model' => 'test',
        ])
        ->assertSessionHasErrors(['provider']);
});

it('can update own ai provider', function () {
    $user = User::factory()->create();
    $provider = AiProvider::factory()->for($user)->openai()->create();

    $this->actingAs($user)
        ->put(route('ai-providers.update', $provider), [
            'name' => 'Updated Name',
            'model' => 'gpt-4o-mini',
        ])
        ->assertRedirect(route('ai-providers.index'));

    expect($provider->fresh())
        ->name->toBe('Updated Name')
        ->model->toBe('gpt-4o-mini');
});

it('cannot update another users ai provider', function () {
    $user = User::factory()->create();
    $otherProvider = AiProvider::factory()->create();

    $this->actingAs($user)
        ->put(route('ai-providers.update', $otherProvider), [
            'name' => 'Hacked',
        ])
        ->assertForbidden();
});

it('keeps existing api key when updating without new key', function () {
    $user = User::factory()->create();
    $provider = AiProvider::factory()->for($user)->create([
        'api_key' => 'original-key',
    ]);

    $originalKey = $provider->api_key;

    $this->actingAs($user)
        ->put(route('ai-providers.update', $provider), [
            'name' => 'Updated Name',
            'api_key' => '', // Empty - should keep original
        ])
        ->assertRedirect();

    expect($provider->fresh()->api_key)->toBe($originalKey);
});

it('can delete own ai provider', function () {
    $user = User::factory()->create();
    $provider = AiProvider::factory()->for($user)->create();

    $this->actingAs($user)
        ->delete(route('ai-providers.destroy', $provider))
        ->assertRedirect(route('ai-providers.index'));

    $this->assertDatabaseMissing('ai_providers', ['id' => $provider->id]);
});

it('cannot delete another users ai provider', function () {
    $user = User::factory()->create();
    $otherProvider = AiProvider::factory()->create();

    $this->actingAs($user)
        ->delete(route('ai-providers.destroy', $otherProvider))
        ->assertForbidden();

    $this->assertDatabaseHas('ai_providers', ['id' => $otherProvider->id]);
});

it('can set provider as default', function () {
    $user = User::factory()->create();
    $provider1 = AiProvider::factory()->for($user)->default()->create();
    $provider2 = AiProvider::factory()->for($user)->create(['is_default' => false]);

    $this->actingAs($user)
        ->post(route('ai-providers.set-default', $provider2))
        ->assertRedirect(route('ai-providers.index'));

    expect($provider1->fresh()->is_default)->toBeFalse();
    expect($provider2->fresh()->is_default)->toBeTrue();
});

it('cannot set another users provider as default', function () {
    $user = User::factory()->create();
    $otherProvider = AiProvider::factory()->create();

    $this->actingAs($user)
        ->post(route('ai-providers.set-default', $otherProvider))
        ->assertForbidden();
});

it('unsets other defaults when creating new default provider', function () {
    $user = User::factory()->create();
    $existingDefault = AiProvider::factory()->for($user)->default()->create();

    $this->actingAs($user)
        ->post(route('ai-providers.store'), [
            'provider' => 'anthropic',
            'name' => 'New Default',
            'api_key' => 'sk-test',
            'model' => 'claude-sonnet-4-20250514',
            'is_default' => true,
            'is_active' => true,
        ])
        ->assertRedirect();

    expect($existingDefault->fresh()->is_default)->toBeFalse();
});

it('encrypts api key when stored', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('ai-providers.store'), [
            'provider' => 'openai',
            'name' => 'Test',
            'api_key' => 'sk-my-secret-key',
            'model' => 'gpt-4o',
        ])
        ->assertRedirect();

    $provider = AiProvider::where('name', 'Test')->first();

    // The raw database value should be encrypted (not the plain text)
    $rawValue = \DB::table('ai_providers')
        ->where('id', $provider->id)
        ->value('api_key');

    expect($rawValue)->not->toBe('sk-my-secret-key');

    // But accessing via model should decrypt it
    expect($provider->api_key)->toBe('sk-my-secret-key');
});
