<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Integration>
 */
class IntegrationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'type' => fake()->randomElement(['wordpress', 'webflow', 'webhook']),
            'name' => fake()->company().' Integration',
            'credentials' => [
                'api_key' => fake()->sha256(),
            ],
            'settings' => [
                'default_status' => 'draft',
            ],
            'is_active' => true,
            'last_connected_at' => fake()->optional()->dateTimeThisMonth(),
        ];
    }

    public function wordpress(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'wordpress',
            'name' => 'WordPress Site',
            'credentials' => [
                'url' => fake()->url(),
                'username' => fake()->userName(),
                'application_password' => fake()->sha256(),
            ],
            'settings' => [
                'default_status' => 'draft',
                'default_category' => 1,
                'default_author' => 1,
            ],
        ]);
    }

    public function webhook(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'webhook',
            'name' => 'Webhook Integration',
            'credentials' => [
                'endpoint_url' => 'https://'.fake()->domainName().'/webhook',
                'access_token' => fake()->sha256(),
            ],
            'settings' => [
                'timeout' => 30,
                'retry_times' => 3,
            ],
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
