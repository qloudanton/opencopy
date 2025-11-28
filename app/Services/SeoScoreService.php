<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Keyword;

class SeoScoreService
{
    protected Article $article;

    protected ?Keyword $keyword;

    protected string $content;

    protected string $primaryKeyword;

    /**
     * @var array<string>
     */
    protected array $secondaryKeywords;

    /**
     * Calculate SEO score for an article.
     *
     * @return array{score: int, breakdown: array<string, array{score: int, max: int, details: array<string, mixed>}>}
     */
    public function calculate(Article $article): array
    {
        $this->article = $article;
        $this->keyword = $article->keyword;
        $this->content = $article->content_markdown ?: $article->content;
        $this->primaryKeyword = $this->keyword?->keyword ?? '';
        $this->secondaryKeywords = $this->keyword?->secondary_keywords ?? [];

        $breakdown = [
            'keyword_optimization' => $this->scoreKeywordOptimization(),
            'content_structure' => $this->scoreContentStructure(),
            'content_length' => $this->scoreContentLength(),
            'meta_quality' => $this->scoreMetaQuality(),
            'enrichment' => $this->scoreEnrichment(),
        ];

        $totalScore = array_sum(array_column($breakdown, 'score'));
        $maxScore = array_sum(array_column($breakdown, 'max'));

        // Normalize to 0-100
        $normalizedScore = $maxScore > 0 ? (int) round(($totalScore / $maxScore) * 100) : 0;

        return [
            'score' => $normalizedScore,
            'breakdown' => $breakdown,
        ];
    }

    /**
     * Calculate and save the SEO score to the article.
     */
    public function calculateAndSave(Article $article): int
    {
        $result = $this->calculate($article);

        $article->update([
            'seo_score' => $result['score'],
            'generation_metadata' => array_merge(
                $article->generation_metadata ?? [],
                ['seo_breakdown' => $result['breakdown']]
            ),
        ]);

        return $result['score'];
    }

    /**
     * Score keyword optimization (max 35 points).
     *
     * @return array{score: int, max: int, details: array<string, mixed>}
     */
    protected function scoreKeywordOptimization(): array
    {
        if (empty($this->primaryKeyword)) {
            return ['score' => 0, 'max' => 35, 'details' => ['no_keyword' => true]];
        }

        $details = [];
        $score = 0;

        // Keyword in title (10 points)
        $keywordInTitle = $this->containsKeyword($this->article->title, $this->primaryKeyword);
        $details['keyword_in_title'] = $keywordInTitle;
        if ($keywordInTitle) {
            $score += 10;
        }

        // Keyword in meta description (8 points)
        $keywordInMeta = $this->containsKeyword($this->article->meta_description ?? '', $this->primaryKeyword);
        $details['keyword_in_meta'] = $keywordInMeta;
        if ($keywordInMeta) {
            $score += 8;
        }

        // Keyword in first 150 words (7 points)
        $first150Words = implode(' ', array_slice(str_word_count($this->content, 1), 0, 150));
        $keywordInFirst150 = $this->containsKeyword($first150Words, $this->primaryKeyword);
        $details['keyword_in_first_150_words'] = $keywordInFirst150;
        if ($keywordInFirst150) {
            $score += 7;
        }

        // Keyword density 1-2% (5 points)
        $density = $this->calculateKeywordDensity($this->content, $this->primaryKeyword);
        $details['keyword_density'] = round($density, 2);
        if ($density >= 0.5 && $density <= 2.5) {
            $score += 5;
        } elseif ($density > 0 && $density < 0.5) {
            $score += 2; // Partial credit
        }

        // Keyword in at least one H2 (5 points)
        preg_match_all('/^##\s+(.+)$/m', $this->content, $h2Matches);
        $keywordInH2 = false;
        foreach ($h2Matches[1] ?? [] as $heading) {
            if ($this->containsKeyword($heading, $this->primaryKeyword)) {
                $keywordInH2 = true;
                break;
            }
        }
        $details['keyword_in_h2'] = $keywordInH2;
        if ($keywordInH2) {
            $score += 5;
        }

        return ['score' => $score, 'max' => 35, 'details' => $details];
    }

