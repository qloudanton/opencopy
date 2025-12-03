<?php

use App\Enums\ContentStatus;
use App\Enums\ContentType;
use App\Models\Keyword;
use App\Models\Project;
use App\Models\ScheduledContent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

it('requires authentication to access content planner', function () {
    $project = Project::factory()->create();

    $this->get(route('projects.planner.index', $project))
        ->assertRedirect(route('login'));
});

it('can view content planner calendar for own project', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    ScheduledContent::factory()->forProject($project)->scheduled()->count(3)->create();
    ScheduledContent::factory()->forProject($project)->backlog()->count(2)->create();

    $this->actingAs($user)
        ->get(route('projects.planner.index', $project))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('ContentPlanner/Index')
            ->has('project')
            ->has('scheduledContents', 3)
            ->has('backlog', 2)
            ->has('stats')
            ->has('contentTypes')
            ->has('contentStatuses')
        );
});

it('cannot view content planner for another users project', function () {
    $user = User::factory()->create();
    $otherProject = Project::factory()->create();

    $this->actingAs($user)
        ->get(route('projects.planner.index', $otherProject))
        ->assertForbidden();
});

it('can add content to planner', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $keyword = Keyword::factory()->for($project)->create();

    $this->actingAs($user)
        ->post(route('projects.planner.store', $project), [
            'keyword_id' => $keyword->id,
            'title' => 'Test Article',
            'content_type' => ContentType::BlogPost->value,
            'scheduled_date' => now()->addDays(5)->format('Y-m-d'),
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('scheduled_contents', [
        'project_id' => $project->id,
        'keyword_id' => $keyword->id,
        'title' => 'Test Article',
        'content_type' => ContentType::BlogPost->value,
        'status' => ContentStatus::Scheduled->value,
    ]);
});

it('adds content to backlog when no date provided', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('projects.planner.store', $project), [
            'title' => 'Backlog Article',
            'content_type' => ContentType::Listicle->value,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('scheduled_contents', [
        'project_id' => $project->id,
        'title' => 'Backlog Article',
        'status' => ContentStatus::Backlog->value,
        'scheduled_date' => null,
    ]);
});

it('cannot add content to another users project', function () {
    $user = User::factory()->create();
    $otherProject = Project::factory()->create();

    $this->actingAs($user)
        ->post(route('projects.planner.store', $otherProject), [
            'title' => 'Hacked',
            'content_type' => ContentType::BlogPost->value,
        ])
        ->assertForbidden();
});

it('can update own scheduled content', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $content = ScheduledContent::factory()->forProject($project)->backlog()->create();

    $this->actingAs($user)
        ->put(route('projects.planner.update', [$project, $content]), [
            'title' => 'Updated Title',
            'content_type' => ContentType::HowTo->value,
        ])
        ->assertRedirect();

    expect($content->fresh())
        ->title->toBe('Updated Title')
        ->content_type->toBe(ContentType::HowTo);
});

it('cannot update content in another users project', function () {
    $user = User::factory()->create();
    $otherProject = Project::factory()->create();
    $content = ScheduledContent::factory()->forProject($otherProject)->create();

    $this->actingAs($user)
        ->put(route('projects.planner.update', [$otherProject, $content]), [
            'title' => 'Hacked',
        ])
        ->assertForbidden();
});

it('can delete own scheduled content', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $content = ScheduledContent::factory()->forProject($project)->create();

    $this->actingAs($user)
        ->delete(route('projects.planner.destroy', [$project, $content]))
        ->assertRedirect();

    $this->assertDatabaseMissing('scheduled_contents', ['id' => $content->id]);
});

it('cannot delete content from another users project', function () {
    $user = User::factory()->create();
    $otherProject = Project::factory()->create();
    $content = ScheduledContent::factory()->forProject($otherProject)->create();

    $this->actingAs($user)
        ->delete(route('projects.planner.destroy', [$otherProject, $content]))
        ->assertForbidden();

    $this->assertDatabaseHas('scheduled_contents', ['id' => $content->id]);
});

