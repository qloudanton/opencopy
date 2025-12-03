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
            $table->boolean('include_youtube_videos')->default(true)->change();
            $table->boolean('include_infographic_placeholders')->default(true)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->boolean('include_youtube_videos')->default(false)->change();
            $table->boolean('include_infographic_placeholders')->default(false)->change();
        });
    }
};
