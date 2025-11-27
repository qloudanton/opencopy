<?php

use App\Models\AiProvider;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Skip Vite manifest check for tests
    $this->withoutVite();
});

it('requires authentication to access projects', function () {
    $this->get(route('projects.index'))
        ->assertRedirect(route('login'));
});

it('can list projects for authenticated user', function () {
    $user = User::factory()->create();
    $projects = Project::factory()->for($user)->count(3)->create();

    // Create projects for another user (should not be visible)
    Project::factory()->count(2)->create();

    $this->actingAs($user)
        ->get(route('projects.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Projects/Index')
            ->has('projects', 3)
        );
});

it('can view create project page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('projects.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Projects/Create'));
});

it('can create a new project', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('projects.store'), [
            'name' => 'My Tech Blog',
            'domain' => 'techblog.com',
            'description' => 'A blog about technology',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('projects', [
        'user_id' => $user->id,
        'name' => 'My Tech Blog',
        'domain' => 'techblog.com',
    ]);
});

it('validates required fields when creating project', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('projects.store'), [])
        ->assertSessionHasErrors(['name']);
});

it('can view own project', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('projects.show', $project))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Projects/Show')
            ->has('project')
            ->where('project.id', $project->id)
        );
});

it('cannot view another users project', function () {
    $user = User::factory()->create();
    $otherProject = Project::factory()->create();

    $this->actingAs($user)
        ->get(route('projects.show', $otherProject))
        ->assertForbidden();
});

it('can view edit project page', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('projects.edit', $project))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Projects/Edit')
            ->has('project')
        );
});

it('can update own project', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    $this->actingAs($user)
        ->put(route('projects.update', $project), [
            'name' => 'Updated Name',
            'domain' => 'updated.com',
        ])
        ->assertRedirect(route('projects.show', $project));

    expect($project->fresh()->name)->toBe('Updated Name');
});

it('cannot update another users project', function () {
    $user = User::factory()->create();
    $otherProject = Project::factory()->create();

    $this->actingAs($user)
        ->put(route('projects.update', $otherProject), [
            'name' => 'Hacked',
        ])
        ->assertForbidden();
});

it('can delete own project', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    $this->actingAs($user)
        ->delete(route('projects.destroy', $project))
        ->assertRedirect(route('projects.index'));

    $this->assertDatabaseMissing('projects', ['id' => $project->id]);
});

it('cannot delete another users project', function () {
    $user = User::factory()->create();
    $otherProject = Project::factory()->create();

    $this->actingAs($user)
        ->delete(route('projects.destroy', $otherProject))
        ->assertForbidden();

    $this->assertDatabaseHas('projects', ['id' => $otherProject->id]);
});

it('can view project settings page', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $aiProvider = AiProvider::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('projects.settings', $project))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Projects/Settings')
            ->has('project')
            ->has('aiProviders')
            ->where('project.id', $project->id)
        );
});

it('cannot view another users project settings', function () {
    $user = User::factory()->create();
    $otherProject = Project::factory()->create();

    $this->actingAs($user)
        ->get(route('projects.settings', $otherProject))
        ->assertForbidden();
});

it('can update own project settings', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $aiProvider = AiProvider::factory()->for($user)->create(['is_active' => true]);

    $this->actingAs($user)
        ->put(route('projects.settings.update', $project), [
            'default_ai_provider_id' => $aiProvider->id,
            'default_word_count' => 2000,
            'default_tone' => 'casual',
            'target_audience' => 'Tech enthusiasts',
            'brand_guidelines' => 'Use simple language',
            'primary_language' => 'en',
            'target_region' => 'us',
            'internal_links_per_article' => 5,
        ])
        ->assertRedirect(route('projects.settings', $project));

    $project->refresh();
    expect($project->default_ai_provider_id)->toBe($aiProvider->id);
    expect($project->default_word_count)->toBe(2000);
    expect($project->default_tone)->toBe('casual');
    expect($project->target_audience)->toBe('Tech enthusiasts');
    expect($project->brand_guidelines)->toBe('Use simple language');
    expect($project->primary_language)->toBe('en');
    expect($project->target_region)->toBe('us');
    expect($project->internal_links_per_article)->toBe(5);
});

it('cannot update another users project settings', function () {
    $user = User::factory()->create();
    $otherProject = Project::factory()->create();

    $this->actingAs($user)
        ->put(route('projects.settings.update', $otherProject), [
            'default_word_count' => 2000,
            'default_tone' => 'casual',
            'primary_language' => 'en',
            'internal_links_per_article' => 5,
        ])
        ->assertForbidden();
});

it('validates word count range in settings', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    $this->actingAs($user)
        ->put(route('projects.settings.update', $project), [
            'default_word_count' => 100, // too low
            'default_tone' => 'professional',
            'primary_language' => 'en',
            'internal_links_per_article' => 3,
        ])
        ->assertSessionHasErrors(['default_word_count']);

    $this->actingAs($user)
        ->put(route('projects.settings.update', $project), [
            'default_word_count' => 10000, // too high
            'default_tone' => 'professional',
            'primary_language' => 'en',
            'internal_links_per_article' => 3,
        ])
        ->assertSessionHasErrors(['default_word_count']);
});

