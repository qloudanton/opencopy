<?php

namespace App\Services;

use App\Models\AiProvider;
use App\Models\Article;
use App\Models\InternalLink;
use App\Models\Keyword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Prism\Prism\Prism;

class ArticleGenerationService
{
    protected Keyword $keyword;

    protected AiProvider $aiProvider;

    protected ?string $generatedContent = null;

    public function __construct(protected Prism $prism) {}

    public function generate(Keyword $keyword, ?AiProvider $aiProvider = null): Article
    {
        $this->keyword = $keyword;
        $this->aiProvider = $aiProvider ?? $this->getDefaultProvider();

        $this->validateProvider();

        $this->keyword->update(['status' => 'generating']);

        try {
            $this->generatedContent = $this->callAi();

            $article = DB::transaction(function () {
                $article = $this->createArticle();
                $this->keyword->update(['status' => 'completed', 'error_message' => null]);

                return $article;
            });

            return $article;
        } catch (\Exception $e) {
            $this->keyword->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    protected function getDefaultProvider(): AiProvider
    {
        $provider = $this->keyword->project->user
            ->aiProviders()
            ->where('is_active', true)
            ->where('is_default', true)
            ->first();

        if (! $provider) {
            $provider = $this->keyword->project->user
                ->aiProviders()
                ->where('is_active', true)
                ->first();
        }

        if (! $provider) {
            throw new \RuntimeException('No active AI provider configured. Please add an AI provider in settings.');
        }

        return $provider;
    }

    protected function validateProvider(): void
    {
        if (! $this->aiProvider->is_active) {
            throw new \RuntimeException('The selected AI provider is not active.');
        }

        if ($this->aiProvider->user_id !== $this->keyword->project->user_id) {
            throw new \RuntimeException('The AI provider does not belong to the project owner.');
        }
    }

    protected function callAi(): string
    {
        $providerConfig = $this->buildProviderConfig();

        $response = $this->prism->text()
            ->using($this->aiProvider->provider, $this->aiProvider->model, $providerConfig)
            ->withSystemPrompt($this->buildSystemPrompt())
            ->withPrompt($this->buildUserPrompt())
            ->asText();

        return $response->text;
    }

    protected function buildProviderConfig(): array
    {
        $config = [];

        if ($this->aiProvider->api_key) {
            $config['api_key'] = $this->aiProvider->api_key;
        }

        if ($this->aiProvider->api_endpoint) {
            $config['url'] = $this->aiProvider->api_endpoint;
        }

        return $config;
    }

    protected function buildSystemPrompt(): string
    {
        return <<<'PROMPT'
You are an expert SEO content writer. Your task is to create high-quality, SEO-optimized articles that rank well in search engines while providing genuine value to readers.

Follow these guidelines:
1. Write in a clear, engaging style appropriate for the specified tone
2. Use proper heading hierarchy (H2, H3, H4) to structure content
3. Include the primary keyword naturally in the title, first paragraph, and throughout the content
4. Incorporate secondary keywords where they fit naturally
5. Write comprehensive content that thoroughly covers the topic
6. Include a compelling meta description (150-160 characters)
7. Use short paragraphs and varied sentence lengths for readability
8. Add relevant internal links where provided, using natural anchor text
9. Include a brief introduction and conclusion
10. Aim for the specified word count target

Output your response in the following markdown format:
---
title: [Article Title]
meta_description: [Meta description, 150-160 characters]
---

[Article content in markdown format with proper headings, paragraphs, and formatting]
PROMPT;
    }

    protected function buildUserPrompt(): string
    {
        $keyword = $this->keyword;
        $internalLinks = $this->getRelevantInternalLinks();

        $prompt = "Write an SEO-optimized article about: **{$keyword->keyword}**\n\n";

        if ($keyword->secondary_keywords) {
            $secondaryList = implode(', ', $keyword->secondary_keywords);
            $prompt .= "Secondary keywords to include: {$secondaryList}\n\n";
        }

        if ($keyword->search_intent) {
            $prompt .= "Search intent: {$keyword->search_intent}\n\n";
        }

        $wordCount = $keyword->target_word_count ?? 1500;
        $prompt .= "Target word count: approximately {$wordCount} words\n\n";

        if ($keyword->tone) {
            $prompt .= "Writing tone: {$keyword->tone}\n\n";
        }

        if ($internalLinks->isNotEmpty()) {
            $prompt .= "Internal links to incorporate naturally:\n";
            foreach ($internalLinks as $link) {
                $prompt .= "- [{$link->anchor_text}]({$link->url})";
                if ($link->description) {
                    $prompt .= " - {$link->description}";
                }
                $prompt .= "\n";
            }
            $prompt .= "\n";
        }

        if ($keyword->additional_instructions) {
            $prompt .= "Additional instructions:\n{$keyword->additional_instructions}\n\n";
        }

        return $prompt;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, InternalLink>
     */
    protected function getRelevantInternalLinks()
    {
        return $this->keyword->project
            ->internalLinks()
            ->where('is_active', true)
            ->orderBy('priority', 'desc')
            ->limit(10)
            ->get();
    }

    protected function createArticle(): Article
    {
        $parsed = $this->parseGeneratedContent();

        $content = $parsed['content'];
        $wordCount = str_word_count(strip_tags($content));
        $readingTime = (int) ceil($wordCount / 200);

        return Article::create([
            'project_id' => $this->keyword->project_id,
            'keyword_id' => $this->keyword->id,
            'ai_provider_id' => $this->aiProvider->id,
            'title' => $parsed['title'],
            'slug' => Str::slug($parsed['title']),
            'meta_description' => $parsed['meta_description'],
            'content' => $content,
            'content_markdown' => $content,
            'word_count' => $wordCount,
            'reading_time_minutes' => $readingTime,
            'status' => 'draft',
            'generation_metadata' => [
                'provider' => $this->aiProvider->provider,
                'model' => $this->aiProvider->model,
                'keyword' => $this->keyword->keyword,
                'secondary_keywords' => $this->keyword->secondary_keywords,
                'target_word_count' => $this->keyword->target_word_count,
                'generated_word_count' => $wordCount,
            ],
            'generated_at' => now(),
        ]);
    }

    /**
     * @return array{title: string, meta_description: string, content: string}
     */
    protected function parseGeneratedContent(): array
    {
        $content = $this->generatedContent ?? '';

        $title = $this->keyword->keyword;
        $metaDescription = '';

        if (preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $content, $matches)) {
            $frontMatter = $matches[1];
            $content = trim($matches[2]);

            if (preg_match('/^title:\s*(.+)$/m', $frontMatter, $titleMatch)) {
                $title = trim($titleMatch[1]);
            }

            if (preg_match('/^meta_description:\s*(.+)$/m', $frontMatter, $metaMatch)) {
                $metaDescription = trim($metaMatch[1]);
            }
        } elseif (preg_match('/^#\s+(.+)$/m', $content, $headingMatch)) {
            $title = trim($headingMatch[1]);
        }

        if (empty($metaDescription)) {
            $plainText = strip_tags($content);
            $metaDescription = Str::limit($plainText, 155);
        }

        return [
            'title' => $title,
            'meta_description' => $metaDescription,
            'content' => $content,
        ];
    }
}
