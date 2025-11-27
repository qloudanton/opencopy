<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Keyword>
 */
class KeywordFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'keyword' => fake()->words(fake()->numberBetween(2, 5), true),
            'secondary_keywords' => fake()->optional()->words(fake()->numberBetween(3, 8)),
            'search_intent' => fake()->randomElement(['informational', 'transactional', 'navigational', 'commercial']),
            'target_word_count' => fake()->randomElement([1000, 1500, 2000, 2500]),
            'tone' => fake()->optional()->randomElement(['professional', 'casual', 'technical', 'friendly']),
            'additional_instructions' => fake()->optional()->sentence(),
            'status' => 'pending',
            'priority' => fake()->numberBetween(0, 10),
            'error_message' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
        ]);
    }

    public function queued(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'queued',
        ]);
    }

    public function generating(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'generating',
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
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
