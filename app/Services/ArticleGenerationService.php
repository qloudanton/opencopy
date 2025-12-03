<?php

namespace App\Services;

use App\Models\AiProvider;
use App\Models\Article;
use App\Models\Keyword;
use App\Models\ProjectPage;
use App\Models\ScheduledContent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Prism\Prism\Prism;
use Prism\Prism\Text\Response as TextResponse;

class ArticleGenerationService
{
    protected Keyword $keyword;

    protected ScheduledContent $scheduledContent;

    protected AiProvider $aiProvider;

    protected ?string $generatedContent = null;

    protected ?TextResponse $lastResponse = null;

    protected array $usedSmartLinks = [];

    public function __construct(
        protected Prism $prism,
        protected SeoScoreService $seoScoreService,
        protected ArticleImageService $articleImageService,
        protected UsageTrackingService $usageTrackingService,
        protected YouTubeService $youTubeService,
        protected SitemapService $sitemapService
    ) {}

    public function generate(ScheduledContent $scheduledContent, ?AiProvider $aiProvider = null): Article
    {
        $startTime = microtime(true);
        $this->scheduledContent = $scheduledContent;
        $this->keyword = $scheduledContent->keyword;

        Log::info('[ArticleGeneration] Starting generation', [
            'scheduled_content_id' => $scheduledContent->id,
            'keyword_id' => $this->keyword->id,
            'keyword' => $this->keyword->keyword,
            'project_id' => $scheduledContent->project_id,
        ]);

        $this->aiProvider = $aiProvider ?? $this->getDefaultProvider();

        Log::info('[ArticleGeneration] Using AI provider', [
            'provider_id' => $this->aiProvider->id,
            'provider' => $this->aiProvider->provider,
            'model' => $this->aiProvider->model,
        ]);

        $this->validateProvider();

        // Mark scheduled content as generating
        $this->scheduledContent->startGeneration();

        try {
            Log::info('[ArticleGeneration] Calling AI API...', [
                'keyword_id' => $this->keyword->id,
                'timeout' => 300,
            ]);

            $aiStartTime = microtime(true);
            $this->generatedContent = $this->callAi();
            $aiDuration = round(microtime(true) - $aiStartTime, 2);

            Log::info('[ArticleGeneration] AI API call completed', [
                'keyword_id' => $this->keyword->id,
                'duration_seconds' => $aiDuration,
                'content_length' => strlen($this->generatedContent ?? ''),
            ]);

            Log::info('[ArticleGeneration] Creating article in database...', [
                'keyword_id' => $this->keyword->id,
            ]);

            $article = DB::transaction(function () {
                $article = $this->createArticle();

                // Mark scheduled content as complete
                $this->scheduledContent->completeGeneration($article);

                return $article;
            });

            Log::info('[ArticleGeneration] Article created', [
                'article_id' => $article->id,
                'title' => $article->title,
                'word_count' => $article->word_count,
            ]);

            // Log usage for text generation
            $this->logUsage($article);

            // Check if we need to do any enrichment
            $project = $this->keyword->project;
            $needsEnrichment = ($project->generate_inline_images && $project->image_style)
                || $this->keyword->project->include_youtube_videos;

            if ($needsEnrichment) {
                // Set status to enriching while we add images, videos, etc.
                $this->scheduledContent->startEnriching();
            }

            // Process inline image placeholders if enabled and project has image style configured
            if ($project->generate_inline_images && $project->image_style) {
                Log::info('[ArticleGeneration] Processing inline images...', [
                    'article_id' => $article->id,
                ]);
                $this->processInlineImages($article);
            }

            // Process video placeholders if YouTube is configured and videos are enabled
            if ($this->keyword->project->include_youtube_videos) {
                Log::info('[ArticleGeneration] Processing video placeholders...', [
                    'article_id' => $article->id,
                ]);
                $this->processVideoPlaceholders($article);
            }

            // Track smart link usage
            $this->trackSmartLinkUsage();

            // Calculate SEO score after article is created
            Log::info('[ArticleGeneration] Calculating SEO score...', [
                'article_id' => $article->id,
            ]);
            $this->seoScoreService->calculateAndSave($article);

            // Complete enrichment and restore previous status
            if ($needsEnrichment) {
                $this->scheduledContent->completeEnriching();
            }

            $totalDuration = round(microtime(true) - $startTime, 2);
            Log::info('[ArticleGeneration] Generation completed successfully', [
                'keyword_id' => $this->keyword->id,
                'article_id' => $article->id,
                'total_duration_seconds' => $totalDuration,
                'ai_duration_seconds' => $aiDuration,
            ]);

            return $article->fresh();
        } catch (\Exception $e) {
            $totalDuration = round(microtime(true) - $startTime, 2);
            Log::error('[ArticleGeneration] Generation failed', [
                'keyword_id' => $this->keyword->id,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'duration_seconds' => $totalDuration,
                'trace' => $e->getTraceAsString(),
            ]);

            // Mark scheduled content as failed
            $this->scheduledContent->failGeneration($e->getMessage());

            throw $e;
        }
    }

