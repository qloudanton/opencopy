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
            'website_url' => 'https://techblog.com',
            'description' => 'A blog about technology',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('projects', [
        'user_id' => $user->id,
        'name' => 'My Tech Blog',
        'website_url' => 'https://techblog.com',
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

it('edit route redirects to settings', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    $this->actingAs($user)
        ->get("/projects/{$project->id}/edit")
        ->assertRedirect(route('projects.settings', $project));
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

    $this->actingAs($user)
        ->get(route('projects.settings', $project))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Projects/Settings/ProjectDetails')
            ->has('project')
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

// Content Settings Tests
it('can update content settings', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $aiProvider = AiProvider::factory()->for($user)->create(['is_active' => true, 'supports_text' => true]);

    $this->actingAs($user)
        ->put(route('projects.settings.content.update', $project), [
            'default_ai_provider_id' => $aiProvider->id,
            'default_word_count' => 2000,
            'default_tone' => 'casual',
            'target_audiences' => ['Tech enthusiasts', 'Startup founders'],
            'brand_guidelines' => 'Use simple language',
            'include_emojis' => true,
        ])
        ->assertRedirect();

    $project->refresh();
    expect($project->default_ai_provider_id)->toBe($aiProvider->id);
    expect($project->default_word_count)->toBe(2000);
    expect($project->default_tone)->toBe('casual');
    expect($project->target_audiences)->toBe(['Tech enthusiasts', 'Startup founders']);
    expect($project->brand_guidelines)->toBe('Use simple language');
    expect($project->include_emojis)->toBeTrue();
});

it('cannot update another users content settings', function () {
    $user = User::factory()->create();
    $otherProject = Project::factory()->create();

    $this->actingAs($user)
        ->put(route('projects.settings.content.update', $otherProject), [
            'default_word_count' => 2000,
            'default_tone' => 'casual',
        ])
        ->assertForbidden();
});

it('validates word count range in content settings', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    $this->actingAs($user)
        ->put(route('projects.settings.content.update', $project), [
            'default_word_count' => 100, // too low
            'default_tone' => 'professional',
        ])
        ->assertSessionHasErrors(['default_word_count']);

    $this->actingAs($user)
        ->put(route('projects.settings.content.update', $project), [
            'default_word_count' => 10000, // too high
            'default_tone' => 'professional',
        ])
        ->assertSessionHasErrors(['default_word_count']);
});

it('validates tone enum in content settings', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    $this->actingAs($user)
        ->put(route('projects.settings.content.update', $project), [
            'default_word_count' => 1500,
            'default_tone' => 'invalid-tone',
        ])
        ->assertSessionHasErrors(['default_tone']);
});

it('validates ai provider belongs to user in content settings', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $otherUserProvider = AiProvider::factory()->create(['supports_text' => true]);

    $this->actingAs($user)
        ->put(route('projects.settings.content.update', $project), [
            'default_ai_provider_id' => $otherUserProvider->id,
            'default_word_count' => 1500,
            'default_tone' => 'professional',
        ])
        ->assertSessionHasErrors(['default_ai_provider_id']);
});

it('allows null ai provider in content settings', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create([
        'default_ai_provider_id' => null,
    ]);

    $this->actingAs($user)
        ->put(route('projects.settings.content.update', $project), [
            'default_ai_provider_id' => null,
            'default_word_count' => 1500,
            'default_tone' => 'professional',
        ])
        ->assertRedirect();

    expect($project->fresh()->default_ai_provider_id)->toBeNull();
});

// Localization Settings Tests
it('can view localization settings page', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('projects.settings.localization', $project))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Projects/Settings/Localization')
            ->has('project')
            ->where('project.id', $project->id)
        );
});

it('can update localization settings', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    $this->actingAs($user)
        ->put(route('projects.settings.localization.update', $project), [
            'primary_language' => 'es',
            'target_region' => 'mx',
        ])
        ->assertRedirect();

    $project->refresh();
    expect($project->primary_language)->toBe('es');
    expect($project->target_region)->toBe('mx');
});

// Internal Linking Settings Tests
it('can view internal linking settings page', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('projects.settings.internal-linking', $project))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Projects/Settings/InternalLinking')
            ->has('project')
            ->has('pageStats')
            ->where('project.id', $project->id)
        );
});

