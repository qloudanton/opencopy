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
        Schema::create('article_internal_link', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('internal_link_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position')->nullable(); // character position in content
            $table->string('anchor_text_used')->nullable(); // actual anchor text used
            $table->timestamps();

            $table->unique(['article_id', 'internal_link_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('article_internal_link');
    }
};
