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
            $table->string('sitemap_url')->nullable()->after('cta_action_text');
            $table->boolean('auto_internal_linking')->default(false)->after('sitemap_url');
            $table->boolean('prioritize_blog_links')->default(true)->after('auto_internal_linking');
            $table->boolean('cross_link_articles')->default(true)->after('prioritize_blog_links');
            $table->timestamp('sitemap_last_fetched_at')->nullable()->after('cross_link_articles');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'sitemap_url',
                'auto_internal_linking',
                'prioritize_blog_links',
                'cross_link_articles',
                'sitemap_last_fetched_at',
            ]);
        });
    }
};
