<?php

use App\Models\Integration;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::factory()->for($this->user)->create();
});

describe('index', function () {
    it('displays integrations page for project owner', function () {
        $integrations = Integration::factory()
            ->for($this->project)
            ->webhook()
            ->count(3)
            ->create();

        $response = $this->actingAs($this->user)
            ->get(route('projects.integrations.index', $this->project));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Projects/Integrations/Index')
                ->has('project')
                ->has('integrations', 3)
                ->has('availableTypes')
            );
    });

    it('returns integration data with correct structure', function () {
        $integration = Integration::factory()
            ->for($this->project)
            ->webhook()
            ->create(['name' => 'My Webhook']);

        $response = $this->actingAs($this->user)
            ->get(route('projects.integrations.index', $this->project));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('integrations.0', fn ($integration) => $integration
                    ->has('id')
                    ->has('type')
                    ->where('name', 'My Webhook')
                    ->has('is_active')
                    ->has('has_credentials')
                    ->has('last_connected_at')
                    ->has('publications_count')
                    ->has('created_at')
                )
            );
    });

    it('denies access to non-project owners', function () {
        $otherUser = User::factory()->create();

        $response = $this->actingAs($otherUser)
            ->get(route('projects.integrations.index', $this->project));

        $response->assertForbidden();
    });

    it('requires authentication', function () {
        $response = $this->get(route('projects.integrations.index', $this->project));

        $response->assertRedirect(route('login'));
    });
});

describe('store', function () {
    it('creates a webhook integration', function () {
        $response = $this->actingAs($this->user)
            ->post(route('projects.integrations.store', $this->project), [
                'type' => 'webhook',
                'name' => 'My Webhook',
                'endpoint_url' => 'https://example.com/webhook',
                'access_token' => 'secret-token-12345678',
                'timeout' => 30,
                'retry_times' => 3,
                'is_active' => true,
            ]);

        $response->assertRedirect(route('projects.integrations.index', $this->project));

        $this->assertDatabaseHas('integrations', [
            'project_id' => $this->project->id,
            'type' => 'webhook',
            'name' => 'My Webhook',
            'is_active' => true,
        ]);

        $integration = Integration::where('project_id', $this->project->id)->first();
        expect($integration->credentials)->toHaveKey('endpoint_url')
            ->and($integration->credentials['endpoint_url'])->toBe('https://example.com/webhook');
    });

    it('validates required fields for webhook type', function () {
        $response = $this->actingAs($this->user)
            ->post(route('projects.integrations.store', $this->project), [
                'type' => 'webhook',
                'name' => 'My Webhook',
            ]);

        $response->assertSessionHasErrors(['endpoint_url', 'access_token']);
    });

    it('validates webhook url must be https', function () {
        $response = $this->actingAs($this->user)
            ->post(route('projects.integrations.store', $this->project), [
                'type' => 'webhook',
                'name' => 'My Webhook',
                'endpoint_url' => 'http://example.com/webhook',
                'access_token' => 'secret-token-12345678',
            ]);

        $response->assertSessionHasErrors(['endpoint_url']);
    });

    it('validates access token minimum length', function () {
        $response = $this->actingAs($this->user)
            ->post(route('projects.integrations.store', $this->project), [
                'type' => 'webhook',
                'name' => 'My Webhook',
                'endpoint_url' => 'https://example.com/webhook',
                'access_token' => 'short',
            ]);

        $response->assertSessionHasErrors(['access_token']);
    });

    it('validates name is required', function () {
        $response = $this->actingAs($this->user)
            ->post(route('projects.integrations.store', $this->project), [
                'type' => 'webhook',
                'endpoint_url' => 'https://example.com/webhook',
                'access_token' => 'secret-token-12345678',
            ]);

        $response->assertSessionHasErrors(['name']);
    });

    it('validates integration type must be valid', function () {
        $response = $this->actingAs($this->user)
            ->post(route('projects.integrations.store', $this->project), [
                'type' => 'invalid-type',
                'name' => 'Test',
            ]);

        $response->assertSessionHasErrors(['type']);
    });

    it('denies creation for non-project owners', function () {
        $otherUser = User::factory()->create();

        $response = $this->actingAs($otherUser)
            ->post(route('projects.integrations.store', $this->project), [
                'type' => 'webhook',
                'name' => 'My Webhook',
                'endpoint_url' => 'https://example.com/webhook',
                'access_token' => 'secret-token-12345678',
            ]);

        $response->assertForbidden();
    });
});

