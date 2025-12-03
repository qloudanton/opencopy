<?php

use App\Models\Project;
use App\Models\ProjectPage;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::factory()->for($this->user)->create();
});

describe('index', function () {
    it('displays internal pages for project owner', function () {
        ProjectPage::factory()
            ->for($this->project)
            ->count(5)
            ->create();

        $response = $this->actingAs($this->user)
            ->get(route('projects.pages.index', $this->project));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Projects/Pages/Index')
                ->has('project')
                ->has('pages.data', 5)
                ->has('stats')
                ->has('filters')
                ->has('pageTypes')
            );
    });

    it('returns stats correctly', function () {
        ProjectPage::factory()->for($this->project)->blog()->count(3)->create();
        ProjectPage::factory()->for($this->project)->product()->count(2)->create();
        ProjectPage::factory()->for($this->project)->inactive()->create();

        $response = $this->actingAs($this->user)
            ->get(route('projects.pages.index', $this->project));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('stats.total', 6)
                ->where('stats.active', 5)
                ->where('stats.by_type.blog', 3)
                ->where('stats.by_type.product', 2)
            );
    });

    it('filters by page type', function () {
        ProjectPage::factory()->for($this->project)->blog()->count(3)->create();
        ProjectPage::factory()->for($this->project)->product()->count(2)->create();

        $response = $this->actingAs($this->user)
            ->get(route('projects.pages.index', ['project' => $this->project, 'page_type' => 'blog']));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('pages.data', 3)
                ->where('filters.page_type', 'blog')
            );
    });

    it('filters by search term', function () {
        ProjectPage::factory()->for($this->project)->create(['url' => 'https://example.com/unique-page']);
        ProjectPage::factory()->for($this->project)->count(4)->create();

        $response = $this->actingAs($this->user)
            ->get(route('projects.pages.index', ['project' => $this->project, 'search' => 'unique']));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('pages.data', 1)
            );
    });

    it('denies access to non-project owners', function () {
        $otherUser = User::factory()->create();

        $response = $this->actingAs($otherUser)
            ->get(route('projects.pages.index', $this->project));

        $response->assertForbidden();
    });

    it('requires authentication', function () {
        $response = $this->get(route('projects.pages.index', $this->project));

        $response->assertRedirect(route('login'));
    });
});