    protected function getDefaultProvider(): AiProvider
    {
        // Get the project's effective text provider (Project → Account Default → Any Active)
        $provider = $this->keyword->project->getEffectiveTextProvider();

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

        $systemPrompt = $this->buildSystemPrompt();
        $userPrompt = $this->buildUserPrompt();

        Log::debug('[ArticleGeneration] Prepared prompts', [
            'keyword_id' => $this->keyword->id,
            'system_prompt_length' => strlen($systemPrompt),
            'user_prompt_length' => strlen($userPrompt),
            'provider' => $this->aiProvider->provider,
            'model' => $this->aiProvider->model,
            'has_custom_endpoint' => ! empty($this->aiProvider->api_endpoint),
        ]);

        Log::info('[ArticleGeneration] Sending request to AI provider...', [
            'keyword_id' => $this->keyword->id,
        ]);

        $this->lastResponse = $this->prism->text()
            ->using($this->aiProvider->provider, $this->aiProvider->model, $providerConfig)
            ->withClientOptions(['timeout' => 300]) // 5 minutes for long article generation
            ->withMaxTokens(16000) // Allow for longer articles (default is 2048)
            ->withSystemPrompt($systemPrompt)
            ->withPrompt($userPrompt)
            ->asText();

        Log::info('[ArticleGeneration] Received response from AI provider', [
            'keyword_id' => $this->keyword->id,
            'response_length' => strlen($this->lastResponse->text ?? ''),
            'prompt_tokens' => $this->lastResponse->usage->promptTokens ?? null,
            'completion_tokens' => $this->lastResponse->usage->completionTokens ?? null,
        ]);

        return $this->lastResponse->text;
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
        $project = $this->keyword->project;

        $prompt = <<<'PROMPT'
You are an expert content strategist and SEO specialist who creates comprehensive, authoritative articles that outrank competitors. Your content demonstrates E-E-A-T (Experience, Expertise, Authoritativeness, Trustworthiness) and provides genuine value that readers can't find elsewhere.

## Your Content Philosophy
- Every article should be the DEFINITIVE resource on the topic
- Provide specific, actionable advice - never generic filler content
- Include real examples, data, and practical insights
- Write like an expert sharing knowledge with a colleague, not a content mill producing fluff
- Answer the reader's question completely - leave no stone unturned

## Quality Standards (NON-NEGOTIABLE)
1. **Depth Over Brevity**: Cover every relevant aspect of the topic. If a section feels thin, expand it with examples, edge cases, or related considerations
2. **Specificity**: Replace vague statements with concrete details. Instead of "this can save time," say "this typically reduces processing time from 2 hours to 15 minutes"
3. **Actionable Content**: Every major section should give readers something they can DO or APPLY
4. **Original Insights**: Include perspectives, tips, or connections that aren't obvious from a basic Google search
5. **Scannable Structure**: Use bullet points, numbered lists, tables, blockquotes and clear headings so readers can find what they need

## CRITICAL WRITING RULES (ABSOLUTE REQUIREMENTS)

### Rule 1: No Em Dashes
NEVER use em dashes (—) or double hyphens (--) in your writing. Instead:
- Use commas, colons, or parentheses for asides
- Break long sentences into shorter ones
- Use "which" or "that" clauses

### Rule 2: No Numbered Headings
NEVER use numbered headings like "1. Topic", "2. Topic", "1.1 Subtopic", "2.1 Subtopic".
- Use descriptive, topic-specific headings only
- Good: "## Legal & Tax Requirements" / "### Core Details Every Invoice Needs"
- Bad: "## 2. Legal & Tax Requirements" / "### 2.1 Core Details Every Invoice Needs"
Numbered headings make content read like a school textbook or academic paper.

### Rule 3: No Generic Section Names
NEVER use "Introduction" or "Conclusion" as heading text.
- Opening section: Use a topic-relevant, engaging heading (e.g., "Why [Topic] Matters" or "The [Topic] Problem")
- Closing section: Use actionable headings (e.g., "Your Next Steps", "Getting Started", "Putting It Into Practice")
These generic terms make content feel like a school essay.

## Article Architecture Requirements
- **Opening section** (150-200 words): Hook with a pain point or intriguing fact. Use a compelling, topic-specific heading (NOT "Introduction").
- **Main Sections**: 6-8 H2 sections with descriptive headings, each with 2-4 H3 subsections where appropriate
- **Each H2 Section**: Minimum 200-300 words with specific details, examples, or steps
- **Closing section**: Wrap up with next steps and forward-looking guidance. Use an actionable heading (NOT "Conclusion").
- **FAQ Section**: 4-6 questions that target "People Also Ask" queries related to the topic

## Content Formatting: Tables vs Lists
Use **tables** when presenting:
- Structured data with multiple attributes (e.g., requirements with name, description, and status columns)
- Comparisons between options, features, or alternatives
- Reference information readers will scan or look up repeatedly
- Checklists with multiple data points per item

Use **bullet lists** when presenting:
- Simple sequences of items without multiple attributes
- Steps in a process (but consider if a table with step/action/notes columns would be clearer)
- Quick tips or warnings

Example: If listing "invoice requirements" with name, description, and whether each is mandatory, use a TABLE, not a numbered list.

## Content Enrichment (Include Where Relevant)
- Statistics with context (explain what the numbers mean)
- Step-by-step instructions
- Real-world examples or case studies
- Comparison tables for features, options, or alternatives
- Pro tips, warnings, or common mistakes to avoid
- Checklists or templates readers can use
- Expert quotes or industry insights

## Callout Boxes (REQUIRED)
Include 1-3 blockquote callouts throughout the article to highlight important information. Use this format:

> **Key Takeaway:** [Important insight the reader should remember]

or

> **Pro Tip:** [Actionable advice that gives readers an edge]

Other callout types you can use:
- **Important:** for critical information
- **Warning:** for common mistakes or pitfalls
- **Real-World Example:** for practical scenarios
- **Expert Insight:** for industry wisdom

Place these strategically after explaining a concept, to emphasize crucial points, or to share practical wisdom. They break up the text and make key information scannable.

PROMPT;

        // Add target audiences context
        if (! empty($project->target_audiences)) {
            $audiencesList = implode(', ', $project->target_audiences);
            $prompt .= "\n## Target Audiences\n{$audiencesList}\nWrite specifically for these audiences - address their knowledge level, concerns, and goals.\n";
        }

        // Add brand guidelines
        if ($project->brand_guidelines) {
            $prompt .= "\n## Brand Voice & Guidelines\n{$project->brand_guidelines}\n";
        }

        $prompt .= <<<'PROMPT'

## Output Format
Return your response in this exact markdown format:

---
title: [Compelling, keyword-rich title - 50-60 characters ideal]
meta_description: [Action-oriented description with keyword - exactly 150-160 characters]
---

[Article content with proper markdown formatting]

## Frequently Asked Questions

### [Question 1]?
[Comprehensive answer - 50-100 words]

### [Question 2]?
[Comprehensive answer - 50-100 words]

[Continue with 4-6 FAQs total]
PROMPT;

        return $prompt;
    }

