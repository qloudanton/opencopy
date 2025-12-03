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
        Schema::create('usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('article_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ai_provider_id')->nullable()->constrained()->nullOnDelete();
            $table->string('operation'); // text_generation, image_generation, improvement
            $table->string('model')->nullable(); // gpt-4o, claude-sonnet-4, dall-e-3, etc.
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->unsignedInteger('image_count')->nullable();
            $table->string('image_size')->nullable(); // 1024x1024, 1792x1024, etc.
            $table->string('image_quality')->nullable(); // standard, hd, high, medium, low
            $table->decimal('estimated_cost', 10, 6); // Cost in USD
            $table->json('metadata')->nullable(); // Additional context
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['article_id', 'operation']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usage_logs');
    }
};