describe('store', function () {
    it('creates a new page', function () {
        $response = $this->actingAs($this->user)
            ->postJson(route('projects.pages.store', $this->project), [
                'url' => 'https://example.com/new-page',
                'title' => 'New Page Title',
                'page_type' => 'blog',
                'priority' => 0.8,
                'keywords' => ['seo', 'content'],
            ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('project_pages', [
            'project_id' => $this->project->id,
            'url' => 'https://example.com/new-page',
            'title' => 'New Page Title',
            'page_type' => 'blog',
            'is_active' => true,
        ]);
    });

    it('creates page with minimal data', function () {
        $response = $this->actingAs($this->user)
            ->postJson(route('projects.pages.store', $this->project), [
                'url' => 'https://example.com/minimal-page',
                'page_type' => 'other',
            ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('project_pages', [
            'project_id' => $this->project->id,
            'url' => 'https://example.com/minimal-page',
            'page_type' => 'other',
        ]);
    });

    it('validates url is required', function () {
        $response = $this->actingAs($this->user)
            ->postJson(route('projects.pages.store', $this->project), [
                'page_type' => 'blog',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['url']);
    });

    it('validates url format', function () {
        $response = $this->actingAs($this->user)
            ->postJson(route('projects.pages.store', $this->project), [
                'url' => 'not-a-valid-url',
                'page_type' => 'blog',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['url']);
    });

    it('validates page type is required', function () {
        $response = $this->actingAs($this->user)
            ->postJson(route('projects.pages.store', $this->project), [
                'url' => 'https://example.com/page',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['page_type']);
    });

    it('prevents duplicate urls for same project', function () {
        ProjectPage::factory()->for($this->project)->create([
            'url' => 'https://example.com/existing-page',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson(route('projects.pages.store', $this->project), [
                'url' => 'https://example.com/existing-page',
                'page_type' => 'blog',
            ]);

        $response->assertUnprocessable()
            ->assertJson(['success' => false]);
    });

    it('allows same url in different projects', function () {
        $otherProject = Project::factory()->for($this->user)->create();
        ProjectPage::factory()->for($otherProject)->create([
            'url' => 'https://example.com/shared-page',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson(route('projects.pages.store', $this->project), [
                'url' => 'https://example.com/shared-page',
                'page_type' => 'blog',
            ]);

        $response->assertOk()
            ->assertJson(['success' => true]);
    });

    it('denies store for non-project owners', function () {
        $otherUser = User::factory()->create();

        $response = $this->actingAs($otherUser)
            ->postJson(route('projects.pages.store', $this->project), [
                'url' => 'https://example.com/page',
                'page_type' => 'blog',
            ]);

        $response->assertForbidden();
    });
});

describe('update', function () {
    it('updates page type', function () {
        $page = ProjectPage::factory()
            ->for($this->project)
            ->blog()
            ->create();

        $response = $this->actingAs($this->user)
            ->putJson(route('projects.pages.update', [$this->project, $page]), [
                'page_type' => 'product',
            ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        $page->refresh();
        expect($page->page_type)->toBe('product');
    });

    it('updates active status', function () {
        $page = ProjectPage::factory()
            ->for($this->project)
            ->create(['is_active' => true]);

        $response = $this->actingAs($this->user)
            ->putJson(route('projects.pages.update', [$this->project, $page]), [
                'page_type' => $page->page_type,
                'is_active' => false,
            ]);

        $response->assertOk();

        $page->refresh();
        expect($page->is_active)->toBeFalse();
    });

    it('validates page type is required', function () {
        $page = ProjectPage::factory()
            ->for($this->project)
            ->create();

        $response = $this->actingAs($this->user)
            ->putJson(route('projects.pages.update', [$this->project, $page]), [
                'title' => 'New Title',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['page_type']);
    });

    it('updates priority', function () {
        $page = ProjectPage::factory()
            ->for($this->project)
            ->create(['priority' => 0.5]);

        $response = $this->actingAs($this->user)
            ->putJson(route('projects.pages.update', [$this->project, $page]), [
                'page_type' => $page->page_type,
                'priority' => 0.9,
            ]);

        $response->assertOk();

        $page->refresh();
        expect((float) $page->priority)->toBe(0.9);
    });

    it('validates priority is between 0 and 1', function () {
        $page = ProjectPage::factory()
            ->for($this->project)
            ->create();

        $response = $this->actingAs($this->user)
            ->putJson(route('projects.pages.update', [$this->project, $page]), [
                'page_type' => $page->page_type,
                'priority' => 1.5,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['priority']);
    });

    it('validates priority cannot be negative', function () {
        $page = ProjectPage::factory()
            ->for($this->project)
            ->create();

        $response = $this->actingAs($this->user)
            ->putJson(route('projects.pages.update', [$this->project, $page]), [
                'page_type' => $page->page_type,
                'priority' => -0.1,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['priority']);
    });

    it('updates keywords', function () {
        $page = ProjectPage::factory()
            ->for($this->project)
            ->create(['keywords' => ['old', 'keywords']]);

        $response = $this->actingAs($this->user)
            ->putJson(route('projects.pages.update', [$this->project, $page]), [
                'page_type' => $page->page_type,
                'keywords' => ['new', 'updated', 'keywords'],
            ]);

        $response->assertOk();

        $page->refresh();
        expect($page->keywords)->toBe(['new', 'updated', 'keywords']);
    });

    it('updates url', function () {
        $page = ProjectPage::factory()
            ->for($this->project)
            ->create(['url' => 'https://example.com/old-url']);

        $response = $this->actingAs($this->user)
            ->putJson(route('projects.pages.update', [$this->project, $page]), [
                'url' => 'https://example.com/new-url',
                'page_type' => $page->page_type,
            ]);

        $response->assertOk();

        $page->refresh();
        expect($page->url)->toBe('https://example.com/new-url');
    });

    it('prevents url update to existing url', function () {
        ProjectPage::factory()->for($this->project)->create([
            'url' => 'https://example.com/existing-url',
        ]);
        $page = ProjectPage::factory()->for($this->project)->create([
            'url' => 'https://example.com/my-url',
        ]);

        $response = $this->actingAs($this->user)
            ->putJson(route('projects.pages.update', [$this->project, $page]), [
                'url' => 'https://example.com/existing-url',
                'page_type' => $page->page_type,
            ]);

        $response->assertUnprocessable()
            ->assertJson(['success' => false]);
    });

    it('denies update for non-project owners', function () {
        $otherUser = User::factory()->create();
        $page = ProjectPage::factory()
            ->for($this->project)
            ->create();

        $response = $this->actingAs($otherUser)
            ->putJson(route('projects.pages.update', [$this->project, $page]), [
                'page_type' => 'blog',
            ]);

        $response->assertForbidden();
    });

    it('returns 404 for page from another project', function () {
        $otherProject = Project::factory()->for($this->user)->create();
        $page = ProjectPage::factory()
            ->for($otherProject)
            ->create();

        $response = $this->actingAs($this->user)
            ->putJson(route('projects.pages.update', [$this->project, $page]), [
                'page_type' => 'blog',
            ]);

        $response->assertNotFound();
    });
});

describe('destroy', function () {
    it('deletes a page', function () {
        $page = ProjectPage::factory()
            ->for($this->project)
            ->create();

        $response = $this->actingAs($this->user)
            ->deleteJson(route('projects.pages.destroy', [$this->project, $page]));

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('project_pages', [
            'id' => $page->id,
        ]);
    });

    it('denies deletion for non-project owners', function () {
        $otherUser = User::factory()->create();
        $page = ProjectPage::factory()
            ->for($this->project)
            ->create();

        $response = $this->actingAs($otherUser)
            ->deleteJson(route('projects.pages.destroy', [$this->project, $page]));

        $response->assertForbidden();
    });

    it('returns 404 for page from another project', function () {
        $otherProject = Project::factory()->for($this->user)->create();
        $page = ProjectPage::factory()
            ->for($otherProject)
            ->create();

        $response = $this->actingAs($this->user)
            ->deleteJson(route('projects.pages.destroy', [$this->project, $page]));

        $response->assertNotFound();
    });
});

describe('stats', function () {
    it('returns stats as json', function () {
        ProjectPage::factory()->for($this->project)->blog()->count(3)->create();
        ProjectPage::factory()->for($this->project)->product()->count(2)->create();

        $response = $this->actingAs($this->user)
            ->getJson(route('projects.pages.stats', $this->project));

        $response->assertOk()
            ->assertJsonStructure([
                'total',
                'active',
                'by_type' => ['blog', 'product', 'service', 'landing', 'other'],
                'total_links_distributed',
                'last_fetched',
            ]);
    });

    it('denies stats access to non-project owners', function () {
        $otherUser = User::factory()->create();

        $response = $this->actingAs($otherUser)
            ->getJson(route('projects.pages.stats', $this->project));

        $response->assertForbidden();
    });
});