    protected function buildUserPrompt(): string
    {
        $keyword = $this->keyword;
        $project = $keyword->project;
        $internalLinks = $this->getRelevantInternalLinks();

        $prompt = "# Article Request\n\n";
        $prompt .= "**Primary Keyword:** {$keyword->keyword}\n\n";

        if ($keyword->secondary_keywords) {
            $secondaryList = implode(', ', $keyword->secondary_keywords);
            $prompt .= "**Secondary Keywords** (weave naturally throughout): {$secondaryList}\n\n";
        }

        // Search intent with specific guidance
        $prompt .= $this->buildSearchIntentInstructions($keyword->search_intent);

        // Word count with target range (not just minimum)
        $wordCount = $keyword->target_word_count ?? $project->default_word_count ?? 1500;
        $minWords = (int) round($wordCount * 0.9);
        $maxWords = (int) round($wordCount * 1.15);
        $prompt .= "## Word Count Requirement\n";
        $prompt .= "**TARGET: {$minWords}-{$maxWords} words** (aiming for ~{$wordCount})\n\n";
        $prompt .= "IMPORTANT: Write exactly as much as needed to cover the topic thoroughly - no more, no less.\n";
        $prompt .= "- Do NOT pad content to hit word counts\n";
        $prompt .= "- Do NOT repeat information in different words\n";
        $prompt .= "- Every sentence must add unique value\n";
        $prompt .= "- If the topic is fully covered in fewer words, that's fine\n";
        $prompt .= "- Exceeding {$maxWords} words likely means you're adding filler\n\n";
        $prompt .= "Suggested structure:\n";
        $prompt .= "- Introduction: 100-150 words (get to the point quickly)\n";
        $prompt .= "- Each main section (H2): 150-300 words of substantive content\n";
        $prompt .= "- FAQ section: 200-400 words total\n";
        $prompt .= "- Conclusion: 75-100 words\n\n";

        $tone = $keyword->tone ?? $project->default_tone ?? 'professional';
        $prompt .= "**Writing Tone:** {$tone}\n\n";

        if ($internalLinks->isNotEmpty()) {
            $prompt .= "## Internal Links to Include\n";
            $prompt .= "Incorporate these links naturally within relevant sections:\n";
            foreach ($internalLinks as $link) {
                $prompt .= "- [{$link->anchor_text}]({$link->url})";
                if ($link->description) {
                    $prompt .= " - {$link->description}";
                }
                $prompt .= "\n";
            }
            $prompt .= "\n";
        }

        // Engagement settings
        $prompt .= $this->buildEngagementInstructions();

        if ($keyword->additional_instructions) {
            $prompt .= "## Special Instructions\n{$keyword->additional_instructions}\n\n";
        }

        // Final quality reminder
        $prompt .= "## Final Checklist (Verify Before Submitting)\n";
        $prompt .= "- [ ] Article is {$minWords}-{$maxWords} words (not significantly over or under)\n";
        $prompt .= "- [ ] At least 4-6 H2 sections with substantive content\n";
        $prompt .= "- [ ] Each section provides specific, actionable information\n";
        $prompt .= "- [ ] Includes real examples, statistics, or data where relevant\n";
        $prompt .= "- [ ] FAQ section with 3-5 relevant questions\n";
        $prompt .= "- [ ] No filler, fluff, or repetition - every sentence earns its place\n";
        $prompt .= "- [ ] A reader would feel satisfied, not overwhelmed\n";

        return $prompt;
    }

