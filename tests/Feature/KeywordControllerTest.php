<?php

use App\Jobs\GenerateArticleJob;
use App\Models\AiProvider;
use App\Models\Keyword;
use App\Models\Project;
use App\Models\ScheduledContent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

it('requires authentication to access keywords', function () {
    $project = Project::factory()->create();

    $this->get(route('projects.keywords.index', $project))
        ->assertRedirect(route('login'));
});

it('can list keywords for own project', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $keywords = Keyword::factory()->for($project)->count(3)->create();

    $this->actingAs($user)
        ->get(route('projects.keywords.index', $project))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Keywords/Index')
            ->has('project')
            ->has('keywords.data', 3)
        );
});

it('cannot list keywords for another users project', function () {
    $user = User::factory()->create();
    $otherProject = Project::factory()->create();

    $this->actingAs($user)
        ->get(route('projects.keywords.index', $otherProject))
        ->assertForbidden();
});

it('can view create keyword page', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('projects.keywords.create', $project))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Keywords/Create')
            ->has('project')
        );
});

it('can create a new keyword', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('projects.keywords.store', $project), [
            'keyword' => 'best coffee machines',
            'secondary_keywords' => ['espresso', 'drip coffee'],
            'search_intent' => 'commercial',
            'target_word_count' => 1500,
            'tone' => 'professional',
            'priority' => 75,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('keywords', [
        'project_id' => $project->id,
        'keyword' => 'best coffee machines',
        'search_intent' => 'commercial',
        'target_word_count' => 1500,
    ]);
});

it('cannot create keyword in another users project', function () {
    $user = User::factory()->create();
    $otherProject = Project::factory()->create();

    $this->actingAs($user)
        ->post(route('projects.keywords.store', $otherProject), [
            'keyword' => 'test keyword',
        ])
        ->assertForbidden();
});

it('validates required fields when creating keyword', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('projects.keywords.store', $project), [])
        ->assertSessionHasErrors(['keyword']);
});

it('validates word count range', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('projects.keywords.store', $project), [
            'keyword' => 'test',
            'target_word_count' => 100, // below minimum
        ])
        ->assertSessionHasErrors(['target_word_count']);

    $this->actingAs($user)
        ->post(route('projects.keywords.store', $project), [
            'keyword' => 'test',
            'target_word_count' => 20000, // above maximum
        ])
        ->assertSessionHasErrors(['target_word_count']);
});

it('validates search intent enum', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('projects.keywords.store', $project), [
            'keyword' => 'test',
            'search_intent' => 'invalid',
        ])
        ->assertSessionHasErrors(['search_intent']);
});

it('can view own keyword', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $keyword = Keyword::factory()->for($project)->create();

    $this->actingAs($user)
        ->get(route('projects.keywords.show', [$project, $keyword]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Keywords/Show')
            ->has('project')
            ->has('keyword')
            ->where('keyword.id', $keyword->id)
        );
});

it('cannot view keyword in another users project', function () {
    $user = User::factory()->create();
    $otherProject = Project::factory()->create();
    $keyword = Keyword::factory()->for($otherProject)->create();

    $this->actingAs($user)
        ->get(route('projects.keywords.show', [$otherProject, $keyword]))
        ->assertForbidden();
});

it('can view edit keyword page', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $keyword = Keyword::factory()->for($project)->create();

    $this->actingAs($user)
        ->get(route('projects.keywords.edit', [$project, $keyword]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Keywords/Edit')
            ->has('project')
            ->has('keyword')
        );
});

it('can update own keyword', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $keyword = Keyword::factory()->for($project)->create();

    $this->actingAs($user)
        ->put(route('projects.keywords.update', [$project, $keyword]), [
            'keyword' => 'updated keyword',
            'priority' => 99,
        ])
        ->assertRedirect(route('projects.keywords.show', [$project, $keyword]));

    expect($keyword->fresh())
        ->keyword->toBe('updated keyword')
        ->priority->toBe(99);
});

