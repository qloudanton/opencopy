<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // Brand & Visual
            $table->string('brand_color', 7)
                ->nullable()
                ->after('internal_links_per_article');
            $table->string('image_style', 50)
                ->default('illustration')
                ->after('brand_color');

            // Content Enhancements
            $table->boolean('include_youtube_videos')
                ->default(false)
                ->after('image_style');
            $table->boolean('include_emojis')
                ->default(false)
                ->after('include_youtube_videos');
            $table->boolean('include_infographic_placeholders')
                ->default(false)
                ->after('include_emojis');

            // Call-to-Action
            $table->boolean('include_cta')
                ->default(true)
                ->after('include_infographic_placeholders');
            $table->string('cta_product_name')
                ->nullable()
                ->after('include_cta');
            $table->string('cta_website_url')
                ->nullable()
                ->after('cta_product_name');
            $table->text('cta_features')
                ->nullable()
                ->after('cta_website_url');
            $table->string('cta_action_text')
                ->nullable()
                ->after('cta_features');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'brand_color',
                'image_style',
                'include_youtube_videos',
                'include_emojis',
                'include_infographic_placeholders',
                'include_cta',
                'cta_product_name',
                'cta_website_url',
                'cta_features',
                'cta_action_text',
            ]);
        });
    }
};
