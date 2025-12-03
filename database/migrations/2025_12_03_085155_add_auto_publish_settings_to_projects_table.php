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
            // auto_publish: manual (default), immediate (publish right after generation), scheduled (publish at scheduled_date)
            $table->string('auto_publish')->default('manual')->after('generate_featured_image');
            // skip_review: if true, auto-approve articles after generation (skip InReview state)
            $table->boolean('skip_review')->default(false)->after('auto_publish');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['auto_publish', 'skip_review']);
        });
    }
};
