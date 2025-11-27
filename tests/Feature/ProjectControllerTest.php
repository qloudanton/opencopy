<?php

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
