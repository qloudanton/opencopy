<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProjectPage>
 */
class ProjectPageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $pageTypes = ['blog', 'product', 'service', 'landing', 'other'];
        $slugWords = $this->faker->words(3);

        return [
            'project_id' => Project::factory(),
            'url' => 'https://example.com/'.implode('-', $slugWords),
            'title' => $this->faker->sentence(4),
            'page_type' => $this->faker->randomElement($pageTypes),
            'keywords' => $this->faker->words(5),
            'priority' => $this->faker->randomFloat(2, 0.1, 1.0),
            'link_count' => $this->faker->numberBetween(0, 50),
            'is_active' => true,
            'last_modified_at' => $this->faker->optional()->dateTimeThisYear(),
            'last_fetched_at' => $this->faker->optional()->dateTimeThisMonth(),
        ];
    }

    public function blog(): static
    {
        return $this->state(fn (array $attributes) => [
            'page_type' => 'blog',
            'url' => 'https://example.com/blog/'.$this->faker->slug(3),
        ]);
    }

    public function product(): static
    {
        return $this->state(fn (array $attributes) => [
            'page_type' => 'product',
            'url' => 'https://example.com/products/'.$this->faker->slug(2),
        ]);
    }

    public function service(): static
    {
        return $this->state(fn (array $attributes) => [
            'page_type' => 'service',
            'url' => 'https://example.com/services/'.$this->faker->slug(2),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function neverLinked(): static
    {
        return $this->state(fn (array $attributes) => [
            'link_count' => 0,
        ]);
    }
}