    /**
     * Score content structure (max 25 points).
     *
     * @return array{score: int, max: int, details: array<string, mixed>}
     */
    protected function scoreContentStructure(): array
    {
        $details = [];
        $score = 0;

        // H2 count - at least 3 (8 points)
        preg_match_all('/^##\s+/m', $this->content, $h2Matches);
        $h2Count = count($h2Matches[0]);
        $details['h2_count'] = $h2Count;
        if ($h2Count >= 5) {
            $score += 8;
        } elseif ($h2Count >= 3) {
            $score += 6;
        } elseif ($h2Count >= 1) {
            $score += 3;
        }

        // H3 subheadings for depth (5 points)
        preg_match_all('/^###\s+/m', $this->content, $h3Matches);
        $h3Count = count($h3Matches[0]);
        $details['h3_count'] = $h3Count;
        if ($h3Count >= 4) {
            $score += 5;
        } elseif ($h3Count >= 2) {
            $score += 3;
        } elseif ($h3Count >= 1) {
            $score += 1;
        }

        // Uses bullet or numbered lists (4 points)
        $hasBulletLists = (bool) preg_match('/^[\-\*]\s+/m', $this->content);
        $hasNumberedLists = (bool) preg_match('/^\d+\.\s+/m', $this->content);
        $details['has_lists'] = $hasBulletLists || $hasNumberedLists;
        if ($hasBulletLists || $hasNumberedLists) {
            $score += 4;
        }

        // Uses tables (4 points)
        $hasTables = (bool) preg_match('/\|.+\|/', $this->content);
        $details['has_tables'] = $hasTables;
        if ($hasTables) {
            $score += 4;
        }

        // Has FAQ section (4 points)
        $hasFaq = (bool) preg_match('/FAQ|Frequently Asked Questions/i', $this->content);
        $details['has_faq'] = $hasFaq;
        if ($hasFaq) {
            $score += 4;
        }

        return ['score' => $score, 'max' => 25, 'details' => $details];
    }

    /**
     * Score content length (max 20 points).
     *
     * @return array{score: int, max: int, details: array<string, mixed>}
     */
    protected function scoreContentLength(): array
    {
        $wordCount = $this->article->word_count ?: str_word_count(strip_tags($this->content));
        $targetWordCount = $this->keyword?->target_word_count ?? 1500;

        $details = [
            'word_count' => $wordCount,
            'target_word_count' => $targetWordCount,
        ];

        $score = match (true) {
            $wordCount < 500 => 5,
            $wordCount < 1000 => 10,
            $wordCount < 1500 => 15,
            $wordCount <= 2500 => 20,
            default => 18, // Over 2500, slight penalty for being too long
        };

        // Bonus/penalty based on target achievement
        if ($targetWordCount > 0) {
            $targetRatio = $wordCount / $targetWordCount;
            $details['target_ratio'] = round($targetRatio, 2);

            if ($targetRatio >= 0.9 && $targetRatio <= 1.2) {
                // Met target, keep full score
            } elseif ($targetRatio >= 0.7) {
                $score = (int) ($score * 0.9); // 10% penalty
            } elseif ($targetRatio >= 0.5) {
                $score = (int) ($score * 0.7); // 30% penalty
            } else {
                $score = (int) ($score * 0.5); // 50% penalty
            }
        }

        return ['score' => $score, 'max' => 20, 'details' => $details];
    }

