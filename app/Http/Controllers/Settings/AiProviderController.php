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
                'models' => ['gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo', 'gpt-3.5-turbo'],
                'requiresApiKey' => true,
                'supportsCustomEndpoint' => true,
            ],
            [
                'value' => 'anthropic',
                'label' => 'Anthropic',
                'models' => ['claude-sonnet-4-20250514', 'claude-3-5-haiku-20241022', 'claude-3-opus-20240229'],
                'requiresApiKey' => true,
                'supportsCustomEndpoint' => false,
            ],
            [
                'value' => 'ollama',
                'label' => 'Ollama (Local)',
                'models' => ['llama3', 'llama3:70b', 'mistral', 'mixtral', 'codellama'],
                'requiresApiKey' => false,
                'supportsCustomEndpoint' => true,
                'defaultEndpoint' => 'http://localhost:11434',
            ],
            [
                'value' => 'groq',
                'label' => 'Groq',
                'models' => ['llama-3.1-70b-versatile', 'llama-3.1-8b-instant', 'mixtral-8x7b-32768'],
                'requiresApiKey' => true,
                'supportsCustomEndpoint' => false,
            ],
            [
                'value' => 'mistral',
                'label' => 'Mistral AI',
                'models' => ['mistral-large-latest', 'mistral-medium-latest', 'mistral-small-latest'],
                'requiresApiKey' => true,
                'supportsCustomEndpoint' => false,
            ],
            [
                'value' => 'openrouter',
                'label' => 'OpenRouter',
                'models' => ['openai/gpt-4o', 'anthropic/claude-3.5-sonnet', 'meta-llama/llama-3.1-405b'],
                'requiresApiKey' => true,
                'supportsCustomEndpoint' => false,
            ],
        ];
    }
}
