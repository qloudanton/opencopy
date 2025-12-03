<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAiProviderRequest;
use App\Http\Requests\UpdateAiProviderRequest;
use App\Models\AiProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AiProviderController extends Controller
{
    public function index(Request $request): Response
    {
        $providers = $request->user()
            ->aiProviders()
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get()
            ->map(fn (AiProvider $provider) => [
                'id' => $provider->id,
                'provider' => $provider->provider,
                'name' => $provider->name,
                'model' => $provider->model,
                'api_endpoint' => $provider->api_endpoint,
                'is_default' => $provider->is_default,
                'is_active' => $provider->is_active,
                'supports_text' => $provider->supports_text,
                'supports_image' => $provider->supports_image,
                'has_api_key' => ! empty($provider->api_key),
                'created_at' => $provider->created_at,
            ]);

        return Inertia::render('settings/ai-providers', [
            'providers' => $providers,
            'availableProviders' => $this->getAvailableProviders(),
        ]);
    }

    public function store(StoreAiProviderRequest $request): RedirectResponse
    {
        $data = $request->validated();

        // If this is set as default, unset other defaults
        if ($data['is_default'] ?? false) {
            $request->user()->aiProviders()->update(['is_default' => false]);
        }

        $request->user()->aiProviders()->create($data);

        return redirect()
            ->route('ai-providers.index')
            ->with('success', 'AI provider added successfully.');
    }

    public function update(UpdateAiProviderRequest $request, AiProvider $aiProvider): RedirectResponse
    {
        $this->authorize('update', $aiProvider);

        $data = $request->validated();

        // If this is set as default, unset other defaults
        if ($data['is_default'] ?? false) {
            $request->user()->aiProviders()
                ->where('id', '!=', $aiProvider->id)
                ->update(['is_default' => false]);
        }

        // Don't update api_key if it's empty (user didn't change it)
        if (empty($data['api_key'])) {
            unset($data['api_key']);
        }

        $aiProvider->update($data);

        return redirect()
            ->route('ai-providers.index')
            ->with('success', 'AI provider updated successfully.');
    }

    public function destroy(Request $request, AiProvider $aiProvider): RedirectResponse
    {
        $this->authorize('delete', $aiProvider);

        $aiProvider->delete();

        return redirect()
            ->route('ai-providers.index')
            ->with('success', 'AI provider removed successfully.');
    }

    public function setDefault(Request $request, AiProvider $aiProvider): RedirectResponse
    {
        $this->authorize('update', $aiProvider);

        $request->user()->aiProviders()->update(['is_default' => false]);
        $aiProvider->update(['is_default' => true]);

        return redirect()
            ->route('ai-providers.index')
            ->with('success', 'Default provider updated.');
    }

    private function getAvailableProviders(): array
    {
        return [
            [
                'value' => 'openai',
                'label' => 'OpenAI',
                'models' => [
                    // GPT-5.1 (Latest)
                    'gpt-5.1',
                    // GPT-5 Family
                    'gpt-5-mini',
                    'gpt-5-nano',
                    // GPT-4.1 Family
                    'gpt-4.1',
                    'gpt-4.1-mini',
                    'gpt-4.1-nano',
                    // Reasoning Models (o-series)
                    'o3',
                    'o4-mini',
                    // GPT-4o (Legacy)
                    'gpt-4o',
                    'gpt-4o-mini',
                ],
                'requiresApiKey' => true,
                'supportsCustomEndpoint' => true,
            ],
            [
                'value' => 'anthropic',
                'label' => 'Anthropic',
                'models' => [
                    'claude-sonnet-4-20250514',
                    'claude-opus-4-20250514',
                    'claude-3-5-sonnet-20241022',
                    'claude-3-5-haiku-20241022',
                    'claude-3-opus-20240229',
                ],
                'requiresApiKey' => true,
                'supportsCustomEndpoint' => false,
            ],
            [
                'value' => 'ollama',
                'label' => 'Ollama (Local)',
                'models' => ['llama3.2', 'llama3.1:70b', 'llama3.1:8b', 'mistral', 'mixtral', 'codellama', 'qwen2.5:72b'],
                'requiresApiKey' => false,
                'supportsCustomEndpoint' => true,
                'defaultEndpoint' => 'http://localhost:11434',
            ],
            [
                'value' => 'groq',
                'label' => 'Groq',
                'models' => ['llama-3.3-70b-versatile', 'llama-3.1-70b-versatile', 'llama-3.1-8b-instant', 'mixtral-8x7b-32768'],
                'requiresApiKey' => true,
                'supportsCustomEndpoint' => false,
            ],
            [
                'value' => 'mistral',
                'label' => 'Mistral AI',
                'models' => ['mistral-large-latest', 'mistral-medium-latest', 'mistral-small-latest', 'codestral-latest'],
                'requiresApiKey' => true,
                'supportsCustomEndpoint' => false,
            ],
            [
                'value' => 'openrouter',
                'label' => 'OpenRouter',
                'models' => ['openai/gpt-5.1', 'openai/gpt-5-mini', 'openai/gpt-4.1', 'openai/gpt-4o', 'anthropic/claude-sonnet-4', 'anthropic/claude-3.5-sonnet', 'meta-llama/llama-3.3-70b'],
                'requiresApiKey' => true,
                'supportsCustomEndpoint' => false,
            ],
        ];
    }
}
