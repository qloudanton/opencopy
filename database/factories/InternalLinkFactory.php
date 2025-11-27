<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InternalLink>
 */
class InternalLinkFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'url' => fake()->url(),
            'anchor_text' => fake()->words(fake()->numberBetween(2, 5), true),
            'title' => fake()->optional()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'category' => fake()->optional()->word(),
            'priority' => fake()->numberBetween(1, 10),
            'max_uses_per_article' => fake()->numberBetween(1, 3),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function highPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => fake()->numberBetween(8, 10),
        ]);
    }
}
