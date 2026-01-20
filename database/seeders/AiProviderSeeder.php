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
        $geminiKey = env('GEMINI_API_KEY');

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

        if (! $geminiKey) {
            $this->command->warn('GEMINI_API_KEY not set in .env - skipping Gemini provider');
        } else {
            // Add Gemini for image generation (Nano Banana)
            AiProvider::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'provider' => 'gemini',
                ],
                [
                    'name' => 'Gemini (Nano Banana)',
                    'api_key' => $geminiKey,
                    'model' => 'gemini-2.0-flash-exp',
                    'is_default' => false,
                    'is_active' => true,
                    'supports_text' => true,
                    'supports_image' => true,
                ]
            );
            $this->command->info('✓ Gemini (Nano Banana) added - For image generation');
        }

        $this->command->info('AI Providers setup complete!');
    }
}
