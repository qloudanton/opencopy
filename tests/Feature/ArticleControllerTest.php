<?php

use App\Models\Article;
use App\Models\Integration;
use App\Models\Project;
use App\Models\Publication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('requires authentication to update article', function () {
    $project = Project::factory()->create();
    $article = Article::factory()->create(['project_id' => $project->id]);

    $response = $this->put("/projects/{$project->id}/articles/{$article->id}", [
        'title' => 'Updated Title',
        'slug' => 'updated-slug',
        'content' => 'Updated content',
    ]);

    $response->assertRedirect('/login');
});

it('forbids updating articles of other users', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $otherUser->id]);
    $article = Article::factory()->create(['project_id' => $project->id]);

    $response = $this->actingAs($user)->put("/projects/{$project->id}/articles/{$article->id}", [
        'title' => 'Updated Title',
        'slug' => 'updated-slug',
        'content' => 'Updated content',
    ]);

    $response->assertForbidden();
});

it('updates article with new slug', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $article = Article::factory()->create([
        'project_id' => $project->id,
        'title' => 'Original Title',
        'slug' => 'original-slug',
    ]);

    $response = $this->actingAs($user)->put("/projects/{$project->id}/articles/{$article->id}", [
        'title' => 'Updated Title',
        'slug' => 'new-custom-slug',
        'meta_description' => 'Updated meta description',
        'content' => 'Updated content here',
    ]);

    $response->assertRedirect("/projects/{$project->id}/articles/{$article->id}");

    $article->refresh();
    expect($article->title)->toBe('Updated Title');
    expect($article->slug)->toBe('new-custom-slug');
    expect($article->meta_description)->toBe('Updated meta description');
});

it('validates slug format', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $article = Article::factory()->create(['project_id' => $project->id]);

    $response = $this->actingAs($user)->put("/projects/{$project->id}/articles/{$article->id}", [
        'title' => 'Test Title',
        'slug' => 'Invalid Slug With Spaces!',
        'content' => 'Test content',
    ]);

    $response->assertSessionHasErrors(['slug']);
});

it('validates slug uniqueness', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $existingArticle = Article::factory()->create([
        'project_id' => $project->id,
        'slug' => 'existing-slug',
    ]);
    $article = Article::factory()->create([
        'project_id' => $project->id,
        'slug' => 'original-slug',
    ]);

    $response = $this->actingAs($user)->put("/projects/{$project->id}/articles/{$article->id}", [
        'title' => 'Test Title',
        'slug' => 'existing-slug',
        'content' => 'Test content',
    ]);

    $response->assertSessionHasErrors(['slug']);
});

it('allows keeping the same slug on update', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $article = Article::factory()->create([
        'project_id' => $project->id,
        'slug' => 'my-slug',
    ]);

    $response = $this->actingAs($user)->put("/projects/{$project->id}/articles/{$article->id}", [
        'title' => 'Updated Title',
        'slug' => 'my-slug',
        'content' => 'Updated content',
    ]);

    $response->assertRedirect("/projects/{$project->id}/articles/{$article->id}");
    $response->assertSessionHasNoErrors();

    $article->refresh();
    expect($article->slug)->toBe('my-slug');
});

