<?php

namespace Database\Factories;

use App\Models\Article;
use App\Models\Integration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Publication>
 */
class PublicationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'article_id' => Article::factory(),
            'integration_id' => Integration::factory(),
            'status' => 'pending',
            'external_id' => null,
            'external_url' => null,
            'payload_sent' => null,
            'response_received' => null,
            'error_message' => null,
            'published_at' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
        ]);
    }

    public function publishing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'publishing',
        ]);
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'published',
            'external_id' => (string) fake()->randomNumber(5),
            'external_url' => fake()->url(),
            'published_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'error_message' => fake()->sentence(),
        ]);
    }
}