    protected function buildSearchIntentInstructions(?string $intent): string
    {
        $instructions = "## Search Intent & Content Strategy\n\n";

        switch ($intent) {
            case 'informational':
                $instructions .= "**Intent: Informational** - The reader wants to LEARN and UNDERSTAND.\n\n";
                $instructions .= "Content approach:\n";
                $instructions .= "- Start with a clear, concise definition or answer to the main question\n";
                $instructions .= "- Expand into comprehensive detail with examples and context\n";
                $instructions .= "- Include \"what,\" \"why,\" \"how,\" and \"when\" perspectives\n";
                $instructions .= "- Add expert tips and common misconceptions\n";
                $instructions .= "- Use educational tone - explain concepts thoroughly\n";
                $instructions .= "- Include a \"Key Takeaways\" or \"Summary\" box\n\n";
                break;

            case 'transactional':
                $instructions .= "**Intent: Transactional** - The reader is ready to TAKE ACTION or BUY.\n\n";
                $instructions .= "Content approach:\n";
                $instructions .= "- Lead with the value proposition and primary benefits\n";
                $instructions .= "- Include pricing information, comparisons, or options where relevant\n";
                $instructions .= "- Address common objections and concerns\n";
                $instructions .= "- Provide clear next steps and calls-to-action\n";
                $instructions .= "- Include trust signals (reviews, guarantees, credentials)\n";
                $instructions .= "- Make the path to conversion crystal clear\n\n";
                break;

            case 'commercial':
                $instructions .= "**Intent: Commercial Investigation** - The reader is RESEARCHING before deciding.\n\n";
                $instructions .= "Content approach:\n";
                $instructions .= "- Provide objective comparisons and analysis\n";
                $instructions .= "- Include pros/cons lists and feature comparisons\n";
                $instructions .= "- Cover different options, alternatives, and use cases\n";
                $instructions .= "- Address \"best for\" scenarios (best for beginners, best for budget, etc.)\n";
                $instructions .= "- Include decision-making frameworks or criteria\n";
                $instructions .= "- Help readers make an informed choice\n\n";
                break;

            case 'navigational':
                $instructions .= "**Intent: Navigational** - The reader wants to FIND something specific.\n\n";
                $instructions .= "Content approach:\n";
                $instructions .= "- Provide direct, clear pathways to what they're looking for\n";
                $instructions .= "- Include step-by-step navigation instructions\n";
                $instructions .= "- Add relevant shortcuts, tips, and alternatives\n";
                $instructions .= "- Anticipate related needs and provide those resources too\n";
                $instructions .= "- Be concise but comprehensive about the destination\n\n";
                break;

            default:
                $instructions .= "**Intent: General** - Create comprehensive, valuable content.\n\n";
                $instructions .= "Content approach:\n";
                $instructions .= "- Balance educational depth with practical application\n";
                $instructions .= "- Cover the topic from multiple angles\n";
                $instructions .= "- Include both foundational information and advanced insights\n";
                $instructions .= "- Make content useful for readers at different knowledge levels\n\n";
                break;
        }

        return $instructions;
    }