describe('update', function () {
    it('updates an integration name', function () {
        $integration = Integration::factory()
            ->for($this->project)
            ->webhook()
            ->create(['name' => 'Old Name']);

        $response = $this->actingAs($this->user)
            ->put(route('projects.integrations.update', [$this->project, $integration]), [
                'name' => 'New Name',
            ]);

        $response->assertRedirect(route('projects.integrations.index', $this->project));

        $this->assertDatabaseHas('integrations', [
            'id' => $integration->id,
            'name' => 'New Name',
        ]);
    });

    it('updates credentials when provided', function () {
        $integration = Integration::factory()
            ->for($this->project)
            ->webhook()
            ->create();

        $response = $this->actingAs($this->user)
            ->put(route('projects.integrations.update', [$this->project, $integration]), [
                'name' => $integration->name,
                'endpoint_url' => 'https://new-url.com/webhook',
            ]);

        $response->assertRedirect();

        $integration->refresh();
        expect($integration->credentials['endpoint_url'])->toBe('https://new-url.com/webhook');
    });

    it('preserves existing credentials when not provided', function () {
        $integration = Integration::factory()
            ->for($this->project)
            ->webhook()
            ->create([
                'credentials' => [
                    'endpoint_url' => 'https://original.com/webhook',
                    'access_token' => 'original-token',
                ],
            ]);

        $originalToken = $integration->credentials['access_token'];

        $response = $this->actingAs($this->user)
            ->put(route('projects.integrations.update', [$this->project, $integration]), [
                'name' => 'Updated Name',
            ]);

        $response->assertRedirect();

        $integration->refresh();
        expect($integration->credentials['access_token'])->toBe($originalToken);
    });

    it('denies update for non-project owners', function () {
        $otherUser = User::factory()->create();
        $integration = Integration::factory()
            ->for($this->project)
            ->webhook()
            ->create();

        $response = $this->actingAs($otherUser)
            ->put(route('projects.integrations.update', [$this->project, $integration]), [
                'name' => 'New Name',
            ]);

        $response->assertForbidden();
    });

    it('returns 404 for integration from another project', function () {
        $otherProject = Project::factory()->for($this->user)->create();
        $integration = Integration::factory()
            ->for($otherProject)
            ->webhook()
            ->create();

        $response = $this->actingAs($this->user)
            ->put(route('projects.integrations.update', [$this->project, $integration]), [
                'name' => 'New Name',
            ]);

        $response->assertNotFound();
    });
});

describe('destroy', function () {
    it('deletes an integration', function () {
        $integration = Integration::factory()
            ->for($this->project)
            ->webhook()
            ->create();

        $response = $this->actingAs($this->user)
            ->delete(route('projects.integrations.destroy', [$this->project, $integration]));

        $response->assertRedirect(route('projects.integrations.index', $this->project));

        $this->assertDatabaseMissing('integrations', [
            'id' => $integration->id,
        ]);
    });

    it('denies deletion for non-project owners', function () {
        $otherUser = User::factory()->create();
        $integration = Integration::factory()
            ->for($this->project)
            ->webhook()
            ->create();

        $response = $this->actingAs($otherUser)
            ->delete(route('projects.integrations.destroy', [$this->project, $integration]));

        $response->assertForbidden();
    });
});