    /**
     * Score meta data quality (max 12 points).
     *
     * @return array{score: int, max: int, details: array<string, mixed>}
     */
    protected function scoreMetaQuality(): array
    {
        $details = [];
        $score = 0;

        // Title length: ideal 50-60 chars (6 points)
        $titleLength = mb_strlen($this->article->title);
        $details['title_length'] = $titleLength;
        if ($titleLength >= 50 && $titleLength <= 60) {
            $score += 6;
        } elseif ($titleLength >= 40 && $titleLength <= 70) {
            $score += 4;
        } elseif ($titleLength >= 30 && $titleLength <= 80) {
            $score += 2;
        }

        // Meta description length: ideal 150-160 chars (6 points)
        $metaLength = mb_strlen($this->article->meta_description ?? '');
        $details['meta_description_length'] = $metaLength;
        if ($metaLength >= 150 && $metaLength <= 160) {
            $score += 6;
        } elseif ($metaLength >= 120 && $metaLength <= 170) {
            $score += 4;
        } elseif ($metaLength >= 80 && $metaLength <= 200) {
            $score += 2;
        }

        return ['score' => $score, 'max' => 12, 'details' => $details];
    }

    /**
     * Score content enrichment (max 8 points).
     *
     * @return array{score: int, max: int, details: array<string, mixed>}
     */
    protected function scoreEnrichment(): array
    {
        $details = [];
        $score = 0;

        // Has image placeholders (4 points)
        $hasImages = (bool) preg_match('/\[IMAGE:|!\[/i', $this->content);
        $details['has_images'] = $hasImages;
        if ($hasImages) {
            $score += 4;
        }

        // Has internal links (4 points)
        $hasLinks = (bool) preg_match('/\[.+\]\(.+\)/', $this->content);
        $details['has_links'] = $hasLinks;
        if ($hasLinks) {
            $score += 4;
        }

        return ['score' => $score, 'max' => 8, 'details' => $details];
    }

    /**
     * Common English stop words to ignore in keyword matching.
     *
     * @var array<string>
     */
    protected const STOP_WORDS = [
        'a', 'an', 'the', 'in', 'on', 'at', 'to', 'for', 'of', 'with',
        'by', 'from', 'as', 'is', 'was', 'are', 'were', 'be', 'been',
        'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would',
        'could', 'should', 'may', 'might', 'must', 'can', 'and', 'but',
        'or', 'nor', 'so', 'yet', 'both', 'either', 'neither', 'not',
        'only', 'than', 'too', 'very', 'just', 'also', 'how', 'what',
        'when', 'where', 'why', 'who', 'which', 'this', 'that', 'these',
        'those', 'it', 'its', 'your', 'my', 'our', 'their', 'his', 'her',
    ];

    /**
     * Check if text contains the keyword (supports word variations).
     *
     * This method first tries exact phrase matching, then falls back to
     * smart matching that handles plurals, stop words, and word variations.
     */
    protected function containsKeyword(string $text, string $keyword): bool
    {
        if (empty($keyword) || empty($text)) {
            return false;
        }

        // First try exact phrase match (case-insensitive)
        $pattern = '/\b'.preg_quote($keyword, '/').'\b/i';
        if (preg_match($pattern, $text)) {
            return true;
        }

        // Fall back to smart matching with word variations
        return $this->containsKeywordSmart($text, $keyword);
    }

