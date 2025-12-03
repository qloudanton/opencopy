<?php

namespace App\Contracts\Publishing;

/**
 * Contract for content that can be published to external integrations.
 *
 * Any model that implements this contract can be published through
 * the publishing system. Currently implemented by Article.
 */
interface PublishableContract
{
    /**
     * Get the unique identifier for this content.
     */
    public function getPublishableId(): int;

    /**
     * Get the title/headline of the content.
     */
    public function getPublishableTitle(): string;

    /**
     * Get the URL-friendly slug.
     */
    public function getPublishableSlug(): string;

    /**
     * Get the content as HTML.
     */
    public function getPublishableHtml(): string;

    /**
     * Get the content as Markdown.
     */
    public function getPublishableMarkdown(): string;

    /**
     * Get the meta description for SEO.
     */
    public function getPublishableMetaDescription(): ?string;

    /**
     * Get the short excerpt/summary.
     */
    public function getPublishableExcerpt(): ?string;

    /**
     * Get the featured image URL if available.
     */
    public function getPublishableFeaturedImageUrl(): ?string;

    /**
     * Get associated tags/categories.
     *
     * @return array<string>
     */
    public function getPublishableTags(): array;

    /**
     * Get the creation timestamp.
     */
    public function getPublishableCreatedAt(): \DateTimeInterface;

    /**
     * Convert to the standard publishing payload array.
     *
     * @return array<string, mixed>
     */
    public function toPublishableArray(): array;
}
