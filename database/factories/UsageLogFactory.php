<?php

namespace Database\Factories;

use App\Models\AiProvider;
use App\Models\Article;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UsageLog>
 */
class UsageLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'article_id' => Article::factory(),
            'ai_provider_id' => AiProvider::factory(),
            'operation' => fake()->randomElement(['article_generation', 'featured_image', 'inline_image', 'improvement']),
            'model' => fake()->randomElement(['gpt-4o', 'claude-3-5-sonnet', 'dall-e-3', 'gpt-image-1']),
            'input_tokens' => fake()->numberBetween(100, 10000),
            'output_tokens' => fake()->numberBetween(500, 20000),
            'image_count' => null,
            'image_size' => null,
            'image_quality' => null,
            'estimated_cost' => fake()->randomFloat(6, 0.001, 1.0),
            'metadata' => null,
        ];
    }

    /**
     * Indicate that the log is for text generation.
     */
    public function textGeneration(): static
    {
        return $this->state(fn (array $attributes) => [
            'operation' => 'article_generation',
            'model' => fake()->randomElement(['gpt-4o', 'claude-3-5-sonnet']),
            'input_tokens' => fake()->numberBetween(1000, 5000),
            'output_tokens' => fake()->numberBetween(2000, 10000),
            'image_count' => null,
            'image_size' => null,
            'image_quality' => null,
        ]);
    }

    /**
     * Indicate that the log is for image generation.
     */
    public function imageGeneration(): static
    {
        return $this->state(fn (array $attributes) => [
            'operation' => fake()->randomElement(['featured_image', 'inline_image']),
            'model' => fake()->randomElement(['dall-e-3', 'gpt-image-1']),
            'input_tokens' => null,
            'output_tokens' => null,
            'image_count' => fake()->numberBetween(1, 3),
            'image_size' => fake()->randomElement(['1024x1024', '1792x1024', '1536x1024']),
            'image_quality' => fake()->randomElement(['standard', 'hd', 'high']),
        ]);
    }
}
