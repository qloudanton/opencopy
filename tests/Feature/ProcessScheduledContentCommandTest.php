<?php

use App\Enums\ContentStatus;
use App\Jobs\GenerateArticleJob;
use App\Models\AiProvider;
use App\Models\Keyword;
use App\Models\Project;
use App\Models\ScheduledContent;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
});

it('processes scheduled content due today', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $aiProvider = AiProvider::factory()->for($user)->create([
        'is_active' => true,
        'supports_text' => true,
        'is_default' => true,
    ]);
    $keyword = Keyword::factory()->for($project)->create();

    $scheduledContent = ScheduledContent::factory()
        ->for($project)
        ->for($keyword)
        ->create([
            'status' => ContentStatus::Scheduled,
            'scheduled_date' => now(),
            'article_id' => null,
        ]);

    $this->artisan('content:process-scheduled --spread=0')
        ->assertSuccessful()
        ->expectsOutputToContain('1 dispatched');

    Queue::assertPushed(GenerateArticleJob::class, function ($job) use ($scheduledContent) {
        return $job->scheduledContent->id === $scheduledContent->id;
    });

    expect($scheduledContent->fresh()->status)->toBe(ContentStatus::Queued);
});

it('processes scheduled content due tomorrow with --days=1', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $aiProvider = AiProvider::factory()->for($user)->create([
        'is_active' => true,
        'supports_text' => true,
        'is_default' => true,
    ]);
    $keyword = Keyword::factory()->for($project)->create();

    $scheduledContent = ScheduledContent::factory()
        ->for($project)
        ->for($keyword)
        ->create([
            'status' => ContentStatus::Scheduled,
            'scheduled_date' => now()->addDay(),
            'article_id' => null,
        ]);

    $this->artisan('content:process-scheduled --days=1 --spread=0')
        ->assertSuccessful()
        ->expectsOutputToContain('1 dispatched');

    Queue::assertPushed(GenerateArticleJob::class);
});

it('skips content scheduled too far in the future', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $aiProvider = AiProvider::factory()->for($user)->create([
        'is_active' => true,
        'supports_text' => true,
        'is_default' => true,
    ]);
    $keyword = Keyword::factory()->for($project)->create();

    ScheduledContent::factory()
        ->for($project)
        ->for($keyword)
        ->create([
            'status' => ContentStatus::Scheduled,
            'scheduled_date' => now()->addDays(5),
            'article_id' => null,
        ]);

    $this->artisan('content:process-scheduled --days=1 --spread=0')
        ->assertSuccessful()
        ->expectsOutputToContain('No content ready to process');

    Queue::assertNotPushed(GenerateArticleJob::class);
});

it('skips content without a keyword', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    AiProvider::factory()->for($user)->create([
        'is_active' => true,
        'supports_text' => true,
        'is_default' => true,
    ]);

    ScheduledContent::factory()
        ->for($project)
        ->create([
            'status' => ContentStatus::Scheduled,
            'scheduled_date' => now(),
            'keyword_id' => null,
            'article_id' => null,
        ]);

    $this->artisan('content:process-scheduled --spread=0')
        ->assertSuccessful()
        ->expectsOutputToContain('No content ready to process');

    Queue::assertNotPushed(GenerateArticleJob::class);
});

it('skips content that already has an article', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $aiProvider = AiProvider::factory()->for($user)->create([
        'is_active' => true,
        'supports_text' => true,
        'is_default' => true,
    ]);
    $keyword = Keyword::factory()->for($project)->create();
    $article = \App\Models\Article::factory()->for($project)->for($keyword)->create();

    ScheduledContent::factory()
        ->for($project)
        ->for($keyword)
        ->for($article)
        ->create([
            'status' => ContentStatus::Scheduled,
            'scheduled_date' => now(),
        ]);

    $this->artisan('content:process-scheduled --spread=0')
        ->assertSuccessful()
        ->expectsOutputToContain('No content ready to process');

    Queue::assertNotPushed(GenerateArticleJob::class);
});

