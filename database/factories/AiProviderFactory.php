<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AiProvider>
 */
class AiProviderFactory extends Factory
{
    public function definition(): array
    {
        $provider = fake()->randomElement(['openai', 'anthropic', 'ollama']);
        $models = [
            'openai' => ['gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo'],
            'anthropic' => ['claude-sonnet-4-20250514', 'claude-3-5-haiku-20241022'],
            'ollama' => ['llama3', 'mistral', 'mixtral'],
        ];

        return [
            'user_id' => User::factory(),
            'provider' => $provider,
            'name' => ucfirst($provider).' API',
            'api_key' => 'sk-test-'.fake()->sha256(),
            'api_endpoint' => $provider === 'ollama' ? 'http://localhost:11434' : null,
            'model' => fake()->randomElement($models[$provider]),
            'settings' => [
                'temperature' => 0.7,
                'max_tokens' => 4000,
            ],
            'is_default' => false,
            'is_active' => true,
        ];
    }

    public function openai(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => 'openai',
            'name' => 'OpenAI',
            'model' => 'gpt-4o',
            'api_endpoint' => null,
        ]);
    }

    public function anthropic(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => 'anthropic',
            'name' => 'Anthropic',
            'model' => 'claude-sonnet-4-20250514',
            'api_endpoint' => null,
        ]);
    }

    public function ollama(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => 'ollama',
            'name' => 'Local Ollama',
            'model' => 'llama3',
            'api_endpoint' => 'http://localhost:11434',
        ]);
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }
}