    protected function buildEngagementInstructions(): string
    {
        $project = $this->keyword->project;
        $instructions = "## Content Enhancement Requirements\n\n";
        $hasEnhancements = false;

        // Emojis
        if ($project->include_emojis) {
            $hasEnhancements = true;
            $instructions .= "**Emojis:** Include relevant emojis in headings and throughout the content to make it more engaging and scannable. Use them sparingly but effectively (1-2 per major section).\n\n";
        }

        // YouTube Videos
        if ($project->include_youtube_videos) {
            $hasEnhancements = true;
            $instructions .= "**YouTube Videos:** Search for and embed 1-2 relevant YouTube videos that complement the content. Include them using this format:\n";
            $instructions .= "```\n[VIDEO: Search for \"topic keyword\" on YouTube and embed a relevant, high-quality video here]\n```\n\n";
        }

        // Infographic Placeholders
        if ($project->include_infographic_placeholders) {
            $hasEnhancements = true;
            $instructions .= "**Infographics:** Add 1-2 infographic placeholder sections where visual data representation would enhance understanding. Use this format:\n";
            $instructions .= "```\n[INFOGRAPHIC: Description of what the infographic should show - e.g., \"Step-by-step process diagram\" or \"Comparison chart of X vs Y\"]\n```\n\n";
        }

        // Image Placeholders
        if ($project->image_style) {
            $hasEnhancements = true;
            $instructions .= "**Images:** Add image placeholders throughout the article where visuals would enhance the content. Use this format:\n";
            $instructions .= "```\n[IMAGE: Description of the image - style: {$project->image_style}]\n```\n";
            $instructions .= "Include at least one featured image placeholder at the beginning and 2-3 content images throughout.\n\n";
        }

        // Call-to-Action
        if ($project->include_cta && $project->cta_product_name) {
            $hasEnhancements = true;
            $instructions .= "**Call-to-Action:** Include a contextual, naturally-placed CTA section that promotes the following product. Make it relevant to the article topic and helpful to the reader.\n\n";
            $instructions .= "Product details for CTA:\n";
            $instructions .= "- Product name: {$project->cta_product_name}\n";

            if ($project->cta_website_url) {
                $instructions .= "- Website: {$project->cta_website_url}\n";
            }

            if ($project->cta_features) {
                $instructions .= "- Key features/benefits: {$project->cta_features}\n";
            }

            if ($project->cta_action_text) {
                $instructions .= "- Call-to-action text: {$project->cta_action_text}\n";
            }

            $instructions .= "\nWrite a compelling CTA that connects the article topic to how the product can help. Place it naturally within the content or as a dedicated section near the end. Do NOT use generic marketing language - make it specific to the article topic.\n\n";
        }

        if (! $hasEnhancements) {
            return '';
        }

        return $instructions;
    }

    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    protected function getRelevantInternalLinks()
    {
        $project = $this->keyword->project;
        $links = collect();

        // Get manually configured internal links
        $manualLinks = $project
            ->internalLinks()
            ->where('is_active', true)
            ->orderBy('priority', 'desc')
            ->limit(5)
            ->get()
            ->map(fn ($link) => (object) [
                'url' => $link->url,
                'anchor_text' => $link->anchor_text,
                'description' => $link->description,
                'is_smart_link' => false,
            ]);

        $links = $links->merge($manualLinks);

        // Get smart links from sitemap pages if enabled
        if ($project->auto_internal_linking && $project->pages()->exists()) {
            $limit = $project->internal_links_per_article ?? 3;
            $remainingSlots = max(0, $limit - $links->count());

            if ($remainingSlots > 0) {
                $smartLinks = $this->getSmartLinksForKeyword($remainingSlots);
                $links = $links->merge($smartLinks);
            }
        }

        return $links;
    }

