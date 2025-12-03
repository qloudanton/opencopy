<?php

namespace App\Enums;

enum IntegrationType: string
{
    case Webhook = 'webhook';
    case WordPress = 'wordpress';
    case Webflow = 'webflow';
    case Shopify = 'shopify';
    case Wix = 'wix';

    public function label(): string
    {
        return match ($this) {
            self::Webhook => 'Webhook',
            self::WordPress => 'WordPress',
            self::Webflow => 'Webflow',
            self::Shopify => 'Shopify',
            self::Wix => 'Wix',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Webhook => 'Send content to any endpoint via HTTP POST',
            self::WordPress => 'Publish directly to WordPress via REST API',
            self::Webflow => 'Publish to Webflow CMS collections',
            self::Shopify => 'Create blog posts in Shopify',
            self::Wix => 'Publish to Wix blog',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Webhook => 'webhook',
            self::WordPress => 'wordpress',
            self::Webflow => 'webflow',
            self::Shopify => 'shopify',
            self::Wix => 'wix',
        };
    }

    public function isAvailable(): bool
    {
        return match ($this) {
            self::Webhook => true,
            self::WordPress => false, // Coming soon
            self::Webflow => false,
            self::Shopify => false,
            self::Wix => false,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function requiredCredentials(): array
    {
        return match ($this) {
            self::Webhook => [
                'endpoint_url' => ['type' => 'url', 'label' => 'Webhook URL', 'required' => true],
                'access_token' => ['type' => 'password', 'label' => 'Access Token', 'required' => true],
            ],
            self::WordPress => [
                'site_url' => ['type' => 'url', 'label' => 'WordPress Site URL', 'required' => true],
                'username' => ['type' => 'text', 'label' => 'Username', 'required' => true],
                'application_password' => ['type' => 'password', 'label' => 'Application Password', 'required' => true],
            ],
            self::Webflow => [
                'api_token' => ['type' => 'password', 'label' => 'API Token', 'required' => true],
                'collection_id' => ['type' => 'text', 'label' => 'Collection ID', 'required' => true],
            ],
            self::Shopify => [
                'store_url' => ['type' => 'url', 'label' => 'Store URL', 'required' => true],
                'access_token' => ['type' => 'password', 'label' => 'Access Token', 'required' => true],
                'blog_id' => ['type' => 'text', 'label' => 'Blog ID', 'required' => true],
            ],
            self::Wix => [
                'api_key' => ['type' => 'password', 'label' => 'API Key', 'required' => true],
                'site_id' => ['type' => 'text', 'label' => 'Site ID', 'required' => true],
            ],
        };
    }

    /**
     * @return array<self>
     */
    public static function available(): array
    {
        return array_filter(self::cases(), fn (self $type) => $type->isAvailable());
    }
}