describe('test connection', function () {
    it('tests a webhook integration successfully', function () {
        Http::fake([
            '*' => Http::response(['status' => 'ok'], 200, ['X-Custom-Header' => 'test']),
        ]);

        $integration = Integration::factory()
            ->for($this->project)
            ->webhook()
            ->create();

        $response = $this->actingAs($this->user)
            ->postJson(route('projects.integrations.test', [$this->project, $integration]));

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Connection successful!',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'request' => ['method', 'url', 'headers', 'body'],
                'response' => ['status_code', 'status_text', 'headers', 'body'],
            ]);

        // Verify request details
        $data = $response->json();
        expect($data['request']['method'])->toBe('POST')
            ->and($data['request']['url'])->toContain('https://')
            ->and($data['request']['headers'])->toHaveKey('Authorization')
            ->and($data['request']['headers']['Authorization'])->toBe('Bearer ••••••••');

        // Verify response details
        expect($data['response']['status_code'])->toBe(200)
            ->and($data['response']['status_text'])->toBe('OK')
            ->and($data['response']['headers'])->toHaveKey('X-Custom-Header');
    });

    it('handles failed webhook test', function () {
        Http::fake([
            '*' => Http::response(['error' => 'Unauthorized'], 401),
        ]);

        $integration = Integration::factory()
            ->for($this->project)
            ->webhook()
            ->create();

        $response = $this->actingAs($this->user)
            ->postJson(route('projects.integrations.test', [$this->project, $integration]));

        $response->assertOk()
            ->assertJson([
                'success' => false,
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'request' => ['method', 'url', 'headers', 'body'],
                'response' => ['status_code', 'status_text', 'headers', 'body'],
            ]);

        $data = $response->json();
        expect($data['response']['status_code'])->toBe(401)
            ->and($data['response']['status_text'])->toBe('Unauthorized');
    });

    it('masks sensitive authorization headers', function () {
        Http::fake([
            '*' => Http::response(['status' => 'ok'], 200),
        ]);

        $integration = Integration::factory()
            ->for($this->project)
            ->webhook()
            ->create();

        $response = $this->actingAs($this->user)
            ->postJson(route('projects.integrations.test', [$this->project, $integration]));

        $data = $response->json();
        expect($data['request']['headers']['Authorization'])->toBe('Bearer ••••••••');
    });

    it('denies test for non-project owners', function () {
        $otherUser = User::factory()->create();
        $integration = Integration::factory()
            ->for($this->project)
            ->webhook()
            ->create();

        $response = $this->actingAs($otherUser)
            ->postJson(route('projects.integrations.test', [$this->project, $integration]));

        $response->assertForbidden();
    });
});

describe('toggle active', function () {
    it('toggles integration from active to inactive', function () {
        $integration = Integration::factory()
            ->for($this->project)
            ->webhook()
            ->create(['is_active' => true]);

        $response = $this->actingAs($this->user)
            ->post(route('projects.integrations.toggle', [$this->project, $integration]));

        $response->assertRedirect(route('projects.integrations.index', $this->project));

        $integration->refresh();
        expect($integration->is_active)->toBeFalse();
    });

    it('toggles integration from inactive to active', function () {
        $integration = Integration::factory()
            ->for($this->project)
            ->webhook()
            ->inactive()
            ->create();

        $response = $this->actingAs($this->user)
            ->post(route('projects.integrations.toggle', [$this->project, $integration]));

        $response->assertRedirect();

        $integration->refresh();
        expect($integration->is_active)->toBeTrue();
    });

    it('denies toggle for non-project owners', function () {
        $otherUser = User::factory()->create();
        $integration = Integration::factory()
            ->for($this->project)
            ->webhook()
            ->create();

        $response = $this->actingAs($otherUser)
            ->post(route('projects.integrations.toggle', [$this->project, $integration]));

        $response->assertForbidden();
    });
});
