<?php

namespace App\Observers;

use App\Enums\ContentStatus;
use App\Enums\ContentType;
use App\Models\Keyword;
use App\Models\ScheduledContent;

class KeywordObserver
{
    /**
     * Handle the Keyword "created" event.
     */
    public function created(Keyword $keyword): void
    {
        // Automatically add keyword to content planner backlog
        $contentType = $this->suggestContentType($keyword);

        ScheduledContent::create([
            'project_id' => $keyword->project_id,
            'keyword_id' => $keyword->id,
            'title' => null, // Will use keyword as display title
            'content_type' => $contentType,
            'status' => ContentStatus::Backlog,
            'target_word_count' => $keyword->target_word_count ?? $contentType->suggestedWordCount(),
            'tone' => $keyword->tone,
        ]);
    }

    /**
     * Handle the Keyword "deleted" event.
     */
    public function deleted(Keyword $keyword): void
    {
        // ScheduledContent will be automatically nullified via foreign key constraint
        // (nullOnDelete), but we can also clean up orphaned entries if needed
    }

    /**
     * Suggest content type based on keyword patterns.
     */
    protected function suggestContentType(Keyword $keyword): ContentType
    {
        $kw = strtolower($keyword->keyword);

        return match (true) {
            str_contains($kw, 'how to') || str_contains($kw, 'guide') => ContentType::HowTo,
            str_contains($kw, 'vs') || str_contains($kw, 'versus') || str_contains($kw, 'compared') => ContentType::Comparison,
            str_contains($kw, 'best') || str_contains($kw, 'top') || (bool) preg_match('/\d+\s+(ways|tips|ideas|reasons)/', $kw) => ContentType::Listicle,
            str_contains($kw, 'review') => ContentType::Review,
            str_contains($kw, 'case study') || str_contains($kw, 'success story') => ContentType::CaseStudy,
            str_contains($kw, 'news') || str_contains($kw, 'update') || str_contains($kw, 'announcement') => ContentType::NewsArticle,
            str_contains($kw, 'complete guide') || str_contains($kw, 'ultimate') || str_contains($kw, 'everything') => ContentType::PillarContent,
            default => ContentType::BlogPost,
        };
    }
}
