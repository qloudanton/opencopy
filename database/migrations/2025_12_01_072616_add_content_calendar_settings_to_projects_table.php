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
            // Publishing cadence settings
            $table->unsignedInteger('posts_per_week')->default(3)->after('settings');
            $table->json('publishing_days')->nullable()->after('posts_per_week');
            $table->time('default_publish_time')->default('09:00:00')->after('publishing_days');

            // Auto-generation settings
            $table->boolean('auto_generate_enabled')->default(false)->after('default_publish_time');
            $table->unsignedInteger('auto_generate_days_ahead')->default(7)->after('auto_generate_enabled');

            // Calendar view preferences
            $table->string('calendar_view')->default('month')->after('auto_generate_days_ahead');
            $table->string('calendar_start_day')->default('monday')->after('calendar_view');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'posts_per_week',
                'publishing_days',
                'default_publish_time',
                'auto_generate_enabled',
                'auto_generate_days_ahead',
                'calendar_view',
                'calendar_start_day',
            ]);
        });
    }
};
