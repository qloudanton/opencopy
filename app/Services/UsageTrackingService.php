<?php

namespace App\Services;

use App\Models\AiProvider;
use App\Models\Article;
use App\Models\UsageLog;
use App\Models\User;

class UsageTrackingService
{
    /**
     * Pricing per million tokens (input/output) by model
     * Updated November 2025
     *
     * @var array<string, array{input: float, output: float}>
     */
    protected const TEXT_PRICING = [
        // OpenAI models
        'gpt-4o' => ['input' => 5.00, 'output' => 20.00],
        'gpt-4o-mini' => ['input' => 0.60, 'output' => 2.40],
        'gpt-4-turbo' => ['input' => 10.00, 'output' => 30.00],
        'gpt-4' => ['input' => 30.00, 'output' => 60.00],
        'gpt-3.5-turbo' => ['input' => 0.50, 'output' => 1.50],

        // Anthropic Claude models
        'claude-3-5-sonnet' => ['input' => 3.00, 'output' => 15.00],
        'claude-3-5-sonnet-20241022' => ['input' => 3.00, 'output' => 15.00],
        'claude-sonnet-4-5-20250929' => ['input' => 3.00, 'output' => 15.00],
        'claude-sonnet-4-20250514' => ['input' => 3.00, 'output' => 15.00],
        'claude-3-5-haiku' => ['input' => 0.80, 'output' => 4.00],
        'claude-3-haiku' => ['input' => 0.25, 'output' => 1.25],
        'claude-3-opus' => ['input' => 15.00, 'output' => 75.00],
        'claude-opus-4-5-20251101' => ['input' => 5.00, 'output' => 25.00],
        'claude-opus-4-20250514' => ['input' => 20.00, 'output' => 80.00],

        // Google Gemini models
        'gemini-1.5-pro' => ['input' => 1.25, 'output' => 5.00],
        'gemini-1.5-flash' => ['input' => 0.075, 'output' => 0.30],
        'gemini-2.0-flash' => ['input' => 0.10, 'output' => 0.40],

        // Ollama (local, free)
        'llama3' => ['input' => 0.00, 'output' => 0.00],
        'llama3.1' => ['input' => 0.00, 'output' => 0.00],
        'llama3.2' => ['input' => 0.00, 'output' => 0.00],
        'mistral' => ['input' => 0.00, 'output' => 0.00],
        'codellama' => ['input' => 0.00, 'output' => 0.00],
    ];

    /**
     * Image generation pricing by model and quality/size
     *
     * @var array<string, array<string, float>>
     */
    protected const IMAGE_PRICING = [
        // GPT Image 1 (gpt-image-1)
        'gpt-image-1' => [
            'low_1024x1024' => 0.011,
            'medium_1024x1024' => 0.042,
            'high_1024x1024' => 0.167,
            'low_1536x1024' => 0.016,
            'medium_1536x1024' => 0.063,
            'high_1536x1024' => 0.250,
            'low_1024x1536' => 0.016,
            'medium_1024x1536' => 0.063,
            'high_1024x1536' => 0.250,
            'default' => 0.17, // High quality default
        ],

        // DALL-E 3
        'dall-e-3' => [
            'standard_1024x1024' => 0.04,
            'standard_1024x1792' => 0.08,
            'standard_1792x1024' => 0.08,
            'hd_1024x1024' => 0.08,
            'hd_1024x1792' => 0.12,
            'hd_1792x1024' => 0.12,
            'default' => 0.12, // HD landscape default
        ],

        // DALL-E 2
        'dall-e-2' => [
            '1024x1024' => 0.02,
            '512x512' => 0.018,
            '256x256' => 0.016,
            'default' => 0.02,
        ],

        // Google Imagen
        'imagen-3.0-generate-002' => [
            'default' => 0.04, // Approximate pricing
        ],
    ];

    public function logTextGeneration(
        User $user,
        ?Article $article,
        ?AiProvider $aiProvider,
        string $model,
        int $inputTokens,
        int $outputTokens,
        string $operation = 'text_generation',
        array $metadata = []
    ): UsageLog {
        $cost = $this->calculateTextCost($model, $inputTokens, $outputTokens);

        return UsageLog::create([
            'user_id' => $user->id,
            'article_id' => $article?->id,
            'ai_provider_id' => $aiProvider?->id,
            'operation' => $operation,
            'model' => $model,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'estimated_cost' => $cost,
            'metadata' => $metadata,
        ]);
    }

