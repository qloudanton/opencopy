<?php

namespace App\Services;

use App\Models\AiProvider;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Prism\Prism\Prism;

class BusinessAnalyzerService
{
    public function __construct(
        protected Prism $prism
    ) {}

    /**
     * Analyze a website and generate business information.
     *
     * @return array{name: string, description: string, industry: string, target_audience: string, language: string, country: string}
     */
    public function analyzeWebsite(string $url, User $user, ?AiProvider $aiProvider = null): array
    {
        // Fetch website content
        $websiteContent = $this->fetchWebsiteContent($url);

        if (empty($websiteContent)) {
            throw new \RuntimeException('Could not fetch website content. Please check the URL and try again.');
        }

        // Get AI provider
        $aiProvider = $aiProvider ?? $this->getDefaultProvider($user);

        // Generate business analysis using AI
        return $this->generateBusinessAnalysis($websiteContent, $url, $aiProvider);
    }

    protected function fetchWebsiteContent(string $url): string
    {
        try {
            // Normalize URL
            if (! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://')) {
                $url = 'https://'.$url;
            }

            $response = Http::timeout(30)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; OpenCopyBot/1.0)',
                    'Accept' => 'text/html,application/xhtml+xml',
                ])
                ->get($url);

            if (! $response->successful()) {
                Log::warning('Failed to fetch website', ['url' => $url, 'status' => $response->status()]);

                return '';
            }

            $html = $response->body();

            // Extract useful content from HTML
            return $this->extractTextContent($html);

        } catch (\Exception $e) {
            Log::error('Error fetching website', ['url' => $url, 'error' => $e->getMessage()]);

            return '';
        }
    }

    protected function extractTextContent(string $html): string
    {
        // Remove script and style tags
        $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
        $html = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $html);
        $html = preg_replace('/<noscript\b[^>]*>(.*?)<\/noscript>/is', '', $html);

        // Extract title
        $title = '';
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches)) {
            $title = trim(strip_tags($matches[1]));
        }

        // Extract meta description
        $metaDescription = '';
        if (preg_match('/<meta[^>]*name=["\']description["\'][^>]*content=["\']([^"\']*)["\'][^>]*>/is', $html, $matches)) {
            $metaDescription = trim($matches[1]);
        } elseif (preg_match('/<meta[^>]*content=["\']([^"\']*)["\'][^>]*name=["\']description["\'][^>]*>/is', $html, $matches)) {
            $metaDescription = trim($matches[1]);
        }

        // Extract headings
        $headings = [];
        preg_match_all('/<h[1-3][^>]*>(.*?)<\/h[1-3]>/is', $html, $matches);
        if (! empty($matches[1])) {
            $headings = array_map(fn ($h) => trim(strip_tags($h)), $matches[1]);
            $headings = array_filter($headings);
            $headings = array_slice($headings, 0, 20);
        }

        // Extract main content (paragraphs)
        $paragraphs = [];
        preg_match_all('/<p[^>]*>(.*?)<\/p>/is', $html, $matches);
        if (! empty($matches[1])) {
            $paragraphs = array_map(fn ($p) => trim(strip_tags($p)), $matches[1]);
            $paragraphs = array_filter($paragraphs, fn ($p) => strlen($p) > 50);
            $paragraphs = array_slice($paragraphs, 0, 30);
        }

        // Extract list items (often contain features/services)
        $listItems = [];
        preg_match_all('/<li[^>]*>(.*?)<\/li>/is', $html, $matches);
        if (! empty($matches[1])) {
            $listItems = array_map(fn ($li) => trim(strip_tags($li)), $matches[1]);
            $listItems = array_filter($listItems, fn ($li) => strlen($li) > 10 && strlen($li) < 200);
            $listItems = array_slice($listItems, 0, 20);
        }

        // Compile extracted content
        $content = "TITLE: {$title}\n\n";
        $content .= "META DESCRIPTION: {$metaDescription}\n\n";

        if (! empty($headings)) {
            $content .= "HEADINGS:\n".implode("\n", $headings)."\n\n";
        }

        if (! empty($paragraphs)) {
            $content .= "MAIN CONTENT:\n".implode("\n\n", $paragraphs)."\n\n";
        }

        if (! empty($listItems)) {
            $content .= "FEATURES/SERVICES:\n- ".implode("\n- ", $listItems)."\n";
        }

        // Truncate to reasonable size for AI processing
        return mb_substr($content, 0, 15000);
    }

    protected function getDefaultProvider(User $user): AiProvider
    {
        $provider = $user->aiProviders()
            ->where('is_active', true)
            ->where('supports_text', true)
            ->where('is_default', true)
            ->first();

        if (! $provider) {
            $provider = $user->aiProviders()
                ->where('is_active', true)
                ->where('supports_text', true)
                ->first();
        }

        if (! $provider) {
            throw new \RuntimeException('No active AI provider configured. Please add an AI provider in settings.');
        }

        return $provider;
    }

    /**
     * @return array{name: string, description: string, industry: string, target_audience: string, language: string, country: string}
     */
    protected function generateBusinessAnalysis(string $websiteContent, string $url, AiProvider $aiProvider): array
    {
        $providerConfig = [];
        if ($aiProvider->api_key) {
            $providerConfig['api_key'] = $aiProvider->api_key;
        }
        if ($aiProvider->api_endpoint) {
            $providerConfig['url'] = $aiProvider->api_endpoint;
        }

        $systemPrompt = <<<'PROMPT'
You are a business analyst expert. Analyze the provided website content and extract key business information.

Return a JSON object with these fields:
- name: The business/company name (extract from content, not the domain)
- description: A comprehensive 2-3 paragraph description of the business. Include:
  - What the business does and its main products/services
  - Key features and benefits
  - Target market and value proposition
  - Any notable integrations, partnerships, or unique selling points
  Write in third person, professionally, and factually based on the website content.
- industry: The primary industry/sector (e.g., "SaaS", "E-commerce", "Healthcare", "Finance", "Marketing")
- target_audience: Who the business serves (e.g., "Small businesses", "Enterprise companies", "Freelancers", "Developers")
- language: The primary language of the website content (e.g., "English", "Spanish", "French")
- country: The country the business appears to operate from or target (e.g., "Australia", "United States", "United Kingdom"). Look for currency, phone numbers, addresses, or regional terms.

IMPORTANT: Return ONLY valid JSON, no markdown formatting or code blocks.
PROMPT;

        $userPrompt = "Website URL: {$url}\n\nWebsite Content:\n{$websiteContent}";

        $response = $this->prism->text()
            ->using($aiProvider->provider, $aiProvider->model, $providerConfig)
            ->withClientOptions(['timeout' => 60])
            ->withMaxTokens(2000)
            ->withSystemPrompt($systemPrompt)
            ->withPrompt($userPrompt)
            ->asText();

        $jsonText = $response->text;

        // Clean up potential markdown code blocks
        $jsonText = preg_replace('/^```json\s*/i', '', $jsonText);
        $jsonText = preg_replace('/^```\s*/i', '', $jsonText);
        $jsonText = preg_replace('/\s*```$/i', '', $jsonText);
        $jsonText = trim($jsonText);

        $result = json_decode($jsonText, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('Failed to parse AI response as JSON', [
                'response' => $jsonText,
                'error' => json_last_error_msg(),
            ]);

            throw new \RuntimeException('Failed to analyze website. Please try again.');
        }

        return [
            'name' => $result['name'] ?? '',
            'description' => $result['description'] ?? '',
            'industry' => $result['industry'] ?? '',
            'target_audience' => $result['target_audience'] ?? '',
            'language' => $result['language'] ?? 'English',
            'country' => $result['country'] ?? '',
        ];
    }

    /**
     * Generate target audience suggestions based on business description.
     *
     * @return array<string>
     */
    public function generateTargetAudiences(
        string $businessDescription,
        User $user,
        ?AiProvider $aiProvider = null
    ): array {
        $aiProvider = $aiProvider ?? $this->getDefaultProvider($user);

        $providerConfig = [];
        if ($aiProvider->api_key) {
            $providerConfig['api_key'] = $aiProvider->api_key;
        }
        if ($aiProvider->api_endpoint) {
            $providerConfig['url'] = $aiProvider->api_endpoint;
        }

        $systemPrompt = <<<'PROMPT'
You are a marketing expert. Based on the business description provided, generate a list of specific target audience segments.

Return a JSON object with a single field:
- audiences: An array of 5-7 concise target audience descriptions

Each audience should be:
- SHORT and concise: 5-15 words maximum
- Specific but brief (e.g., "Small e-commerce businesses" not "Small to mid-sized e-commerce businesses needing inventory management solutions")
- Written as a noun phrase (e.g., "Digital agencies and marketing consultancies")
- Can include brief qualifiers in parentheses if needed (e.g., "Professional services firms (legal, accounting)")

Good examples:
- "Australian freelancers and solopreneurs"
- "Small to mid-sized retail businesses"
- "Marketing teams at enterprise companies"
- "Professional services firms (legal, accounting, advisory)"
- "Operations and finance managers"

IMPORTANT: Return ONLY valid JSON, no markdown formatting or code blocks.
PROMPT;

        $userPrompt = "Business Description:\n{$businessDescription}";

        $response = $this->prism->text()
            ->using($aiProvider->provider, $aiProvider->model, $providerConfig)
            ->withClientOptions(['timeout' => 60])
            ->withMaxTokens(1000)
            ->withSystemPrompt($systemPrompt)
            ->withPrompt($userPrompt)
            ->asText();

        $jsonText = $this->cleanJsonResponse($response->text);
        $result = json_decode($jsonText, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('Failed to parse AI response for target audiences', [
                'response' => $jsonText,
                'error' => json_last_error_msg(),
            ]);

            throw new \RuntimeException('Failed to generate target audiences. Please try again.');
        }

        return $result['audiences'] ?? [];
    }

    /**
     * Generate competitor suggestions based on business description.
     *
     * @return array<string>
     */
    public function generateCompetitors(
        string $businessDescription,
        User $user,
        ?AiProvider $aiProvider = null
    ): array {
        $aiProvider = $aiProvider ?? $this->getDefaultProvider($user);

        $providerConfig = [];
        if ($aiProvider->api_key) {
            $providerConfig['api_key'] = $aiProvider->api_key;
        }
        if ($aiProvider->api_endpoint) {
            $providerConfig['url'] = $aiProvider->api_endpoint;
        }

        $systemPrompt = <<<'PROMPT'
You are a market research expert. Based on the business description provided, identify likely competitors in the market.

Return a JSON object with a single field:
- competitors: An array of 5-7 competitor domain names (just the domain, e.g., "notion.so" not "https://notion.so")

Focus on:
- Direct competitors offering similar products/services
- Well-known companies in the same industry
- Mix of larger established players and notable alternatives
- Only include real, existing companies with active websites

IMPORTANT: Return ONLY valid JSON, no markdown formatting or code blocks.
PROMPT;

        $userPrompt = "Business Description:\n{$businessDescription}";

        $response = $this->prism->text()
            ->using($aiProvider->provider, $aiProvider->model, $providerConfig)
            ->withClientOptions(['timeout' => 60])
            ->withMaxTokens(500)
            ->withSystemPrompt($systemPrompt)
            ->withPrompt($userPrompt)
            ->asText();

        $jsonText = $this->cleanJsonResponse($response->text);
        $result = json_decode($jsonText, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('Failed to parse AI response for competitors', [
                'response' => $jsonText,
                'error' => json_last_error_msg(),
            ]);

            throw new \RuntimeException('Failed to generate competitors. Please try again.');
        }

        return $result['competitors'] ?? [];
    }

    /**
     * Generate keyword suggestions based on business information.
     *
     * @return array<array{keyword: string, search_intent: string, difficulty: string, volume: string}>
     */
    public function generateKeywordSuggestions(
        string $businessDescription,
        array $targetAudiences,
        array $competitors,
        User $user,
        ?AiProvider $aiProvider = null
    ): array {
        $aiProvider = $aiProvider ?? $this->getDefaultProvider($user);

        $providerConfig = [];
        if ($aiProvider->api_key) {
            $providerConfig['api_key'] = $aiProvider->api_key;
        }
        if ($aiProvider->api_endpoint) {
            $providerConfig['url'] = $aiProvider->api_endpoint;
        }

        $audiencesList = ! empty($targetAudiences) ? implode(', ', $targetAudiences) : 'General audience';
        $competitorsList = ! empty($competitors) ? implode(', ', $competitors) : 'No specific competitors provided';

        $systemPrompt = <<<'PROMPT'
You are an SEO strategist specializing in content planning and keyword research. Based on the business information provided, generate a strategic list of keywords optimized for ranking opportunity.

Return a JSON object with a single field:
- keywords: An array of 12-15 keyword objects, each with:
  - keyword: The keyword phrase (3-7 words, MUST be long-tail and specific)
  - search_intent: One of "informational", "commercial", "transactional", or "navigational"
  - difficulty: Estimated ranking difficulty - "low", "medium", or "high"
  - volume: Estimated relative search volume - "low", "medium", or "high"

KEYWORD SELECTION STRATEGY:
1. **Product Relevance**: Prioritize keywords highly relevant to the business goals and target audience
2. **Ranking Opportunity**: Focus on LOWER competition keywords that offer quick ranking wins
3. **Long-tail Focus**: Target specific multi-word phrases (4-7 words) that are easier to rank for while driving qualified traffic

COMPETITOR ANALYSIS:
- Consider what keywords the competitors might be ranking for
- Look for gaps where the business could outrank competitors
- Find related topics competitors may have missed

KEYWORD MIX:
- 60% informational (how-to guides, tutorials, explanations) - these build authority
- 30% commercial (comparisons, best-of, reviews) - these drive consideration
- 10% transactional (specific solutions, tools) - these drive conversions

Good examples:
- {"keyword": "how to create a content calendar for small business", "search_intent": "informational", "difficulty": "low", "volume": "medium"}
- {"keyword": "best project management tools for remote teams 2024", "search_intent": "commercial", "difficulty": "medium", "volume": "high"}
- {"keyword": "agile vs waterfall which methodology to choose", "search_intent": "informational", "difficulty": "low", "volume": "medium"}
- {"keyword": "free gantt chart template for marketing teams", "search_intent": "transactional", "difficulty": "low", "volume": "low"}

IMPORTANT: Return ONLY valid JSON, no markdown formatting or code blocks. Sort by difficulty ascending (easiest to rank first).
PROMPT;

        $userPrompt = <<<PROMPT
Business Description:
{$businessDescription}

Target Audiences: {$audiencesList}

Competitors: {$competitorsList}
PROMPT;

        $response = $this->prism->text()
            ->using($aiProvider->provider, $aiProvider->model, $providerConfig)
            ->withClientOptions(['timeout' => 60])
            ->withMaxTokens(2000)
            ->withSystemPrompt($systemPrompt)
            ->withPrompt($userPrompt)
            ->asText();

        $jsonText = $this->cleanJsonResponse($response->text);
        $result = json_decode($jsonText, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('Failed to parse AI response for keywords', [
                'response' => $jsonText,
                'error' => json_last_error_msg(),
            ]);

            throw new \RuntimeException('Failed to generate keywords. Please try again.');
        }

        return $result['keywords'] ?? [];
    }

    /**
     * Analyze keywords to determine difficulty and volume.
     *
     * @param  array<string>  $keywords
     * @return array<array{keyword: string, difficulty: string, volume: string}>
     */
    public function analyzeKeywordMetrics(
        array $keywords,
        string $businessDescription,
        User $user,
        ?AiProvider $aiProvider = null
    ): array {
        if (empty($keywords)) {
            return [];
        }

        $aiProvider = $aiProvider ?? $this->getDefaultProvider($user);

        $providerConfig = [];
        if ($aiProvider->api_key) {
            $providerConfig['api_key'] = $aiProvider->api_key;
        }
        if ($aiProvider->api_endpoint) {
            $providerConfig['url'] = $aiProvider->api_endpoint;
        }

        $keywordsList = implode("\n", array_map(fn ($k) => "- {$k}", $keywords));

        $systemPrompt = <<<'PROMPT'
You are an SEO expert analyzing keyword metrics. For each keyword provided, estimate:

1. **Difficulty** (how hard to rank): "low", "medium", or "high"
   - Low: Long-tail keywords, specific niche topics, low competition
   - Medium: Moderate competition, some established content exists
   - High: Short-tail keywords, highly competitive topics, many authoritative sites

2. **Volume** (relative search volume): "low", "medium", or "high"
   - Low: Niche queries, very specific long-tail phrases
   - Medium: Regular search interest, moderate monthly searches
   - High: Popular topics, high monthly search volume

Consider the business context when analyzing - some keywords may be easier to rank for in specific niches.

Return a JSON object with a single field:
- keywords: An array of objects, each with: keyword, difficulty, volume

IMPORTANT: Return ONLY valid JSON, no markdown formatting or code blocks. Return keywords in the same order as provided.
PROMPT;

        $userPrompt = <<<PROMPT
Business Context:
{$businessDescription}

Keywords to analyze:
{$keywordsList}
PROMPT;

        Log::info('Analyzing keyword metrics', [
            'keyword_count' => count($keywords),
            'keywords' => $keywords,
            'user_prompt' => $userPrompt,
        ]);

        $response = $this->prism->text()
            ->using($aiProvider->provider, $aiProvider->model, $providerConfig)
            ->withClientOptions(['timeout' => 120])
            ->withMaxTokens(4000)
            ->withSystemPrompt($systemPrompt)
            ->withPrompt($userPrompt)
            ->asText();

        Log::info('AI response for keyword metrics', [
            'raw_response' => $response->text,
        ]);

        $jsonText = $this->cleanJsonResponse($response->text);
        $result = json_decode($jsonText, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('Failed to parse AI response for keyword metrics', [
                'response' => $jsonText,
                'error' => json_last_error_msg(),
            ]);

            throw new \RuntimeException('Failed to analyze keywords. Please try again.');
        }

        Log::info('Parsed keyword metrics', [
            'result_count' => count($result['keywords'] ?? []),
            'results' => $result['keywords'] ?? [],
        ]);

        return $result['keywords'] ?? [];
    }

    /**
     * Clean up AI response by removing markdown code blocks.
     */
    protected function cleanJsonResponse(string $text): string
    {
        $text = preg_replace('/^```json\s*/i', '', $text);
        $text = preg_replace('/^```\s*/i', '', $text);
        $text = preg_replace('/\s*```$/i', '', $text);

        return trim($text);
    }
}
