<?php

namespace App\Events;

use App\Models\Article;
use App\Models\Integration;
use App\Models\Publication;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when an article is successfully published to an integration.
 */
class ArticlePublished
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Article $article,
        public Integration $integration,
        public Publication $publication,
    ) {}
}