describe('publish', function () {
    it('requires authentication', function () {
        $project = Project::factory()->create();
        $article = Article::factory()->create(['project_id' => $project->id]);

        $response = $this->postJson("/projects/{$project->id}/articles/{$article->id}/publish", [
            'integration_ids' => [1],
        ]);

        $response->assertUnauthorized();
    });

    it('publishes article to selected integrations', function () {
        Queue::fake();

        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $article = Article::factory()->for($project)->create();
        $integration = Integration::factory()
            ->for($project)
            ->webhook()
            ->create();

        $response = $this->actingAs($user)
            ->postJson("/projects/{$project->id}/articles/{$article->id}/publish", [
                'integration_ids' => [$integration->id],
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('publications', [
            'article_id' => $article->id,
            'integration_id' => $integration->id,
            'status' => 'pending',
        ]);

        Queue::assertPushed(\App\Jobs\PublishToIntegrationJob::class);
    });

    it('validates at least one integration is selected', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $article = Article::factory()->for($project)->create();

        $response = $this->actingAs($user)
            ->postJson("/projects/{$project->id}/articles/{$article->id}/publish", [
                'integration_ids' => [],
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['integration_ids']);
    });

    it('only publishes to active integrations', function () {
        Queue::fake();

        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $article = Article::factory()->for($project)->create();

        $activeIntegration = Integration::factory()
            ->for($project)
            ->webhook()
            ->create(['is_active' => true]);

        $inactiveIntegration = Integration::factory()
            ->for($project)
            ->webhook()
            ->inactive()
            ->create();

        $response = $this->actingAs($user)
            ->postJson("/projects/{$project->id}/articles/{$article->id}/publish", [
                'integration_ids' => [$activeIntegration->id, $inactiveIntegration->id],
            ]);

        $response->assertOk();

        // Only active integration should have a publication
        $this->assertDatabaseHas('publications', [
            'article_id' => $article->id,
            'integration_id' => $activeIntegration->id,
        ]);

        $this->assertDatabaseMissing('publications', [
            'article_id' => $article->id,
            'integration_id' => $inactiveIntegration->id,
        ]);
    });

    it('forbids publishing to other users projects', function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $project = Project::factory()->for($otherUser)->create();
        $article = Article::factory()->for($project)->create();

        $response = $this->actingAs($user)
            ->postJson("/projects/{$project->id}/articles/{$article->id}/publish", [
                'integration_ids' => [1],
            ]);

        $response->assertForbidden();
    });
});

describe('retry publication', function () {
    it('retries a failed publication', function () {
        Queue::fake();

        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $article = Article::factory()->for($project)->create();
        $integration = Integration::factory()
            ->for($project)
            ->webhook()
            ->create();

        $publication = Publication::factory()->create([
            'article_id' => $article->id,
            'integration_id' => $integration->id,
            'status' => 'failed',
            'error_message' => 'Connection timeout',
        ]);

        $response = $this->actingAs($user)
            ->postJson("/projects/{$project->id}/articles/{$article->id}/retry-publication", [
                'publication_id' => $publication->id,
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Retrying publication...',
            ]);

        $publication->refresh();
        expect($publication->status)->toBe('pending')
            ->and($publication->error_message)->toBeNull();

        Queue::assertPushed(\App\Jobs\PublishToIntegrationJob::class);
    });

    it('returns error if integration is inactive', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $article = Article::factory()->for($project)->create();
        $integration = Integration::factory()
            ->for($project)
            ->webhook()
            ->inactive()
            ->create();

        $publication = Publication::factory()->create([
            'article_id' => $article->id,
            'integration_id' => $integration->id,
            'status' => 'failed',
        ]);

        $response = $this->actingAs($user)
            ->postJson("/projects/{$project->id}/articles/{$article->id}/retry-publication", [
                'publication_id' => $publication->id,
            ]);

        $response->assertUnprocessable()
            ->assertJson([
                'error' => 'Integration is no longer active.',
            ]);
    });
});

describe('publication status', function () {
    it('returns publication status for article', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $article = Article::factory()->for($project)->create();
        $integration = Integration::factory()
            ->for($project)
            ->webhook()
            ->create(['name' => 'My Webhook']);

        $publication = Publication::factory()->create([
            'article_id' => $article->id,
            'integration_id' => $integration->id,
            'status' => 'published',
            'external_url' => 'https://example.com/post/123',
        ]);

        $response = $this->actingAs($user)
            ->getJson("/projects/{$project->id}/articles/{$article->id}/publication-status");

        $response->assertOk()
            ->assertJsonStructure([
                'publications' => [
                    '*' => [
                        'id',
                        'integration_id',
                        'integration_name',
                        'integration_type',
                        'status',
                        'external_url',
                        'error_message',
                        'published_at',
                        'created_at',
                    ],
                ],
            ]);

        expect($response->json('publications.0.status'))->toBe('published')
            ->and($response->json('publications.0.integration_name'))->toBe('My Webhook');
    });
});

describe('show page includes integrations', function () {
    it('includes integrations and publications in show page', function () {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $article = Article::factory()->for($project)->create();
        $integration = Integration::factory()
            ->for($project)
            ->webhook()
            ->create(['name' => 'Test Integration']);

        $response = $this->actingAs($user)
            ->get("/projects/{$project->id}/articles/{$article->id}");

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Articles/Show')
                ->has('integrations', 1)
                ->has('publications')
            );
    });
});
