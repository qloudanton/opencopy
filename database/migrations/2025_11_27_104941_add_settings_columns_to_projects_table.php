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
            $table->foreignId('default_ai_provider_id')
                ->nullable()
                ->after('is_active')
                ->constrained('ai_providers')
                ->nullOnDelete();
            $table->unsignedInteger('default_word_count')
                ->default(1500)
                ->after('default_ai_provider_id');
            $table->string('default_tone', 50)
                ->default('professional')
                ->after('default_word_count');
            $table->string('target_audience')
                ->nullable()
                ->after('default_tone');
            $table->text('brand_guidelines')
                ->nullable()
                ->after('target_audience');
            $table->string('primary_language', 10)
                ->default('en')
                ->after('brand_guidelines');
            $table->string('target_region', 50)
                ->nullable()
                ->after('primary_language');
            $table->unsignedTinyInteger('internal_links_per_article')
                ->default(3)
                ->after('target_region');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['default_ai_provider_id']);
            $table->dropColumn([
                'default_ai_provider_id',
                'default_word_count',
                'default_tone',
                'target_audience',
                'brand_guidelines',
                'primary_language',
                'target_region',
                'internal_links_per_article',
            ]);
        });
    }
};
