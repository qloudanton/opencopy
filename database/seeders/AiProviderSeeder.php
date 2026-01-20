<?php

namespace Database\Seeders;

use App\Models\AiProvider;
use App\Models\User;
use Illuminate\Database\Seeder;

class AiProviderSeeder extends Seeder
{
    public function run(): void
    {
        // Get the first user (or create one if needed)
        $user = User::first();

        if (! $user) {
            $this->command->error('No user found. Please register an account first.');
            return;
        }

        $anthropicKey = env('ANTHROPIC_API_KEY');
        $openaiKey = env('OPENAI_API_KEY');

        if (! $anthropicKey) {
            $this->command->warn('ANTHROPIC_API_KEY not set in .env - skipping Claude provider');
        } else {
            // Add Claude (Anthropic) for text generation
            AiProvider::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'provider' => 'anthropic',
                ],
                [
                    'name' => 'Claude (Anthropic)',
                    'api_key' => $anthropicKey,
                    'model' => 'claude-sonnet-4-20250514',
                    'is_default' => true,
                    'is_active' => true,
                    'supports_text' => true,
                    'supports_image' => false,
                ]
            );
            $this->command->info('✓ Claude (Anthropic) added - Default for text generation');
        }

        if (! $openaiKey) {
            $this->command->warn('OPENAI_API_KEY not set in .env - skipping OpenAI provider');
        } else {
            // Add OpenAI for image generation (DALL-E)
            AiProvider::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'provider' => 'openai',
                ],
                [
                    'name' => 'OpenAI (DALL-E)',
                    'api_key' => $openaiKey,
                    'model' => 'dall-e-3',
                    'is_default' => false,
                    'is_active' => true,
                    'supports_text' => true,
                    'supports_image' => true,
                ]
            );
            $this->command->info('✓ OpenAI (DALL-E) added - For image generation');
        }

        // Disable any existing Gemini provider (not available in UK)
        AiProvider::where('user_id', $user->id)
            ->where('provider', 'gemini')
            ->update(['is_active' => false]);

        $this->command->info('AI Providers setup complete!');
    }
}
