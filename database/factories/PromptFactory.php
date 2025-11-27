<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Prompt>
 */
class PromptFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'name' => fake()->words(3, true),
            'type' => fake()->randomElement(['system', 'structure', 'tone', 'seo', 'custom']),
            'content' => fake()->paragraphs(3, true),
            'variables' => [
                'keyword' => 'The primary keyword to target',
                'word_count' => 'Target word count',
            ],
            'is_default' => false,
            'is_active' => true,
        ];
    }

    public function system(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'system',
            'name' => 'System Prompt',
            'project_id' => null,
        ]);
    }

    public function structure(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'structure',
            'name' => 'Article Structure',
        ]);
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }
}
