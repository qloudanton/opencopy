<?php

namespace App\Enums;

enum ContentType: string
{
    case BlogPost = 'blog_post';
    case Listicle = 'listicle';
    case HowTo = 'how_to';
    case Comparison = 'comparison';
    case CaseStudy = 'case_study';
    case Review = 'review';
    case NewsArticle = 'news_article';
    case PillarContent = 'pillar_content';

    public function label(): string
    {
        return match ($this) {
            self::BlogPost => 'Blog Post',
            self::Listicle => 'Listicle',
            self::HowTo => 'How-To Guide',
            self::Comparison => 'Comparison',
            self::CaseStudy => 'Case Study',
            self::Review => 'Review',
            self::NewsArticle => 'News Article',
            self::PillarContent => 'Pillar Content',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::BlogPost => 'Standard blog post with introduction, body, and conclusion',
            self::Listicle => 'List-based article (e.g., "10 Best Ways to...")',
            self::HowTo => 'Step-by-step tutorial or guide',
            self::Comparison => 'Side-by-side comparison of products, services, or concepts',
            self::CaseStudy => 'In-depth analysis of a specific example or success story',
            self::Review => 'Product or service review with pros, cons, and verdict',
            self::NewsArticle => 'Timely news or industry update',
            self::PillarContent => 'Comprehensive cornerstone content covering a broad topic',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::BlogPost => 'file-text',
            self::Listicle => 'list-ordered',
            self::HowTo => 'book-open',
            self::Comparison => 'scale',
            self::CaseStudy => 'briefcase',
            self::Review => 'star',
            self::NewsArticle => 'newspaper',
            self::PillarContent => 'landmark',
        };
    }

    public function suggestedWordCount(): int
    {
        return match ($this) {
            self::BlogPost => 1500,
            self::Listicle => 2000,
            self::HowTo => 2500,
            self::Comparison => 2000,
            self::CaseStudy => 2500,
            self::Review => 1800,
            self::NewsArticle => 800,
            self::PillarContent => 4000,
        };
    }

    /**
     * @return array<self>
     */
    public static function defaultTypes(): array
    {
        return [
            self::BlogPost,
            self::Listicle,
            self::HowTo,
            self::Comparison,
        ];
    }
}