it('skips content not in scheduled status', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $aiProvider = AiProvider::factory()->for($user)->create([
        'is_active' => true,
        'supports_text' => true,
        'is_default' => true,
    ]);
    $keyword = Keyword::factory()->for($project)->create();

    ScheduledContent::factory()
        ->for($project)
        ->for($keyword)
        ->create([
            'status' => ContentStatus::Backlog,
            'scheduled_date' => now(),
            'article_id' => null,
        ]);

    $this->artisan('content:process-scheduled --spread=0')
        ->assertSuccessful()
        ->expectsOutputToContain('No content ready to process');

    Queue::assertNotPushed(GenerateArticleJob::class);
});

it('skips projects without an AI provider', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    // No AI provider created
    $keyword = Keyword::factory()->for($project)->create();

    ScheduledContent::factory()
        ->for($project)
        ->for($keyword)
        ->create([
            'status' => ContentStatus::Scheduled,
            'scheduled_date' => now(),
            'article_id' => null,
        ]);

    $this->artisan('content:process-scheduled --spread=0')
        ->assertSuccessful()
        ->expectsOutputToContain('No AI provider configured')
        ->expectsOutputToContain('0 dispatched, 1 skipped');

    Queue::assertNotPushed(GenerateArticleJob::class);
});

it('respects the limit option', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $aiProvider = AiProvider::factory()->for($user)->create([
        'is_active' => true,
        'supports_text' => true,
        'is_default' => true,
    ]);

    // Create 5 scheduled items
    for ($i = 0; $i < 5; $i++) {
        $keyword = Keyword::factory()->for($project)->create();
        ScheduledContent::factory()
            ->for($project)
            ->for($keyword)
            ->create([
                'status' => ContentStatus::Scheduled,
                'scheduled_date' => now(),
                'article_id' => null,
            ]);
    }

    $this->artisan('content:process-scheduled --limit=2 --spread=0')
        ->assertSuccessful()
        ->expectsOutputToContain('2 dispatched');

    Queue::assertPushed(GenerateArticleJob::class, 2);
});

it('does not dispatch jobs in dry-run mode', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $aiProvider = AiProvider::factory()->for($user)->create([
        'is_active' => true,
        'supports_text' => true,
        'is_default' => true,
    ]);
    $keyword = Keyword::factory()->for($project)->create();

    $scheduledContent = ScheduledContent::factory()
        ->for($project)
        ->for($keyword)
        ->create([
            'status' => ContentStatus::Scheduled,
            'scheduled_date' => now(),
            'article_id' => null,
        ]);

    $this->artisan('content:process-scheduled --dry-run --spread=0')
        ->assertSuccessful()
        ->expectsOutputToContain('[DRY RUN]')
        ->expectsOutputToContain('This was a dry run');

    Queue::assertNotPushed(GenerateArticleJob::class);

    // Status should remain unchanged
    expect($scheduledContent->fresh()->status)->toBe(ContentStatus::Scheduled);
});

it('spreads jobs over the specified time period', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $aiProvider = AiProvider::factory()->for($user)->create([
        'is_active' => true,
        'supports_text' => true,
        'is_default' => true,
    ]);

    // Create 3 scheduled items
    for ($i = 0; $i < 3; $i++) {
        $keyword = Keyword::factory()->for($project)->create();
        ScheduledContent::factory()
            ->for($project)
            ->for($keyword)
            ->create([
                'status' => ContentStatus::Scheduled,
                'scheduled_date' => now(),
                'article_id' => null,
            ]);
    }

    $this->artisan('content:process-scheduled --spread=60')
        ->assertSuccessful()
        ->expectsOutputToContain('Spreading jobs over 60 minutes')
        ->expectsOutputToContain('3 dispatched');

    Queue::assertPushed(GenerateArticleJob::class, 3);
});
