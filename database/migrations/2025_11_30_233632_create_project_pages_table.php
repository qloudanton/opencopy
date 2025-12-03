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
        Schema::create('project_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('url');
            $table->string('title')->nullable();
            $table->string('page_type')->default('other'); // blog, product, service, landing, other
            $table->json('keywords')->nullable(); // Extracted keywords from URL/title
            $table->decimal('priority', 3, 2)->default(0.5); // 0.0-1.0 from sitemap
            $table->unsignedInteger('link_count')->default(0); // Times linked in articles
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_modified_at')->nullable(); // From sitemap lastmod
            $table->timestamp('last_fetched_at')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'url']);
            $table->index(['project_id', 'page_type', 'is_active']);
            $table->index(['project_id', 'link_count']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_pages');
    }
};
