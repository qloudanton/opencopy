<?php

namespace App\Console\Commands;

use App\Enums\ContentStatus;
use App\Jobs\GenerateArticleJob;
use App\Models\ScheduledContent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessScheduledContentCommand extends Command
{
    protected $signature = 'content:process-scheduled
                            {--days=1 : Number of days ahead to look for scheduled content}
                            {--limit=100 : Maximum number of items to process per run}
                            {--spread=60 : Spread jobs over this many minutes (0 to disable)}
                            {--dry-run : Show what would be processed without actually dispatching jobs}';

    protected $description = 'Process scheduled content that is due for generation (today or tomorrow)';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $limit = (int) $this->option('limit');
        $spreadMinutes = (int) $this->option('spread');
        $dryRun = $this->option('dry-run');

        $this->info("Checking for scheduled content due within {$days} day(s)...");

        // Find content ready to generate:
        // - Status is 'scheduled' (not yet queued for generation)
        // - Has a keyword (required for generation)
        // - Has no article yet
        // - Scheduled date is today or within the look-ahead period
        $readyContent = ScheduledContent::query()
            ->where('status', ContentStatus::Scheduled)
            ->whereNotNull('keyword_id')
            ->whereNull('article_id')
            ->whereNotNull('scheduled_date')
            ->whereDate('scheduled_date', '<=', now()->addDays($days))
            ->with(['keyword', 'project.user.aiProviders'])
            ->orderBy('scheduled_date')
            ->orderBy('scheduled_time')
            ->limit($limit)
            ->get();

        if ($readyContent->isEmpty()) {
            $this->info('No content ready to process.');

            return self::SUCCESS;
        }

        $totalCount = $readyContent->count();
        $this->info("Found {$totalCount} item(s) ready to generate.");

        if ($spreadMinutes > 0 && $totalCount > 1) {
            $this->info("Spreading jobs over {$spreadMinutes} minutes to balance load.");
        }

        $dispatched = 0;
        $skipped = 0;

        foreach ($readyContent as $index => $content) {
            $project = $content->project;

            // Get the project's effective AI provider for text generation
            $aiProvider = $project->getEffectiveTextProvider();

            if (! $aiProvider) {
                $this->warn("  - Skipping '{$content->display_title}': No AI provider configured for project '{$project->name}'");
                $skipped++;

                continue;
            }

            // Calculate delay to spread jobs evenly over the spread period
            $delay = $this->calculateDelay($index, $totalCount, $spreadMinutes);

            if ($dryRun) {
                $delayMinutes = $delay ? (int) now()->diffInMinutes($delay, false) : 0;
                $delayText = $delayMinutes > 0 ? " (delay: {$delayMinutes} min)" : '';
                $this->line("  - [DRY RUN] Would dispatch: {$content->display_title} (scheduled: {$content->scheduled_date->format('Y-m-d')}){$delayText}");
                $dispatched++;

                continue;
            }

            // Update status to queued before dispatching
            $content->update(['status' => ContentStatus::Queued]);

            $delayMinutes = $delay ? (int) now()->diffInMinutes($delay, false) : 0;

            Log::info('[ScheduledGeneration] Dispatching GenerateArticleJob', [
                'scheduled_content_id' => $content->id,
                'keyword_id' => $content->keyword_id,
                'keyword' => $content->keyword->keyword,
                'scheduled_date' => $content->scheduled_date->format('Y-m-d'),
                'ai_provider' => $aiProvider->name,
                'delay_minutes' => $delayMinutes,
            ]);

            if ($delay) {
                GenerateArticleJob::dispatch($content, $aiProvider)->delay($delay);
            } else {
                GenerateArticleJob::dispatch($content, $aiProvider);
            }

            $delayText = $delayMinutes > 0 ? " (starts in {$delayMinutes} min)" : '';
            $this->line("  - Dispatched: {$content->display_title}{$delayText}");
            $dispatched++;
        }

        $this->newLine();
        $this->info("Summary: {$dispatched} dispatched, {$skipped} skipped.");

        if ($dryRun) {
            $this->warn('This was a dry run. No jobs were actually dispatched.');
        }

        return self::SUCCESS;
    }

    /**
     * Calculate the delay for a job to spread load evenly.
     */
    protected function calculateDelay(int $index, int $total, int $spreadMinutes): ?\Carbon\Carbon
    {
        if ($spreadMinutes <= 0 || $total <= 1) {
            return null;
        }

        // Spread jobs evenly: first job starts now, last job starts at spreadMinutes
        // For 100 jobs over 60 min: job 0 = 0 min, job 50 = 30 min, job 99 = ~59 min
        $delayMinutes = (int) (($index / ($total - 1)) * $spreadMinutes);

        if ($delayMinutes === 0) {
            return null;
        }

        return now()->addMinutes($delayMinutes);
    }
}