it('can schedule content to a specific date', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $content = ScheduledContent::factory()->forProject($project)->backlog()->create();

    $scheduledDate = now()->addWeek()->format('Y-m-d');

    $this->actingAs($user)
        ->postJson(route('projects.planner.schedule', [$project, $content]), [
            'scheduled_date' => $scheduledDate,
            'scheduled_time' => '10:00',
        ])
        ->assertOk()
        ->assertJson(['success' => true]);

    expect($content->fresh())
        ->status->toBe(ContentStatus::Scheduled)
        ->scheduled_date->format('Y-m-d')->toBe($scheduledDate);
});

it('can unschedule content and move to backlog', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $content = ScheduledContent::factory()->forProject($project)->scheduled()->create();

    $this->actingAs($user)
        ->postJson(route('projects.planner.unschedule', [$project, $content]))
        ->assertOk()
        ->assertJson(['success' => true]);

    expect($content->fresh())
        ->status->toBe(ContentStatus::Backlog)
        ->scheduled_date->toBeNull();
});

it('can update content status', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $content = ScheduledContent::factory()->forProject($project)->inReview()->create();

    $this->actingAs($user)
        ->postJson(route('projects.planner.update-status', [$project, $content]), [
            'status' => ContentStatus::Approved->value,
        ])
        ->assertOk()
        ->assertJson(['success' => true]);

    expect($content->fresh()->status)->toBe(ContentStatus::Approved);
});

it('rejects invalid status transition', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $content = ScheduledContent::factory()->forProject($project)->published()->create();

    // Published content cannot transition to any other status
    $this->actingAs($user)
        ->postJson(route('projects.planner.update-status', [$project, $content]), [
            'status' => ContentStatus::Scheduled->value,
        ])
        ->assertStatus(422);

    expect($content->fresh()->status)->toBe(ContentStatus::Published);
});

it('automatically adds new keywords to backlog via observer', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    // Create a keyword - observer should auto-create backlog entry
    $keyword = Keyword::factory()->for($project)->create([
        'keyword' => 'how to bake a cake',
    ]);

    // Verify backlog entry was created with correct content type
    $this->assertDatabaseHas('scheduled_contents', [
        'project_id' => $project->id,
        'keyword_id' => $keyword->id,
        'content_type' => ContentType::HowTo->value, // "how to" triggers HowTo type
        'status' => ContentStatus::Backlog->value,
    ]);
});

it('bulk add skips keywords already in planner', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    // Keywords are auto-added to planner via observer when created
    $keywords = Keyword::factory()->for($project)->count(3)->create();

    // Attempting bulk add should skip all (they're already in planner)
    $this->actingAs($user)
        ->post(route('projects.planner.bulk-add', $project), [
            'keyword_ids' => $keywords->pluck('id')->toArray(),
            'content_type' => ContentType::Comparison->value,
            'add_to_backlog' => true,
        ])
        ->assertRedirect();

    // Each keyword should still have only 1 scheduled content (from observer)
    foreach ($keywords as $keyword) {
        expect(ScheduledContent::where('keyword_id', $keyword->id)->count())->toBe(1);
    }
});

it('filters scheduled contents by date range', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    // Create content for different dates - use dates far apart to avoid month overlap
    ScheduledContent::factory()->forProject($project)->create([
        'scheduled_date' => now()->format('Y-m-d'),
        'status' => ContentStatus::Scheduled,
    ]);
    ScheduledContent::factory()->forProject($project)->create([
        'scheduled_date' => now()->addMonths(3)->format('Y-m-d'),
        'status' => ContentStatus::Scheduled,
    ]);

    // Request current month - should only include current month's content
    $this->actingAs($user)
        ->get(route('projects.planner.index', [
            'project' => $project,
            'view' => 'month',
            'date' => now()->format('Y-m-d'),
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('ContentPlanner/Index')
            ->has('scheduledContents', 1)
        );
});

it('includes pipeline stats', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    // Create content in various statuses
    ScheduledContent::factory()->forProject($project)->backlog()->count(2)->create();
    ScheduledContent::factory()->forProject($project)->scheduled()->count(3)->create();
    ScheduledContent::factory()->forProject($project)->inReview()->create();
    ScheduledContent::factory()->forProject($project)->approved()->create();
    ScheduledContent::factory()->forProject($project)->failed()->create();

    $this->actingAs($user)
        ->get(route('projects.planner.index', $project))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('ContentPlanner/Index')
            ->where('stats.backlog', 2)
            ->where('stats.scheduled', 3)
            ->where('stats.in_review', 1)
            ->where('stats.approved', 1)
            ->where('stats.failed', 1)
        );
});

