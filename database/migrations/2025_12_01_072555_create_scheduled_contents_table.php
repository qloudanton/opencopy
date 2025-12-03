<?php

use App\Enums\ContentStatus;
use App\Enums\ContentType;
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
        Schema::create('scheduled_contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('keyword_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('article_id')->nullable()->constrained()->nullOnDelete();

            // Content details
            $table->string('title')->nullable();
            $table->string('content_type')->default(ContentType::BlogPost->value);
            $table->string('status')->default(ContentStatus::Backlog->value);

            // Scheduling
            $table->date('scheduled_date')->nullable();
            $table->time('scheduled_time')->nullable();
            $table->unsignedInteger('position')->default(0);

            // Content settings (override project defaults if set)
            $table->unsignedInteger('target_word_count')->nullable();
            $table->string('tone')->nullable();
            $table->text('custom_instructions')->nullable();

            // Metadata
            $table->text('notes')->nullable();
            $table->unsignedInteger('generation_attempts')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('generation_started_at')->nullable();
            $table->timestamp('generation_completed_at')->nullable();
            $table->timestamps();

            // Indexes for efficient queries
            $table->index(['project_id', 'status']);
            $table->index(['project_id', 'scheduled_date']);
            $table->index(['project_id', 'scheduled_date', 'status']);
            $table->index(['status', 'scheduled_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scheduled_contents');
    }
};