    public function logImageGeneration(
        User $user,
        ?Article $article,
        ?AiProvider $aiProvider,
        string $model,
        int $imageCount = 1,
        ?string $size = null,
        ?string $quality = null,
        string $operation = 'image_generation',
        array $metadata = []
    ): UsageLog {
        $cost = $this->calculateImageCost($model, $imageCount, $size, $quality);

        return UsageLog::create([
            'user_id' => $user->id,
            'article_id' => $article?->id,
            'ai_provider_id' => $aiProvider?->id,
            'operation' => $operation,
            'model' => $model,
            'image_count' => $imageCount,
            'image_size' => $size,
            'image_quality' => $quality,
            'estimated_cost' => $cost,
            'metadata' => $metadata,
        ]);
    }

    public function calculateTextCost(string $model, int $inputTokens, int $outputTokens): float
    {
        $pricing = $this->getTextPricing($model);

        // Convert from per-million to per-token and calculate
        $inputCost = ($inputTokens / 1_000_000) * $pricing['input'];
        $outputCost = ($outputTokens / 1_000_000) * $pricing['output'];

        return $inputCost + $outputCost;
    }

    public function calculateImageCost(string $model, int $imageCount = 1, ?string $size = null, ?string $quality = null): float
    {
        $pricePerImage = $this->getImagePrice($model, $size, $quality);

        return $pricePerImage * $imageCount;
    }

    /**
     * Get text pricing for a model, with fallback to similar models
     *
     * @return array{input: float, output: float}
     */
    protected function getTextPricing(string $model): array
    {
        // Direct match
        if (isset(self::TEXT_PRICING[$model])) {
            return self::TEXT_PRICING[$model];
        }

        // Try to match partial model names
        foreach (self::TEXT_PRICING as $knownModel => $pricing) {
            if (str_contains(strtolower($model), strtolower($knownModel))) {
                return $pricing;
            }
        }

        // Default fallback (mid-tier pricing)
        return ['input' => 3.00, 'output' => 15.00];
    }

    protected function getImagePrice(string $model, ?string $size = null, ?string $quality = null): float
    {
        $modelPricing = self::IMAGE_PRICING[$model] ?? self::IMAGE_PRICING['dall-e-3'] ?? [];

        // Build lookup key
        if ($quality && $size) {
            $key = strtolower($quality).'_'.$size;
            if (isset($modelPricing[$key])) {
                return $modelPricing[$key];
            }
        }

        if ($size && isset($modelPricing[$size])) {
            return $modelPricing[$size];
        }

        return $modelPricing['default'] ?? 0.12;
    }

    public function getArticleCostBreakdown(Article $article): array
    {
        $logs = $article->usageLogs()->get();

        $breakdown = [
            'text_generation' => 0.0,
            'image_generation' => 0.0,
            'improvement' => 0.0,
            'total' => 0.0,
            'details' => [],
        ];

        foreach ($logs as $log) {
            $cost = (float) $log->estimated_cost;
            $breakdown['total'] += $cost;

            if (str_contains($log->operation, 'image')) {
                $breakdown['image_generation'] += $cost;
            } elseif ($log->operation === 'improvement') {
                $breakdown['improvement'] += $cost;
            } else {
                $breakdown['text_generation'] += $cost;
            }

            $breakdown['details'][] = [
                'operation' => $log->operation,
                'model' => $log->model,
                'cost' => $cost,
                'tokens' => $log->input_tokens ? $log->input_tokens + $log->output_tokens : null,
                'images' => $log->image_count,
                'created_at' => $log->created_at->toIso8601String(),
            ];
        }

        return $breakdown;
    }

    public function getUserMonthlyCost(User $user, ?int $year = null, ?int $month = null): float
    {
        $year = $year ?? now()->year;
        $month = $month ?? now()->month;

        return (float) UsageLog::where('user_id', $user->id)
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->sum('estimated_cost');
    }
}
