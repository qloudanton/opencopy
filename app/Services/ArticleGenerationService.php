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
            ->withClientOptions(['timeout' => 300]) // 5 minutes for long article generation
            ->withMaxTokens(16000) // Allow for longer articles (default is 2048)
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
5. **Scannable Structure**: Use bullet points, numbered lists, tables, and clear headings so readers can find what they need

## CRITICAL WRITING RULE (ABSOLUTE REQUIREMENT)
NEVER use em dashes (—) or double hyphens (--) in your writing. This is non-negotiable. Instead:
- Use commas, colons, or parentheses for asides
- Break long sentences into shorter ones
- Use "which" or "that" clauses
Em dashes are a telltale sign of AI-generated content and must be avoided completely.

## Article Architecture Requirements
- **Introduction** (150-200 words): Hook with a pain point or intriguing fact, establish credibility, preview what they'll learn
- **Main Sections**: Minimum 6-8 H2 sections, each with 2-4 H3 subsections where appropriate
- **Each H2 Section**: Minimum 200-300 words with specific details, examples, or steps
- **Conclusion**: Summarize key takeaways, provide next steps, include a forward-looking statement
- **FAQ Section**: 4-6 questions that target "People Also Ask" queries related to the topic

## Content Enrichment (Include Where Relevant)
- Statistics with context (don't just cite numbers - explain what they mean)
- Step-by-step instructions with clear numbering
- Real-world examples or case studies
- Comparison tables for features, options, or alternatives
- Pro tips, warnings, or common mistakes to avoid
- Checklists or templates readers can use
- Expert quotes or industry insights

PROMPT;

        // Add target audience context
        if ($project->target_audience) {
            $prompt .= "\n## Target Audience\n{$project->target_audience}\nWrite specifically for this audience - address their knowledge level, concerns, and goals.\n";
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

        // Word count with strict enforcement
        $wordCount = $keyword->target_word_count ?? $project->default_word_count ?? 1500;
        $prompt .= "## Word Count Requirement\n";
        $prompt .= "**MINIMUM {$wordCount} words** - This is a hard requirement, not a suggestion.\n";
        $prompt .= "- Introduction: 150-200 words\n";
        $prompt .= "- Each main section (H2): 200-400 words\n";
        $prompt .= "- FAQ section: 300-500 words total\n";
        $prompt .= "- Conclusion: 100-150 words\n\n";

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
        $prompt .= "- [ ] Article meets or exceeds {$wordCount} words\n";
        $prompt .= "- [ ] Minimum 6 H2 sections with substantive content\n";
        $prompt .= "- [ ] Each section provides specific, actionable information\n";
        $prompt .= "- [ ] Includes real examples, statistics, or case studies\n";
        $prompt .= "- [ ] FAQ section with 4-6 relevant questions\n";
        $prompt .= "- [ ] No generic filler content - every paragraph adds value\n";

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
