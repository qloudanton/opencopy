<?php

namespace Database\Factories;

use App\Models\AiProvider;
use App\Models\Keyword;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Article>
 */
class ArticleFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->sentence(6);
        $wordCount = fake()->numberBetween(800, 2500);

        return [
            'project_id' => Project::factory(),
            'keyword_id' => Keyword::factory(),
            'ai_provider_id' => AiProvider::factory(),
            'title' => $title,
            'slug' => Str::slug($title),
            'meta_description' => fake()->text(155),
            'excerpt' => fake()->paragraph(),
            'content' => '<h1>'.$title.'</h1>'.fake()->randomHtml(5, 10),
            'content_markdown' => '# '.$title."\n\n".fake()->paragraphs(10, true),
            'outline' => [
                ['level' => 1, 'text' => $title],
                ['level' => 2, 'text' => fake()->sentence(4)],
                ['level' => 2, 'text' => fake()->sentence(4)],
                ['level' => 3, 'text' => fake()->sentence(3)],
            ],
            'word_count' => $wordCount,
            'reading_time_minutes' => (int) ceil($wordCount / 200),
            'seo_score' => fake()->numberBetween(60, 95),
            'seo_analysis' => [
                'keyword_in_title' => true,
                'keyword_in_meta' => true,
                'keyword_density' => fake()->randomFloat(2, 0.5, 2.5),
                'heading_structure' => ['h1' => 1, 'h2' => 3, 'h3' => 5],
            ],
            'generation_metadata' => [
                'tokens_used' => fake()->numberBetween(2000, 8000),
                'model' => 'gpt-4o',
                'duration_seconds' => fake()->randomFloat(2, 10, 60),
            ],
            'generated_at' => now(),
        ];
    }

    public function withoutKeyword(): static
    {
        return $this->state(fn (array $attributes) => [
            'keyword_id' => null,
        ]);
    }
}