it('validates tone enum in settings', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    $this->actingAs($user)
        ->put(route('projects.settings.update', $project), [
            'default_word_count' => 1500,
            'default_tone' => 'invalid-tone',
            'primary_language' => 'en',
            'internal_links_per_article' => 3,
        ])
        ->assertSessionHasErrors(['default_tone']);
});

it('validates ai provider belongs to user', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $otherUserProvider = AiProvider::factory()->create();

    $this->actingAs($user)
        ->put(route('projects.settings.update', $project), [
            'default_ai_provider_id' => $otherUserProvider->id,
            'default_word_count' => 1500,
            'default_tone' => 'professional',
            'primary_language' => 'en',
            'internal_links_per_article' => 3,
        ])
        ->assertSessionHasErrors(['default_ai_provider_id']);
});

it('allows null ai provider in settings', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create([
        'default_ai_provider_id' => null,
    ]);

    $this->actingAs($user)
        ->put(route('projects.settings.update', $project), [
            'default_ai_provider_id' => null,
            'default_word_count' => 1500,
            'default_tone' => 'professional',
            'primary_language' => 'en',
            'internal_links_per_article' => 3,
        ])
        ->assertRedirect(route('projects.settings', $project));

    expect($project->fresh()->default_ai_provider_id)->toBeNull();
});

it('can update engagement settings', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    $this->actingAs($user)
        ->put(route('projects.settings.update', $project), [
            'default_word_count' => 1500,
            'default_tone' => 'professional',
            'primary_language' => 'en',
            'internal_links_per_article' => 3,
            // Engagement settings
            'brand_color' => '#ff5500',
            'image_style' => 'watercolor',
            'include_youtube_videos' => true,
            'include_emojis' => true,
            'include_infographic_placeholders' => true,
            'include_cta' => true,
            'cta_product_name' => 'OpenCopy',
            'cta_website_url' => 'https://opencopy.ai',
            'cta_features' => 'AI content generation, SEO optimization',
            'cta_action_text' => 'Try for free',
        ])
        ->assertRedirect(route('projects.settings', $project));

    $project->refresh();
    expect($project->brand_color)->toBe('#ff5500');
    expect($project->image_style)->toBe('watercolor');
    expect($project->include_youtube_videos)->toBeTrue();
    expect($project->include_emojis)->toBeTrue();
    expect($project->include_infographic_placeholders)->toBeTrue();
    expect($project->include_cta)->toBeTrue();
    expect($project->cta_product_name)->toBe('OpenCopy');
    expect($project->cta_website_url)->toBe('https://opencopy.ai');
    expect($project->cta_features)->toBe('AI content generation, SEO optimization');
    expect($project->cta_action_text)->toBe('Try for free');
});

it('validates brand_color format', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    $this->actingAs($user)
        ->put(route('projects.settings.update', $project), [
            'default_word_count' => 1500,
            'default_tone' => 'professional',
            'primary_language' => 'en',
            'internal_links_per_article' => 3,
            'brand_color' => 'invalid-color',
        ])
        ->assertSessionHasErrors(['brand_color']);

    $this->actingAs($user)
        ->put(route('projects.settings.update', $project), [
            'default_word_count' => 1500,
            'default_tone' => 'professional',
            'primary_language' => 'en',
            'internal_links_per_article' => 3,
            'brand_color' => '#fff', // too short
        ])
        ->assertSessionHasErrors(['brand_color']);
});

it('validates image_style enum', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    $this->actingAs($user)
        ->put(route('projects.settings.update', $project), [
            'default_word_count' => 1500,
            'default_tone' => 'professional',
            'primary_language' => 'en',
            'internal_links_per_article' => 3,
            'image_style' => 'invalid-style',
        ])
        ->assertSessionHasErrors(['image_style']);
});

it('validates cta_website_url format', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    $this->actingAs($user)
        ->put(route('projects.settings.update', $project), [
            'default_word_count' => 1500,
            'default_tone' => 'professional',
            'primary_language' => 'en',
            'internal_links_per_article' => 3,
            'cta_website_url' => 'not-a-valid-url',
        ])
        ->assertSessionHasErrors(['cta_website_url']);
});

it('allows empty engagement settings', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    $this->actingAs($user)
        ->put(route('projects.settings.update', $project), [
            'default_word_count' => 1500,
            'default_tone' => 'professional',
            'primary_language' => 'en',
            'internal_links_per_article' => 3,
            'brand_color' => null,
            'include_cta' => false,
            'cta_product_name' => null,
            'cta_website_url' => null,
        ])
        ->assertRedirect(route('projects.settings', $project));

    $project->refresh();
    expect($project->brand_color)->toBeNull();
    expect($project->include_cta)->toBeFalse();
    expect($project->cta_product_name)->toBeNull();
});