it('auto-create returns zero when keywords already in planner via observer', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    // Create an AI provider for the user
    \App\Models\AiProvider::factory()->for($user)->create([
        'is_active' => true,
        'is_default' => true,
    ]);

    // Keywords are auto-added to planner via observer when created
    Keyword::factory()->for($project)->count(3)->create();

    // Auto-create should find no keywords to add (all already in planner)
    $this->actingAs($user)
        ->postJson(route('projects.planner.auto-create', $project))
        ->assertOk()
        ->assertJson([
            'success' => true,
            'created' => 0,
        ]);
});

it('returns error when no AI provider configured for auto-create', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    Keyword::factory()->for($project)->count(2)->create();

    $this->actingAs($user)
        ->postJson(route('projects.planner.auto-create', $project))
        ->assertStatus(422)
        ->assertJson([
            'success' => false,
        ]);
});

it('returns zero created when no available keywords for auto-create', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    \App\Models\AiProvider::factory()->for($user)->create([
        'is_active' => true,
        'is_default' => true,
    ]);

    // No keywords to add
    $this->actingAs($user)
        ->postJson(route('projects.planner.auto-create', $project))
        ->assertOk()
        ->assertJson([
            'success' => true,
            'created' => 0,
        ]);
});

it('can create keyword on the fly from planner', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    $this->actingAs($user)
        ->postJson(route('projects.planner.create-keyword', $project), [
            'keyword' => 'best seo tools 2024',
        ])
        ->assertOk()
        ->assertJson([
            'success' => true,
        ])
        ->assertJsonStructure([
            'keyword' => ['id', 'keyword', 'volume', 'difficulty'],
        ]);

    $this->assertDatabaseHas('keywords', [
        'project_id' => $project->id,
        'keyword' => 'best seo tools 2024',
    ]);
});

it('cannot create keyword for another users project', function () {
    $user = User::factory()->create();
    $otherProject = Project::factory()->create();

    $this->actingAs($user)
        ->postJson(route('projects.planner.create-keyword', $otherProject), [
            'keyword' => 'test keyword',
        ])
        ->assertForbidden();
});

it('can schedule an existing article', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $keyword = Keyword::factory()->for($project)->create();
    $article = \App\Models\Article::factory()->for($project)->for($keyword)->create([
        'title' => 'Test Article Title',
    ]);

    $this->actingAs($user)
        ->post(route('projects.planner.store', $project), [
            'article_id' => $article->id,
            'scheduled_date' => now()->addDays(5)->format('Y-m-d'),
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('scheduled_contents', [
        'project_id' => $project->id,
        'article_id' => $article->id,
        'keyword_id' => $keyword->id,
        'title' => 'Test Article Title',
        'status' => ContentStatus::Approved->value,
    ]);
});

it('can add existing article to backlog', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $article = \App\Models\Article::factory()->for($project)->create();

    $this->actingAs($user)
        ->post(route('projects.planner.store', $project), [
            'article_id' => $article->id,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('scheduled_contents', [
        'project_id' => $project->id,
        'article_id' => $article->id,
        'status' => ContentStatus::InReview->value,
        'scheduled_date' => null,
    ]);
});

it('returns all keywords with counts', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $keyword = Keyword::factory()->for($project)->create();

    // Create an article for this keyword
    \App\Models\Article::factory()->for($project)->for($keyword)->create();

    $this->actingAs($user)
        ->get(route('projects.planner.index', $project))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('ContentPlanner/Index')
            ->has('allKeywords', 1)
            ->where('allKeywords.0.articles_count', 1)
        );
});

it('returns unscheduled articles', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    // Create an article without scheduling it
    \App\Models\Article::factory()->for($project)->create();

    // Create another article and schedule it
    $scheduledArticle = \App\Models\Article::factory()->for($project)->create();
    ScheduledContent::factory()->forProject($project)->create([
        'article_id' => $scheduledArticle->id,
    ]);

    $this->actingAs($user)
        ->get(route('projects.planner.index', $project))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('ContentPlanner/Index')
            ->has('unscheduledArticles', 1)
        );
});