    /**
     * Smart keyword matching that handles word variations.
     *
     * Extracts significant words from keyword (removing stop words),
     * then checks if variations of each word appear in the text.
     */
    protected function containsKeywordSmart(string $text, string $keyword): bool
    {
        $keywordWords = $this->getSignificantWords($keyword);

        if (empty($keywordWords)) {
            return false;
        }

        $textLower = strtolower($text);

        // All significant keyword words must be found in the text
        foreach ($keywordWords as $word) {
            if (! $this->wordVariationExistsInText($word, $textLower)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Extract significant words from text (removes stop words and short words).
     *
     * @return array<string>
     */
    protected function getSignificantWords(string $text): array
    {
        $words = preg_split('/\s+/', strtolower(trim($text)));

        if ($words === false) {
            return [];
        }

        return array_values(array_filter($words, function ($word) {
            return strlen($word) > 2 && ! in_array($word, self::STOP_WORDS, true);
        }));
    }

    /**
     * Check if a variation of the keyword word exists in the text.
     *
     * Uses prefix matching to handle plurals, verb forms, etc.
     * e.g., "invoice" matches "invoices", "invoicing", "invoiced"
     */
    protected function wordVariationExistsInText(string $keywordWord, string $text): bool
    {
        $keywordWord = strtolower($keywordWord);

        // Normalize the word (remove common suffixes to get base form)
        $normalized = $this->normalizeWord($keywordWord);

        // Calculate minimum prefix length for matching
        // At least 4 chars or 75% of the normalized word
        $minPrefixLen = max(4, (int) (strlen($normalized) * 0.75));

        // Ensure we don't exceed the word length
        $minPrefixLen = min($minPrefixLen, strlen($normalized));

        $matchPrefix = substr($normalized, 0, $minPrefixLen);

        // Look for words in text that start with our prefix
        $pattern = '/\b'.preg_quote($matchPrefix, '/').'[a-z]*\b/i';

        return (bool) preg_match($pattern, $text);
    }

    /**
     * Normalize a word by removing common suffixes to get the base form.
     *
     * Handles: plurals (-s, -es, -ies), verb forms (-ed, -ing)
     */
    protected function normalizeWord(string $word): string
    {
        // -ies → -y (e.g., "stories" → "story")
        if (strlen($word) > 4 && str_ends_with($word, 'ies')) {
            return substr($word, 0, -3).'y';
        }

        // -es after sibilants (e.g., "boxes" → "box", "watches" → "watch")
        if (strlen($word) > 4 && preg_match('/(ss|sh|ch|x|z)es$/', $word)) {
            return substr($word, 0, -2);
        }

        // -s but not -ss (e.g., "invoices" → "invoice", but "boss" stays "boss")
        if (strlen($word) > 3 && str_ends_with($word, 's') && ! str_ends_with($word, 'ss')) {
            return substr($word, 0, -1);
        }

        // -ed (e.g., "invoiced" → "invoic")
        if (strlen($word) > 4 && str_ends_with($word, 'ed')) {
            return substr($word, 0, -2);
        }

        // -ing (e.g., "invoicing" → "invoic")
        if (strlen($word) > 5 && str_ends_with($word, 'ing')) {
            return substr($word, 0, -3);
        }

        return $word;
    }

    /**
     * Calculate keyword density as a percentage.
     *
     * Uses smart matching to count keyword variations, not just exact matches.
     */
    protected function calculateKeywordDensity(string $content, string $keyword): float
    {
        if (empty($keyword)) {
            return 0;
        }

        $wordCount = str_word_count(strip_tags($content));
        if ($wordCount === 0) {
            return 0;
        }

        // Get significant words from keyword
        $keywordWords = $this->getSignificantWords($keyword);
        if (empty($keywordWords)) {
            return 0;
        }

        // Count occurrences of keyword word variations in content
        $contentLower = strtolower($content);
        $totalMatches = 0;

        foreach ($keywordWords as $keywordWord) {
            $normalized = $this->normalizeWord($keywordWord);
            $minPrefixLen = max(4, (int) (strlen($normalized) * 0.75));
            $minPrefixLen = min($minPrefixLen, strlen($normalized));
            $matchPrefix = substr($normalized, 0, $minPrefixLen);

            // Count all words matching the prefix
            $pattern = '/\b'.preg_quote($matchPrefix, '/').'[a-z]*\b/i';
            $matches = preg_match_all($pattern, $contentLower);
            $totalMatches += $matches;
        }

        // Average matches per keyword word, as percentage of total words
        $keywordWordCount = count($keywordWords);

        return ($totalMatches / $keywordWordCount / $wordCount) * 100;
    }
}