it('can update internal linking settings', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    $this->actingAs($user)
        ->put(route('projects.settings.internal-linking.update', $project), [
            'internal_links_per_article' => 5,
            'sitemap_url' => 'https://example.com/sitemap.xml',
            'auto_internal_linking' => true,
            'prioritize_blog_links' => false,
            'cross_link_articles' => true,
        ])
        ->assertRedirect();

    $project->refresh();
    expect($project->internal_links_per_article)->toBe(5);
    expect($project->sitemap_url)->toBe('https://example.com/sitemap.xml');
    expect($project->auto_internal_linking)->toBeTrue();
    expect($project->prioritize_blog_links)->toBeFalse();
    expect($project->cross_link_articles)->toBeTrue();
});

// Media Settings Tests
it('can view media settings page', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('projects.settings.media', $project))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Projects/Settings/Media')
            ->has('project')
            ->where('project.id', $project->id)
        );
});

it('can update media settings', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    $this->actingAs($user)
        ->put(route('projects.settings.media.update', $project), [
            'generate_inline_images' => true,
            'generate_featured_image' => false,
            'brand_color' => '#ff5500',
            'image_style' => 'watercolor',
            'include_youtube_videos' => true,
            'include_infographic_placeholders' => true,
        ])
        ->assertRedirect();

    $project->refresh();
    expect($project->generate_inline_images)->toBeTrue();
    expect($project->generate_featured_image)->toBeFalse();
    expect($project->brand_color)->toBe('#ff5500');
    expect($project->image_style)->toBe('watercolor');
    expect($project->include_youtube_videos)->toBeTrue();
    expect($project->include_infographic_placeholders)->toBeTrue();
});

it('validates brand_color format in media settings', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    $this->actingAs($user)
        ->put(route('projects.settings.media.update', $project), [
            'brand_color' => 'invalid-color',
            'image_style' => 'illustration',
            'include_youtube_videos' => false,
            'include_infographic_placeholders' => false,
        ])
        ->assertSessionHasErrors(['brand_color']);
});

it('validates image_style enum in media settings', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    $this->actingAs($user)
        ->put(route('projects.settings.media.update', $project), [
            'image_style' => 'invalid-style',
            'include_youtube_videos' => false,
            'include_infographic_placeholders' => false,
        ])
        ->assertSessionHasErrors(['image_style']);
});

// Call-to-Action Settings Tests
it('can view call-to-action settings page', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('projects.settings.call-to-action', $project))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Projects/Settings/CallToAction')
            ->has('project')
            ->where('project.id', $project->id)
        );
});

it('can update call-to-action settings', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    $this->actingAs($user)
        ->put(route('projects.settings.call-to-action.update', $project), [
            'include_cta' => true,
            'cta_product_name' => 'OpenCopy',
            'cta_website_url' => 'https://opencopy.ai',
            'cta_features' => 'AI content generation, SEO optimization',
            'cta_action_text' => 'Try for free',
        ])
        ->assertRedirect();

    $project->refresh();
    expect($project->include_cta)->toBeTrue();
    expect($project->cta_product_name)->toBe('OpenCopy');
    expect($project->cta_website_url)->toBe('https://opencopy.ai');
    expect($project->cta_features)->toBe('AI content generation, SEO optimization');
    expect($project->cta_action_text)->toBe('Try for free');
});

it('validates cta_website_url format in call-to-action settings', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    $this->actingAs($user)
        ->put(route('projects.settings.call-to-action.update', $project), [
            'include_cta' => true,
            'cta_website_url' => 'not-a-valid-url',
        ])
        ->assertSessionHasErrors(['cta_website_url']);
});

it('allows empty call-to-action settings', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    $this->actingAs($user)
        ->put(route('projects.settings.call-to-action.update', $project), [
            'include_cta' => false,
            'cta_product_name' => null,
            'cta_website_url' => null,
        ])
        ->assertRedirect();

    $project->refresh();
    expect($project->include_cta)->toBeFalse();
    expect($project->cta_product_name)->toBeNull();
});

// Danger Zone Tests
it('can view danger zone page', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('projects.settings.danger-zone', $project))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Projects/Settings/DangerZone')
            ->has('project')
            ->where('project.id', $project->id)
        );
});