it('cannot update keyword in another users project', function () {
    $user = User::factory()->create();
    $otherProject = Project::factory()->create();
    $keyword = Keyword::factory()->for($otherProject)->create();

    $this->actingAs($user)
        ->put(route('projects.keywords.update', [$otherProject, $keyword]), [
            'keyword' => 'hacked',
        ])
        ->assertForbidden();
});

it('can delete own keyword', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $keyword = Keyword::factory()->for($project)->create();

    $this->actingAs($user)
        ->delete(route('projects.keywords.destroy', [$project, $keyword]))
        ->assertRedirect(route('projects.keywords.index', $project));

    $this->assertDatabaseMissing('keywords', ['id' => $keyword->id]);
});

it('cannot delete keyword in another users project', function () {
    $user = User::factory()->create();
    $otherProject = Project::factory()->create();
    $keyword = Keyword::factory()->for($otherProject)->create();

    $this->actingAs($user)
        ->delete(route('projects.keywords.destroy', [$otherProject, $keyword]))
        ->assertForbidden();

    $this->assertDatabaseHas('keywords', ['id' => $keyword->id]);
});

it('stores secondary keywords as array', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('projects.keywords.store', $project), [
            'keyword' => 'main keyword',
            'secondary_keywords' => ['secondary one', 'secondary two'],
        ])
        ->assertRedirect();

    $keyword = Keyword::where('keyword', 'main keyword')->first();
    expect($keyword->secondary_keywords)->toBeArray()->toHaveCount(2);
});

it('can view all keywords across projects', function () {
    $user = User::factory()->create();
    $project1 = Project::factory()->for($user)->create();
    $project2 = Project::factory()->for($user)->create();
    Keyword::factory()->for($project1)->count(2)->create();
    Keyword::factory()->for($project2)->count(3)->create();

    // Create keywords for another user (should not be visible)
    $otherProject = Project::factory()->create();
    Keyword::factory()->for($otherProject)->count(2)->create();

    $this->actingAs($user)
        ->get(route('keywords.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Keywords/IndexAll')
            ->has('keywords.data', 5)
        );
});

it('can queue article generation for own keyword', function () {
    Queue::fake();

    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    AiProvider::factory()->for($user)->default()->create();
    $keyword = Keyword::factory()->for($project)->create();

    $this->actingAs($user)
        ->post(route('projects.keywords.generate', [$project, $keyword]))
        ->assertRedirect();

    Queue::assertPushed(GenerateArticleJob::class, function ($job) use ($keyword) {
        return $job->scheduledContent->keyword_id === $keyword->id;
    });

    // Verify a ScheduledContent was created with queued status
    $this->assertDatabaseHas('scheduled_contents', [
        'keyword_id' => $keyword->id,
        'project_id' => $project->id,
        'status' => 'queued',
    ]);
});

it('cannot queue generation for another users keyword', function () {
    Queue::fake();

    $user = User::factory()->create();
    $otherProject = Project::factory()->create();
    $keyword = Keyword::factory()->for($otherProject)->create();

    $this->actingAs($user)
        ->post(route('projects.keywords.generate', [$otherProject, $keyword]))
        ->assertForbidden();

    Queue::assertNothingPushed();
});

it('cannot queue generation when keyword is already generating', function () {
    Queue::fake();

    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    AiProvider::factory()->for($user)->default()->create();
    $keyword = Keyword::factory()->for($project)->create();
    ScheduledContent::factory()
        ->withKeyword($keyword)
        ->generating()
        ->create();

    $this->actingAs($user)
        ->post(route('projects.keywords.generate', [$project, $keyword]))
        ->assertRedirect()
        ->assertSessionHas('error');

    Queue::assertNothingPushed();
});

it('redirects to ai providers settings when no provider configured', function () {
    Queue::fake();

    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $keyword = Keyword::factory()->for($project)->create();

    $this->actingAs($user)
        ->post(route('projects.keywords.generate', [$project, $keyword]))
        ->assertRedirect(route('ai-providers.index'))
        ->assertSessionHas('error');

    Queue::assertNothingPushed();
});
