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
        Schema::create('keywords', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('keyword');
            $table->json('secondary_keywords')->nullable();
            $table->string('search_intent')->default('informational'); // informational, transactional, navigational, commercial
            $table->unsignedInteger('target_word_count')->default(1500);
            $table->string('tone')->nullable();
            $table->text('additional_instructions')->nullable();
            $table->string('status')->default('pending'); // pending, queued, generating, completed, failed
            $table->unsignedInteger('priority')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index(['status', 'priority']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('keywords');
    }
};
