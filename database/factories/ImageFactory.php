<?php

namespace Database\Factories;

use App\Models\Article;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Image>
 */
class ImageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'article_id' => Article::factory(),
            'type' => fake()->randomElement(['featured', 'content', 'og_image']),
            'source' => fake()->randomElement(['ai_generated', 'stock', 'uploaded']),
            'prompt' => fake()->optional()->sentence(),
            'path' => 'images/'.fake()->uuid().'.jpg',
            'url' => fake()->imageUrl(1200, 630),
            'alt_text' => fake()->sentence(4),
            'caption' => fake()->optional()->sentence(),
            'width' => 1200,
            'height' => 630,
            'file_size' => fake()->numberBetween(50000, 500000),
            'mime_type' => 'image/jpeg',
            'metadata' => [
                'generated_by' => 'dall-e-3',
            ],
        ];
    }

    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'featured',
        ]);
    }

    public function content(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'content',
        ]);
    }

    public function aiGenerated(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => 'ai_generated',
            'prompt' => fake()->sentence(),
        ]);
    }

    public function stock(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => 'stock',
            'prompt' => null,
            'metadata' => ['provider' => 'unsplash', 'photo_id' => fake()->uuid()],
        ]);
    }

    public function inline(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'inline',
            'source' => 'ai_generated',
        ]);
    }
}
