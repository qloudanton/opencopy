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
            'difficulty' => fake()->randomElement(['low', 'medium', 'high']),
            'volume' => fake()->randomElement(['low', 'medium', 'high']),
            'target_word_count' => fake()->randomElement([1000, 1500, 2000, 2500]),
            'tone' => fake()->optional()->randomElement(['professional', 'casual', 'technical', 'friendly']),
            'additional_instructions' => fake()->optional()->sentence(),
            'priority' => fake()->numberBetween(0, 10),
        ];
    }
}