    /**
     * Get relevant smart links from project pages based on keyword relevance.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    protected function getSmartLinksForKeyword(int $limit)
    {
        $project = $this->keyword->project;
        $keywords = array_merge(
            [$this->keyword->keyword],
            $this->keyword->secondary_keywords ?? []
        );

        // Use sitemap service to get relevant pages
        $relevantPages = $this->sitemapService->getRelevantPagesForArticle(
            $project,
            '', // We don't have article content yet
            $keywords,
            $limit
        );

        // Track which pages we're using for link count updates
        $this->usedSmartLinks = $relevantPages->pluck('id')->toArray();

        return $relevantPages->map(fn (ProjectPage $page) => (object) [
            'url' => $page->url,
            'anchor_text' => $page->title ?: $this->generateAnchorText($page->url),
            'description' => 'Related content from your website',
            'is_smart_link' => true,
            'page_id' => $page->id,
        ]);
    }

    protected function generateAnchorText(string $url): string
    {
        $parsed = parse_url($url);
        $path = $parsed['path'] ?? '';

        // Get the last path segment
        $segments = array_filter(explode('/', $path));
        $lastSegment = end($segments);

        if (! $lastSegment) {
            return 'Learn more';
        }

        // Convert hyphens/underscores to spaces and title case
        $title = str_replace(['-', '_'], ' ', $lastSegment);

        return Str::title($title);
    }

    protected function trackSmartLinkUsage(): void
    {
        if (empty($this->usedSmartLinks)) {
            return;
        }

        // Increment link count for all used pages
        ProjectPage::whereIn('id', $this->usedSmartLinks)
            ->increment('link_count');

        // Reset for next generation
        $this->usedSmartLinks = [];
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

    protected function processInlineImages(Article $article): void
    {
        try {
            // Get the project's effective image provider (may be different from text provider)
            $imageProvider = $this->keyword->project->getEffectiveImageProvider();

            if (! $imageProvider) {
                Log::warning('[ArticleGeneration] No image provider configured, skipping inline images', [
                    'article_id' => $article->id,
                ]);

                return;
            }

            $result = $this->articleImageService->processArticleImages($article, $imageProvider);

            // Log any errors but don't fail the article generation
            if (! empty($result['errors'])) {
                Log::warning('[ArticleGeneration] Some inline images failed to generate', [
                    'article_id' => $article->id,
                    'errors' => $result['errors'],
                ]);
            }
        } catch (\Exception $e) {
            // Log the error but don't fail the article generation
            Log::error('[ArticleGeneration] Failed to process inline images', [
                'article_id' => $article->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function processVideoPlaceholders(Article $article): void
    {
        $user = $this->keyword->project->user;

        // Check if user has YouTube API configured
        if (! $user->settings?->hasYouTubeApiKey()) {
            Log::info('[ArticleGeneration] YouTube API key not configured, skipping video placeholders', [
                'article_id' => $article->id,
            ]);

            return;
        }

        try {
            $content = $article->content_markdown ?? $article->content;
            $processedContent = $this->youTubeService
                ->forUser($user)
                ->processVideoPlaceholders($content);

            // Only update if content changed
            if ($processedContent !== $content) {
                $article->update([
                    'content' => $processedContent,
                    'content_markdown' => $processedContent,
                ]);

                Log::info('[ArticleGeneration] Processed video placeholders in article', [
                    'article_id' => $article->id,
                ]);
            }
        } catch (\Exception $e) {
            // Log the error but don't fail the article generation
            Log::error('[ArticleGeneration] Failed to process video placeholders', [
                'article_id' => $article->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function logUsage(Article $article): void
    {
        if (! $this->lastResponse) {
            return;
        }

        $usage = $this->lastResponse->usage;

        $this->usageTrackingService->logTextGeneration(
            user: $this->keyword->project->user,
            article: $article,
            aiProvider: $this->aiProvider,
            model: $this->aiProvider->model,
            inputTokens: $usage->promptTokens,
            outputTokens: $usage->completionTokens,
            operation: 'article_generation',
            metadata: [
                'keyword' => $this->keyword->keyword,
                'target_word_count' => $this->keyword->target_word_count,
                'generated_word_count' => $article->word_count,
                'cache_write_tokens' => $usage->cacheWriteInputTokens,
                'cache_read_tokens' => $usage->cacheReadInputTokens,
            ]
        );
    }
}
