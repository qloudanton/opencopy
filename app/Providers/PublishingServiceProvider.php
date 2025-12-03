<?php

namespace App\Providers;

use App\Services\Publishing\PublisherFactory;
use App\Services\Publishing\PublishingService;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider for the publishing system.
 *
 * Registers the PublisherFactory and PublishingService as singletons,
 * ensuring consistent state across the application.
 */
class PublishingServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(PublisherFactory::class, function ($app) {
            return new PublisherFactory;
        });

        $this->app->singleton(PublishingService::class, function ($app) {
            return new PublishingService(
                $app->make(PublisherFactory::class)
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
