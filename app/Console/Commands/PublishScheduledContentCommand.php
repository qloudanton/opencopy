<?php

namespace App\Console\Commands;

use App\Enums\ContentStatus;
use App\Jobs\PublishArticleJob;
use App\Models\ScheduledContent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PublishScheduledContentCommand extends Command
{
    protected $signature = 'content:publish-scheduled';

    protected $description = 'Publish scheduled content that has reached its scheduled date/time';

    public function handle(): int
    {
        $this->info('Checking for scheduled content ready to publish...');

        // Find content ready to publish:
        // - Project has auto_publish = 'scheduled'
        // - Status is Approved
        // - Has an article
        // - scheduled_date has passed (with optional scheduled_time)
        $readyContent = ScheduledContent::query()
            ->whereHas('project', fn ($q) => $q->where('auto_publish', 'scheduled'))
            ->whereNotNull('article_id')
            ->where('status', ContentStatus::Approved)
            ->where(function ($query) {
                // Content where scheduled date/time has passed
                $query->where(function ($q) {
                    // If scheduled_time is set, combine with date
                    $q->whereNotNull('scheduled_time')
                        ->whereRaw("CONCAT(DATE(scheduled_date), ' ', TIME(scheduled_time)) <= ?", [now()]);
                })->orWhere(function ($q) {
                    // If no scheduled_time, just check the date
                    $q->whereNull('scheduled_time')
                        ->whereDate('scheduled_date', '<=', now());
                });
            })
            ->with(['article', 'project'])
            ->get();

        if ($readyContent->isEmpty()) {
            $this->info('No content ready to publish.');

            return self::SUCCESS;
        }

        $this->info("Found {$readyContent->count()} item(s) ready to publish.");

        foreach ($readyContent as $content) {
            $this->line("  - Dispatching publish job for article: {$content->article->title}");

            Log::info('[ScheduledPublish] Dispatching PublishArticleJob', [
                'scheduled_content_id' => $content->id,
                'article_id' => $content->article_id,
                'scheduled_date' => $content->scheduled_date,
                'scheduled_time' => $content->scheduled_time,
            ]);

            PublishArticleJob::dispatch($content->article);
        }

        $this->info('Done.');

        return self::SUCCESS;
    }
}
