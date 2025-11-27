<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Project>
 */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->company(),
            'domain' => fake()->domainName(),
            'description' => fake()->optional()->sentence(),
            'settings' => [
                'default_tone' => fake()->randomElement(['professional', 'casual', 'technical', 'friendly']),
                'default_word_count' => fake()->randomElement([1000, 1500, 2000, 2500]),
                'default_search_intent' => 'informational',
                'language' => 'en',
            ],
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
