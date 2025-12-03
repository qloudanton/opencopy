<?php

namespace App\Services;

use App\Enums\ContentStatus;
use App\Enums\ContentType;
use App\Models\AiProvider;
use App\Models\Keyword;
use App\Models\Project;
use App\Models\ScheduledContent;
use App\Models\User;
use Illuminate\Support\Collection;
use Prism\Prism\Prism;

class ContentPlannerService
{
    public function __construct(
        protected Prism $prism,
        protected UsageTrackingService $usageTrackingService
    ) {}

    /**
     * Automatically create backlog content for all available keywords.
     *
     * @return array{created: int, keywords: Collection}
     */
    public function autoCreateBacklog(Project $project, ?AiProvider $aiProvider = null): array
    {
        $aiProvider = $aiProvider ?? $this->getDefaultProvider($project->user);

        // Get keywords not yet in content planner
        $keywords = $project->keywords()
            ->whereDoesntHave('scheduledContents')
            ->get();

        if ($keywords->isEmpty()) {
            return ['created' => 0, 'keywords' => collect()];
        }

        $createdContents = collect();

        // Batch keywords for efficient AI processing (up to 10 at a time)
        foreach ($keywords->chunk(10) as $batch) {
            $contentTypes = $this->suggestContentTypes($batch, $project, $aiProvider);

            foreach ($batch as $keyword) {
                $contentType = $contentTypes[$keyword->id] ?? ContentType::BlogPost;

                $content = ScheduledContent::create([
                    'project_id' => $project->id,
                    'keyword_id' => $keyword->id,
                    'title' => null, // Will use keyword as display title
                    'content_type' => $contentType,
                    'status' => ContentStatus::Backlog,
                    'target_word_count' => $contentType->suggestedWordCount(),
                ]);

                $createdContents->push($content);
            }
        }

        return [
            'created' => $createdContents->count(),
            'keywords' => $createdContents,
        ];
    }

    /**
     * Suggest content types for a batch of keywords using AI.
     *
     * @param  Collection<Keyword>  $keywords
     * @return array<int, ContentType>
     */
    protected function suggestContentTypes(Collection $keywords, Project $project, AiProvider $aiProvider): array
    {
        $keywordList = $keywords->map(fn ($k) => "- ID:{$k->id} \"{$k->keyword}\"")->implode("\n");

        $contentTypeOptions = collect(ContentType::cases())
            ->map(fn ($type) => "- {$type->value}: {$type->label()} - {$type->description()}")
            ->implode("\n");

        $prompt = <<<PROMPT
You are an SEO content strategist. Analyze the following keywords and suggest the most appropriate content type for each one.

Project context:
- Name: {$project->name}
- Website: {$project->website_url}
- Description: {$project->description}

Available content types:
{$contentTypeOptions}

Keywords to analyze:
{$keywordList}

For each keyword, determine the best content type based on:
1. Search intent (informational, transactional, navigational)
2. Keyword structure (question words, comparisons, "how to", lists)
3. User expectations for this type of search

Respond with ONLY a JSON object mapping keyword IDs to content types. Example:
{"1": "how_to", "2": "listicle", "3": "comparison"}

JSON response:
PROMPT;

        try {
            $providerConfig = [];
            if ($aiProvider->api_key) {
                $providerConfig['api_key'] = $aiProvider->api_key;
            }
            if ($aiProvider->api_endpoint) {
                $providerConfig['url'] = $aiProvider->api_endpoint;
            }

            $response = $this->prism->text()
                ->using($aiProvider->provider, $aiProvider->model, $providerConfig)
                ->withSystemPrompt('You are an SEO content strategist. Respond only with valid JSON.')
                ->withPrompt($prompt)
                ->asText();

            // Log usage
            $this->usageTrackingService->logUsage(
                user: $project->user,
                provider: $aiProvider,
                inputTokens: $response->usage->promptTokens ?? 0,
                outputTokens: $response->usage->completionTokens ?? 0,
                model: $aiProvider->model,
                operation: 'content_type_suggestion'
            );

            $json = $this->extractJson($response->text);
            $suggestions = json_decode($json, true) ?? [];

            return collect($suggestions)->mapWithKeys(function ($type, $id) {
                $contentType = ContentType::tryFrom($type) ?? ContentType::BlogPost;

                return [(int) $id => $contentType];
            })->all();
        } catch (\Exception $e) {
            // Fallback to heuristic-based suggestions if AI fails
            return $this->suggestContentTypesHeuristically($keywords);
        }
    }

    /**
     * Fallback heuristic-based content type suggestion.
     *
     * @param  Collection<Keyword>  $keywords
     * @return array<int, ContentType>
     */
    protected function suggestContentTypesHeuristically(Collection $keywords): array
    {
        return $keywords->mapWithKeys(function ($keyword) {
            $kw = strtolower($keyword->keyword);

            $contentType = match (true) {
                str_contains($kw, 'how to') || str_contains($kw, 'guide') => ContentType::HowTo,
                str_contains($kw, 'vs') || str_contains($kw, 'versus') || str_contains($kw, 'compared') => ContentType::Comparison,
                str_contains($kw, 'best') || str_contains($kw, 'top') || preg_match('/\d+\s+(ways|tips|ideas|reasons)/', $kw) => ContentType::Listicle,
                str_contains($kw, 'review') => ContentType::Review,
                str_contains($kw, 'case study') || str_contains($kw, 'success story') => ContentType::CaseStudy,
                str_contains($kw, 'news') || str_contains($kw, 'update') || str_contains($kw, 'announcement') => ContentType::NewsArticle,
                str_contains($kw, 'complete guide') || str_contains($kw, 'ultimate') || str_contains($kw, 'everything') => ContentType::PillarContent,
                default => ContentType::BlogPost,
            };

            return [$keyword->id => $contentType];
        })->all();
    }

    /**
     * Extract JSON from AI response text.
     */
    protected function extractJson(string $text): string
    {
        // Try to find JSON object in the response
        if (preg_match('/\{[^{}]*\}/', $text, $matches)) {
            return $matches[0];
        }

        return '{}';
    }

    /**
     * Get the default AI provider for a user.
     */
    protected function getDefaultProvider(User $user): AiProvider
    {
        $provider = $user->aiProviders()
            ->where('is_active', true)
            ->where('is_default', true)
            ->first();

        if (! $provider) {
            $provider = $user->aiProviders()
                ->where('is_active', true)
                ->first();
        }

        if (! $provider) {
            throw new \RuntimeException('No active AI provider configured. Please add an AI provider in settings.');
        }

        return $provider;
    }
}
