<?php

namespace App\Services;

use App\Models\AiProvider;
use App\Models\Article;
use Prism\Prism\Prism;

class ArticleImprovementService
{
    public function __construct(
        protected Prism $prism
    ) {}

    /**
     * Apply an AI-powered improvement to the article.
     *
     * @return array{field: string, value: string, message: string}
     */
    public function improve(Article $article, string $improvementType, AiProvider $aiProvider): array
    {
        $providerConfig = $this->buildProviderConfig($aiProvider);
        $keyword = $article->keyword?->keyword ?? '';

        return match ($improvementType) {
            'add_keyword_to_title' => $this->improveTitle($article, $keyword, $aiProvider, $providerConfig),
            'add_keyword_to_meta' => $this->improveMeta($article, $keyword, $aiProvider, $providerConfig),
            'add_faq_section' => $this->addFaqSection($article, $keyword, $aiProvider, $providerConfig),
            'add_table' => $this->addTable($article, $keyword, $aiProvider, $providerConfig),
            'add_h2_headings' => $this->addH2Headings($article, $keyword, $aiProvider, $providerConfig),
            'add_lists' => $this->addLists($article, $keyword, $aiProvider, $providerConfig),
            'optimize_title_length' => $this->optimizeTitleLength($article, $keyword, $aiProvider, $providerConfig),
            'optimize_meta_length' => $this->optimizeMetaLength($article, $keyword, $aiProvider, $providerConfig),
            'add_keyword_to_h2' => $this->addKeywordToH2($article, $keyword, $aiProvider, $providerConfig),
            'add_keyword_to_intro' => $this->addKeywordToIntro($article, $keyword, $aiProvider, $providerConfig),
            default => throw new \InvalidArgumentException("Unknown improvement type: {$improvementType}"),
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildProviderConfig(AiProvider $aiProvider): array
    {
        $config = [];

        if ($aiProvider->api_key) {
            $config['api_key'] = $aiProvider->api_key;
        }

        if ($aiProvider->base_url) {
            $config['url'] = $aiProvider->base_url;
        }

        return $config;
    }

    /**
     * @param  array<string, mixed>  $providerConfig
     * @return array{field: string, value: string, message: string}
     */
    protected function improveTitle(Article $article, string $keyword, AiProvider $aiProvider, array $providerConfig): array
    {
        $prompt = <<<PROMPT
Rewrite this article title to naturally include the keyword "{$keyword}".

Current title: {$article->title}

Rules:
- Keep it between 50-60 characters
- Make it compelling and click-worthy
- Include the keyword naturally (variations like plurals are OK)
- Do not use em dashes
- Return ONLY the new title, nothing else
PROMPT;

        $newTitle = $this->callAi($aiProvider, $providerConfig, $prompt);

        return [
            'field' => 'title',
            'value' => trim($newTitle),
            'message' => 'Title updated to include keyword',
        ];
    }

    /**
     * @param  array<string, mixed>  $providerConfig
     * @return array{field: string, value: string, message: string}
     */
    protected function improveMeta(Article $article, string $keyword, AiProvider $aiProvider, array $providerConfig): array
    {
        $currentMeta = $article->meta_description ?? '';
        $prompt = <<<PROMPT
Write a compelling meta description for this article that includes the keyword "{$keyword}".

Article title: {$article->title}
Current meta description: {$currentMeta}

Rules:
- Keep it between 150-160 characters exactly
- Include the keyword naturally
- Make it compelling to encourage clicks
- Summarize what the reader will learn
- Do not use em dashes
- Return ONLY the meta description, nothing else
PROMPT;

        $newMeta = $this->callAi($aiProvider, $providerConfig, $prompt);

        return [
            'field' => 'meta_description',
            'value' => trim($newMeta),
            'message' => 'Meta description updated to include keyword',
        ];
    }

    /**
     * @param  array<string, mixed>  $providerConfig
     * @return array{field: string, value: string, message: string}
     */
    protected function addFaqSection(Article $article, string $keyword, AiProvider $aiProvider, array $providerConfig): array
    {
        $content = $article->content_markdown ?: $article->content;
        $prompt = <<<PROMPT
Generate a "Frequently Asked Questions" section for this article about "{$keyword}".

Article title: {$article->title}

Rules:
- Create 4-5 relevant questions and answers
- Questions should be what real people would ask
- Answers should be concise but helpful (2-3 sentences)
- Include the keyword naturally in at least one question
- Format as markdown with ## FAQ as the heading
- Use ### for each question
- Do not use em dashes
- Return ONLY the FAQ section in markdown format
PROMPT;

        $faqSection = $this->callAi($aiProvider, $providerConfig, $prompt);

        $newContent = trim($content)."\n\n".trim($faqSection);

        return [
            'field' => 'content',
            'value' => $newContent,
            'message' => 'FAQ section added to content',
        ];
    }

    /**
     * @param  array<string, mixed>  $providerConfig
     * @return array{field: string, value: string, message: string}
     */
    protected function addTable(Article $article, string $keyword, AiProvider $aiProvider, array $providerConfig): array
    {
        $content = $article->content_markdown ?: $article->content;
        $prompt = <<<PROMPT
Generate a useful comparison or data table for this article about "{$keyword}".

Article title: {$article->title}

Rules:
- Create a markdown table with 3-5 columns and 4-6 rows
- Make it informative and relevant to the topic
- Include a brief introduction sentence before the table
- Include a ## heading for the table section
- Do not use em dashes
- Return ONLY the table section (heading + intro + table) in markdown format
PROMPT;

        $tableSection = $this->callAi($aiProvider, $providerConfig, $prompt);

        $newContent = $this->insertBeforeConclusion($content, trim($tableSection));

        return [
            'field' => 'content',
            'value' => $newContent,
            'message' => 'Comparison table added to content',
        ];
    }

    /**
     * @param  array<string, mixed>  $providerConfig
     * @return array{field: string, value: string, message: string}
     */
    protected function addH2Headings(Article $article, string $keyword, AiProvider $aiProvider, array $providerConfig): array
    {
        $content = $article->content_markdown ?: $article->content;

        preg_match_all('/^##\s+(.+)$/m', $content, $matches);
        $existingH2s = implode(', ', $matches[1] ?? []);
        $neededCount = max(1, 3 - count($matches[0]));

        $prompt = <<<PROMPT
Generate {$neededCount} new H2 section(s) with content for this article about "{$keyword}".

Article title: {$article->title}
Existing H2 headings: {$existingH2s}

Rules:
- Create {$neededCount} new section(s) with ## headings
- Each section should have 2-3 paragraphs of useful content
- Make headings different from existing ones
- Include the keyword naturally in at least one heading
- Do not use em dashes
- Return ONLY the new sections in markdown format
PROMPT;

        $newSections = $this->callAi($aiProvider, $providerConfig, $prompt);

        $newContent = $this->insertBeforeFaq($content, trim($newSections));

        return [
            'field' => 'content',
            'value' => $newContent,
            'message' => "{$neededCount} new section(s) added to content",
        ];
    }

    /**
     * @param  array<string, mixed>  $providerConfig
     * @return array{field: string, value: string, message: string}
     */
    protected function addLists(Article $article, string $keyword, AiProvider $aiProvider, array $providerConfig): array
    {
        $content = $article->content_markdown ?: $article->content;
        $prompt = <<<PROMPT
Generate a useful bulleted list section for this article about "{$keyword}".

Article title: {$article->title}

Rules:
- Create a section with a ## heading
- Include 5-8 bullet points with helpful information
- Each bullet should be a complete, useful point
- Do not use em dashes
- Return ONLY the list section in markdown format
PROMPT;

        $listSection = $this->callAi($aiProvider, $providerConfig, $prompt);

        $newContent = $this->insertBeforeConclusion($content, trim($listSection));

        return [
            'field' => 'content',
            'value' => $newContent,
            'message' => 'Bullet list section added to content',
        ];
    }

    /**
     * @param  array<string, mixed>  $providerConfig
     * @return array{field: string, value: string, message: string}
     */
    protected function optimizeTitleLength(Article $article, string $keyword, AiProvider $aiProvider, array $providerConfig): array
    {
        $currentLength = mb_strlen($article->title);
        $action = $currentLength < 50 ? 'longer' : 'shorter';

        $prompt = <<<PROMPT
Rewrite this title to be {$action} (aim for 50-60 characters).

Current title ({$currentLength} chars): {$article->title}
Keyword: {$keyword}

Rules:
- Keep the same meaning and intent
- Target 50-60 characters
- Include the keyword if possible
- Do not use em dashes
- Return ONLY the new title, nothing else
PROMPT;

        $newTitle = $this->callAi($aiProvider, $providerConfig, $prompt);

        return [
            'field' => 'title',
            'value' => trim($newTitle),
            'message' => 'Title optimized to ideal length',
        ];
    }

    /**
     * @param  array<string, mixed>  $providerConfig
     * @return array{field: string, value: string, message: string}
     */
    protected function optimizeMetaLength(Article $article, string $keyword, AiProvider $aiProvider, array $providerConfig): array
    {
        $currentMeta = $article->meta_description ?? '';
        $currentLength = mb_strlen($currentMeta);
        $action = $currentLength < 150 ? 'expand' : 'shorten';

        $prompt = <<<PROMPT
Rewrite this meta description to be {$action} (aim for 150-160 characters).

Article title: {$article->title}
Current meta ({$currentLength} chars): {$currentMeta}
Keyword: {$keyword}

Rules:
- Keep the same meaning and intent
- Target 150-160 characters exactly
- Include the keyword naturally
- Do not use em dashes
- Return ONLY the meta description, nothing else
PROMPT;

        $newMeta = $this->callAi($aiProvider, $providerConfig, $prompt);

        return [
            'field' => 'meta_description',
            'value' => trim($newMeta),
            'message' => 'Meta description optimized to ideal length',
        ];
    }

    /**
     * @param  array<string, mixed>  $providerConfig
     * @return array{field: string, value: string, message: string}
     */
    protected function addKeywordToH2(Article $article, string $keyword, AiProvider $aiProvider, array $providerConfig): array
    {
        $content = $article->content_markdown ?: $article->content;

        preg_match_all('/^(##\s+)(.+)$/m', $content, $matches, PREG_SET_ORDER);

        $prompt = <<<PROMPT
Rewrite one of these H2 headings to naturally include the keyword "{$keyword}".

Current H2 headings:
{$this->formatH2List($matches)}

Rules:
- Choose the most appropriate heading to modify
- Keep the same meaning and intent
- Include the keyword naturally (variations are OK)
- Do not use em dashes
- Return in format: OLD_HEADING|||NEW_HEADING
PROMPT;

        $response = $this->callAi($aiProvider, $providerConfig, $prompt);

        $parts = explode('|||', $response);
        if (count($parts) === 2) {
            $oldHeading = trim($parts[0]);
            $newHeading = trim($parts[1]);
            $content = str_replace("## {$oldHeading}", "## {$newHeading}", $content);
        }

        return [
            'field' => 'content',
            'value' => $content,
            'message' => 'H2 heading updated to include keyword',
        ];
    }

    /**
     * @param  array<string, mixed>  $providerConfig
     * @return array{field: string, value: string, message: string}
     */
    protected function addKeywordToIntro(Article $article, string $keyword, AiProvider $aiProvider, array $providerConfig): array
    {
        $content = $article->content_markdown ?: $article->content;

        $words = preg_split('/\s+/', $content);
        if ($words === false) {
            $words = [];
        }
        $first150 = implode(' ', array_slice($words, 0, 150));

        $prompt = <<<PROMPT
Rewrite the introduction of this article to include the keyword "{$keyword}" within the first 150 words.

Current introduction:
{$first150}

Rules:
- Keep the same tone and style
- Include the keyword naturally in the first 2-3 sentences
- Make it engaging and informative
- Do not use em dashes
- Return ONLY the rewritten introduction (same approximate length)
PROMPT;

        $newIntro = $this->callAi($aiProvider, $providerConfig, $prompt);

        $remainingWords = array_slice($words, 150);
        $newContent = trim($newIntro).' '.implode(' ', $remainingWords);

        return [
            'field' => 'content',
            'value' => $newContent,
            'message' => 'Introduction updated to include keyword',
        ];
    }

    /**
     * @param  array<string, mixed>  $providerConfig
     */
    protected function callAi(AiProvider $aiProvider, array $providerConfig, string $prompt): string
    {
        $response = $this->prism->text()
            ->using($aiProvider->provider, $aiProvider->model, $providerConfig)
            ->withClientOptions(['timeout' => 60])
            ->withMaxTokens(2000)
            ->withSystemPrompt('You are an SEO expert helping to optimize article content. Be concise and follow instructions exactly.')
            ->withPrompt($prompt)
            ->asText();

        return $response->text;
    }

    protected function insertBeforeConclusion(string $content, string $newSection): string
    {
        $patterns = [
            '/^(##\s+(?:Final Thoughts|Wrapping Up|Summary|Key Takeaways).*)/mi',
            '/^(##\s+FAQ.*)/mi',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $matches, \PREG_OFFSET_CAPTURE)) {
                $position = $matches[0][1];

                return substr($content, 0, $position)."\n\n".$newSection."\n\n".substr($content, $position);
            }
        }

        return $content."\n\n".$newSection;
    }

    protected function insertBeforeFaq(string $content, string $newSection): string
    {
        if (preg_match('/^(##\s+(?:FAQ|Frequently Asked Questions).*)/mi', $content, $matches, \PREG_OFFSET_CAPTURE)) {
            $position = $matches[0][1];

            return substr($content, 0, $position)."\n\n".$newSection."\n\n".substr($content, $position);
        }

        return $this->insertBeforeConclusion($content, $newSection);
    }

    /**
     * @param  array<array<string>>  $matches
     */
    protected function formatH2List(array $matches): string
    {
        $list = [];
        foreach ($matches as $match) {
            $list[] = '- '.$match[2];
        }

        return implode("\n", $list);
    }
}
