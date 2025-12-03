<?php

namespace Database\Factories;

use App\Enums\ContentStatus;
use App\Enums\ContentType;
use App\Models\Article;
use App\Models\Keyword;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ScheduledContent>
 */
class ScheduledContentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'keyword_id' => Keyword::factory(),
            'article_id' => null,
            'title' => fake()->sentence(fake()->numberBetween(4, 8)),
            'content_type' => fake()->randomElement(ContentType::cases()),
            'status' => ContentStatus::Backlog,
            'scheduled_date' => null,
            'scheduled_time' => null,
            'position' => 0,
            'target_word_count' => fake()->optional()->randomElement([1000, 1500, 2000, 2500]),
            'tone' => fake()->optional()->randomElement(['professional', 'casual', 'technical', 'friendly']),
            'custom_instructions' => fake()->optional()->paragraph(),
            'notes' => fake()->optional()->sentence(),
            'generation_attempts' => 0,
            'error_message' => null,
            'generation_started_at' => null,
            'generation_completed_at' => null,
        ];
    }

    public function forProject(Project $project): static
    {
        return $this->state(fn (array $attributes) => [
            'project_id' => $project->id,
        ]);
    }

    public function withKeyword(Keyword $keyword): static
    {
        return $this->state(fn (array $attributes) => [
            'keyword_id' => $keyword->id,
            'project_id' => $keyword->project_id,
        ]);
    }

    public function backlog(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ContentStatus::Backlog,
            'scheduled_date' => null,
            'scheduled_time' => null,
        ]);
    }

    public function scheduled(?string $date = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ContentStatus::Scheduled,
            'scheduled_date' => $date ?? fake()->dateTimeBetween('now', '+30 days')->format('Y-m-d'),
            'scheduled_time' => fake()->time('H:i'),
        ]);
    }

    public function scheduledForToday(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ContentStatus::Scheduled,
            'scheduled_date' => now()->toDateString(),
            'scheduled_time' => fake()->time('H:i'),
        ]);
    }

    public function scheduledForTomorrow(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ContentStatus::Scheduled,
            'scheduled_date' => now()->addDay()->toDateString(),
            'scheduled_time' => fake()->time('H:i'),
        ]);
    }

    public function generating(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ContentStatus::Generating,
            'generation_started_at' => now(),
            'generation_attempts' => 1,
        ]);
    }

    public function inReview(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ContentStatus::InReview,
            'article_id' => Article::factory(),
            'generation_started_at' => now()->subMinutes(5),
            'generation_completed_at' => now(),
            'generation_attempts' => 1,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ContentStatus::Approved,
            'article_id' => Article::factory(),
            'generation_started_at' => now()->subMinutes(10),
            'generation_completed_at' => now()->subMinutes(5),
            'generation_attempts' => 1,
        ]);
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ContentStatus::Published,
            'article_id' => Article::factory(),
            'generation_started_at' => now()->subMinutes(15),
            'generation_completed_at' => now()->subMinutes(10),
            'generation_attempts' => 1,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ContentStatus::Failed,
            'error_message' => fake()->sentence(),
            'generation_started_at' => now()->subMinutes(5),
            'generation_completed_at' => now(),
            'generation_attempts' => fake()->numberBetween(1, 3),
        ]);
    }

    public function withContentType(ContentType $type): static
    {
        return $this->state(fn (array $attributes) => [
            'content_type' => $type,
        ]);
    }

    public function blogPost(): static
    {
        return $this->withContentType(ContentType::BlogPost);
    }

    public function listicle(): static
    {
        return $this->withContentType(ContentType::Listicle);
    }

    public function howTo(): static
    {
        return $this->withContentType(ContentType::HowTo);
    }

    public function comparison(): static
    {
        return $this->withContentType(ContentType::Comparison);
    }
}
